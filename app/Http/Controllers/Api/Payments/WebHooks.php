<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Auth\{BearerTrait, RequestMethod};
use App\Rules\ValidHubtelDirectWebhookResponse;
use Illuminate\Support\Facades\Log;
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
use Bundana\Services\Messaging\Mnotify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebHooks extends Controller
{
      use BearerTrait, RequestMethod;
      private const VERSION = 1;
      private const MERCHANT_ID = 2019424;
      private const USERNAME = "KZzq12r";
      private const PASSWORD = "7a80136175c444159f34362e4ca2fe96";

      private function _encodeAuthHeader()
      {
            return base64_encode(self::USERNAME . ':' . self::PASSWORD);
      }
      public function validateHubtelDirectWebhookResponse(Request $request)
      {
            $data = $request->all();

            // Validate the Hubtel direct web response
            if (
                  !isset($data['ResponseCode']) ||
                  !isset($data['Message']) ||
                  !isset($data['Data']) ||
                  !is_array($data['Data']) ||
                  !isset($data['Data']['Amount']) ||
                  !isset($data['Data']['Charges']) ||
                  !isset($data['Data']['AmountAfterCharges']) ||
                  !isset($data['Data']['Description']) ||
                  !isset($data['Data']['ClientReference']) ||
                  !isset($data['Data']['TransactionId']) ||
                  !isset($data['Data']['ExternalTransactionId']) ||
                  !isset($data['Data']['AmountCharged']) ||
                  !isset($data['Data']['OrderId'])
            ) {
                  return response()->json(['success' => false, 'message' => 'Invalid or missing required webhook fields.', 'errorCode' => 400], 400);
            }

            // Continue processing the webhook response since it's valid
            return response()->json(['message' => 'Webhook response is valid.'], 200);
      }

      public function verifyDirectTransaction(Request $request, $clientReference)
      {
            $authHeader = new Hubtel();
            $authHeader = $authHeader->_encodeAuthHeader(); //API Keys to Base64 for authentication

            if (empty($clientReference)) {
                  return response()->json(['success' => false, 'message' => 'Payment Reference is required', 'errorCode' => 400], 400);
            }
            // Make a POST request using Laravel's HTTP client
            $paymentData = [
                  "clientReference" => $clientReference,
            ];
            try {
                  $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic ' . $authHeader // Include Basic Authorization header
                  ])->post('https://api-txnstatus.hubtel.com/transactions/2019424/status', $paymentData);
                  $response = $response->json();
                  if (!isset($response['responseCode']) || $response['responseCode'] !== "0000") {
                        return response()->json(['success' => false, 'message' => 'Payment wasn\'t verified'], 400);
                  }
                  return response()->json(['success' => true, 'message' => 'Payment Verified Successful'], 200);
            } catch (\Exception $e) {
                  return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on Hubtel\'s end.', 'errorCode' => 500], 500);
            }
      }

      public function directUssdWebhook(Request $request)
      {
            // Validate the incoming webhook response
            $validationResult = $this->validateHubtelDirectWebhookResponse($request);

            // If validation fails, return the validation result
            if (!$validationResult->isOk()) {
                  return $validationResult;
            }
            $input = $request->all();
            if (!isset($input['Data']) || !isset($input['ResponseCode']) || $input['ResponseCode'] !== "0000" || !isset($input['Message']) || isset($input['Message']) != "success") {
                  return response()->json(['success' => false, 'message' => 'Webhook verification failed', 'errorCode' => 400], 400); // Return a response indicating verification failure
            }

            // Extract the 'Data' array from the input
            $data = $input['Data'];
            $donationData = []; // Initialize an array to store fetched donation data
            $clientReference = $data['ClientReference'];
            // Retrieve the JSON string from the database
            $unpaidDonations = UnpaidDonationsReceipts::where('reference', $clientReference)->first();

            if (!$unpaidDonations) {
                  return response()->json(['success' => false, 'message' => 'Payment Reference not found', 'errorCode' => 404], 404);
            }

            $authHeader = new Hubtel();
            $authHeader = $authHeader->_encodeAuthHeader(); //API Keys to Base64 for authentication

            // Call the verifyDirectTransaction method
            $verificationResponse = $this->verifyDirectTransaction($request, $clientReference);

            // Check if the response is an instance of JsonResponse
            if (!$verificationResponse instanceof \Illuminate\Http\JsonResponse) {
                  // Handle the case where the response is not a JsonResponse
                  return response()->json(['success' => false, 'message' => 'Invalid verification response'], 500);
            }

            // Get the response content as an array
            $verificationStatus = $verificationResponse->original;

            // Check the verification status and return a response accordingly
            if (!isset($verificationStatus['success']) || $verificationStatus['success'] !== true) {
                  return response()->json(['success' => false, 'message' => $verificationStatus['message'], 'errorCode' => $verificationStatus['message']], 404);
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
                        return response()->json(['success' => false, 'message' => 'Payment User owner/manager not found', 'errorCode' => 404], 404);
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
                        return response()->json(['success' => false, 'message' => 'Campaign not found', 'errorCode' => 404], 404);
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
                        $sms_content = "Hi $agentName, you have successfully paid ₵{$amount} for campaign\n";
                        Mnotify::to($user->phone_number)
                        ->message($sms_content)
                        ->send();
                        UserAccountNotification::create([
                              'user_id' => $user->user_id,
                              'type' => 'campaign',
                              'title' => 'Cash Donations Paid Successfully',
                              'message' => "You have successfully paid all of your cash for selected donations"
                        ]);
                  } else {
                        $sms_content = "Hi, thanks for the donation of ₵{$amount} for {$campaign->name},
                        Learn more at  {$campaign->short_url}.  God bless you\n";
                        Mnotify::to($unpaidDonations->phone)
                        ->message($sms_content)
                        ->send(); 

                  } 
                  // Delete the record from the database
                  // $unpaidDonations->delete();

                  $title = Str::ucfirst($unpaidDonations->type);
                  return response()->json(['success' => false, 'message' => "$title Payment verified successfully", 'errorCode' => 200], 200);
            } catch (\Exception $e) {
                  return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on Hubtel\'s end.' . $e, 'errorCode' => 500], 500);
            }
      }
}
