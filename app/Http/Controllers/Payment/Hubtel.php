<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\Helpers;
use App\Http\Controllers\Utilities\Messaging\SMS; 
use App\Models\Campaigns\Campaign;
use App\Models\Campaigns\Commission;
use App\Models\Campaigns\Donations;
use App\Models\Campaigns\FundraiserAccount;
use App\Models\UnpaidDonationsReceipts;
use App\Models\User;
use App\Models\UserAccountNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 *
 */
class Hubtel extends Controller
{
    private const CALLBACKURL = 'https://webhook.site/af650e89-62f9-4694-8fad-fc92279f098e' ?: 'https://bundanatechnologies.com/';
    public $totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $clientReference;
    private const MERCHANT_NUMBER = '';
    private const PAYMENT_CALLBACK = '';
    private $reference;

    public function generateInvoice($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl)
    {
        $this->totalAmount = $totalAmount;
        $this->description = $description;
        $this->callbackUrl = $callbackUrl;
        $this->returnUrl = $returnUrl;
        $this->cancellationUrl = $cancellationUrl;
        $this->clientReference = Str::random(24);

        // Instantiate the Hubtel class with your parameters
        $hubtel = new Hubtel($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $this->clientReference);

        // Call the initiate method to make the API request
        $response = $hubtel->initiate();
        // Decode the JSON response
        $responseArray = json_decode($response, true);

        // Check the status and handle the response accordingly
        $checkoutDirectUrl = '';
        $checkoutUrl = '';

        if ($responseArray['status']) {
            // Success case
            $responseData = $responseArray['data'];
            $clientReference = $responseData['clientReference'];
            $checkoutUrl = $responseData['checkoutUrl'];
            $checkoutId = $responseData['checkoutId'];
            $checkoutDirectUrl = $responseData['checkoutDirectUrl'];

            return json_encode(['checkoutUrl' => $checkoutUrl, 'checkoutId' => $checkoutId, 'checkoutDirectUrl' => $checkoutDirectUrl, 'clientReference' => $clientReference]);
        } else {
            // Error case
            $errorData = isset($responseArray['error']) ? $responseArray['error'] : 'Unknown error';
            return json_encode(['error' => $errorData]);
        }
    }


    public function webhook(Request $request)
    {
        $input = $request->all();


        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 500);
        }

        if (!isset($input['Data']) || !isset($input['ResponseCode']) || $input['ResponseCode'] !== "0000") {
            return response()->json(['success' => false, 'message' => 'Payment failed'], 500);
        }

        $data = $input['Data'];
        // Further access nested elements
        $checkoutId = $data['CheckoutId'];
        $salesInvoiceId = $data['ClientReference'];
        $clientReference = $data['ClientReference'];
        $donationData = []; // Initialize an array to store fetched donation data

        // Retrieve the JSON string from the database
        $unpaidDonations = UnpaidDonationsReceipts::where('reference', $clientReference)->first();

        if (!$unpaidDonations) {
            return response()->json(['success' => false, 'message' => 'Reference not found'], 404);
        }

        try {
            // Wrap database operations in a transaction
            // DB::beginTransaction();
            // Decode the JSON string into an array
            $donationReferencesArray = json_decode($unpaidDonations->data);

            $user = User::where('user_id', $unpaidDonations->user_id)->first();

            $agentName = '';
            $agentNumber = '';

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            foreach ($donationReferencesArray as $donationRef) {
                $donation = Donations::where('donation_ref', $donationRef)->first();

                if ($donation) {
                    // Update the status attribute to 'paid'
                    $donation->status = 'paid';
                    $donation->save();
                }
            }

            $campaign_id = $unpaidDonations->campaign_id;
            $amount = $unpaidDonations->amount;

            // Find the campaign with the given ID
            $campaign = Campaign::where('campaign_id', $campaign_id)->first();

            // Check if the campaign exists; if not, redirect back with an error message
            if (!$campaign) {
                return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
            }

            $commission = Commission::where('user_id', $campaign->manager_id)->where('campaign_id', $campaign_id)->first();
            $balance = FundraiserAccount::where('campaign_id', $campaign_id)->where('user_id', $campaign->manager_id)->first();

            //platform usage comission is 15%
            $plaftformCommisionPercentage = '';

            if (isset($commission)) {
                $plaftformCommisionPercentage = $commission->commission;
            } else {
                $plaftformCommisionPercentage = 15;
            }

            $plaftformCommision = max(0, number_format(($amount * $plaftformCommisionPercentage) / 100, 2));
            $remainingAmount = max(0, number_format($amount - $plaftformCommision, 2));
            $remainingAmount = str_replace(',', '', $remainingAmount);

            // Insert or update balance
            if ($balance) {
                $accBalance = $balance->balance ?: 0;
                $balance->update([
                    'balance' => DB::raw($accBalance + $remainingAmount),
                ]);
            } else {
                FundraiserAccount::updateOrInsert(
                    ['campaign_id' => $campaign_id, 'user_id' => $campaign->manager_id],
                    [
                        'campaign_id' => $campaign_id,
                        'user_id' => $campaign->manager_id,
                        'balance' => $remainingAmount,
                    ]
                );
            }

            if ($unpaidDonations->type == 'receipt') {
                // Send SMS notices
                $agentName = Helpers::getFirstName($user->name);
                $agentNumber = $user->phone_number;
                $sms_content = "Hi $agentName, you have successfully paid ₵{$amount} for campaign\n";
                $sms = new SMS($agentNumber, $sms_content);
                // $sms->singleSendSMS();
                UserAccountNotification::create([
                    'user_id' => $user->user_id,
                    'type' => 'campaign',
                    'title' => 'Cash Donations Paid Successfully',
                    'message' => "You have successfully paid all of your cash for selected donations"
                ]);
            } else {
                $agentName = Helpers::getFirstName($user->name);
                $agentNumber = $unpaidDonations->phone;
                $sms_content = "Hi thanks for your donation of ₵{$amount} for campaign { $campaign->name}, God bless you\n";
                $sms = new SMS($agentNumber, $sms_content);
                $sms->singleSendSMS();
            }
            // Delete the record from the database
            // $unpaidDonations->delete();

            $title = Str::ucfirst($unpaidDonations->type);
            return response()->json(['success' => true, 'message' => "$title Payment verified successfully"], 200);
        } catch (\Exception $e) {
            // Rollback the transaction on error
            // Log the exception for debugging
            Log::error('Webhook processing failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Internal Server Error' . $e->getMessage()], 500);
        }
    }

}
