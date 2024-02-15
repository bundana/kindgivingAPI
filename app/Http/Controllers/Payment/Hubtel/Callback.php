<?php

namespace App\Http\Controllers\Payment\Hubtel;

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
use App\Models\USSDSESSION;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Callback extends Controller
{
    public function onlineCheckoutWebhook(Request $request)
    {
        $input = $request->all();


        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 500);
        }

        if (!isset($input['Data']) || !isset($input['ResponseCode']) || $input['ResponseCode'] !== "0000") {
            return response()->json(['success' => false, 'message' => 'Payment failed'], 500);
        }

        // Extract the 'Data' array from the input
        $data = $input['Data'];

        // Extract specific elements from the 'Data' array
        $checkoutId = $data['CheckoutId'];
        $salesInvoiceId = $data['SalesInvoiceId'];
        $clientReference = $data['ClientReference'];

        // Check if required fields are present in the 'Data' array
        $requiredFields = ["CheckoutId", "SalesInvoiceId", "ClientReference", "Status", "Amount", "CustomerPhoneNumber", "PaymentDetails", "Description"];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                echo "Invalid data - Missing required field: $field";
                exit;
            }
        }

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
                $sms_content = "Hi, thanks for the donation of ₵{$amount} for {$campaign->name},
                Learn more at  {$campaign->short_url}  God bless you\n";

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

    public function directPaymentPromptWebhook(Request $request)
    {
        $input = $request->all();


        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 500);
        }

        if (!isset($input['Data']) || !isset($input['ResponseCode']) || $input['ResponseCode'] !== "0000" || !isset($input['Message']) || isset($input['Message']) != "success") {
            return response()->json(['success' => false, 'message' => 'Payment failed'], 500);
        }

        // Extract the 'Data' array from the input
        $data = $input['Data'];

        // Check if required fields are present in the 'Data' array
        $requiredFields = ["Amount", "Charges", "AmountAfterCharges", "Description", "ClientReference", "TransactionId", "ExternalTransactionId", "AmountCharged", "OrderId"];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                echo "Invalid data - Missing required field: $field";
                exit;
            }
        }
        $donationData = []; // Initialize an array to store fetched donation data
        $clientReference = $data['ClientReference'];
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
                $sms_content = "Hi, thanks for the donation of ₵{$amount} for {$campaign->name},
                Learn more at  {$campaign->short_url}  God bless you\n";

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

    public function ussdPaymentPromptWebhook(Request $request)
    {
        $input = $request->all();


        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access'], 500);
        }

        if (!isset($input['OrderInfo']) || !isset($input['SessionId']) || !isset($input['OrderId'])) {
            return response()->json(['success' => false, 'message' => 'Payment failed'], 500);
        }


        // Extract the 'OrderInfo' array from the input
        $data = $input['OrderInfo'];

     

        // Check if required fields are present in the 'OrderInfo' array
        $requiredFields = ["CustomerMobileNumber", "CustomerName", "Status"];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return response()->json(['success' => false, 'message' => 'Invalid data - Missing required field: ' . $field], 500);
            }
        }  
        
        // Extract the 'SessionId' from the input
        $sessionId = $input['SessionId'];
        
        try {
            //    get session
            $ussdSession = USSDSESSION::where('session_id', $sessionId)->first();
            
            if (!$ussdSession) {
                return response()->json(['success' => false, 'message' => 'Session not found'], 404);
            }
 
            $campaign_id = $ussdSession->campaign_id;
            $amount = $ussdSession->donation_amount;

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
            $donation_ref = Str::random(10);
            $userPhone = $ussdSession->mobile;

            // create donation
            $donation = Donations::create([
                'creator' => $campaign->manager_id,
                'campaign_id' => $campaign->campaign_id,
                'donation_ref' => $donation_ref,
                'momo_number' => $userPhone,
                'amount' => (int) $amount,
                'donor_name' => 'Anonymous Donor',
                'method' => 'ussd',
                'agent_id' => $campaign->manager_id,
                'status' => 'paid',
                'platform_tip' => 0,
                'hide_donor' => 'no',
                'comment' => '',
                'country' => 'ghana',
            ]);
            $sms_content = "Hi, thanks for the donation of ₵{$amount} for {$campaign->name},
                Learn more at  {$campaign->short_url}  God bless you\n";

            $sms = new SMS($userPhone, $sms_content);

           $sms->singleSendSMS();

            // Delete the record from the database
            $ussdSession->delete(); 
            return response()->json(['success' => true, 'message' => "Payment verified successfully"], 200);
        } catch (\Exception $e) {
            // Rollback the transaction on error
            // Log the exception for debugging
            Log::error('Webhook processing failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Internal Server Error' . $e->getMessage()], 500);
        }
    }
}
