<?php

namespace App\Http\Controllers\Utilities\Payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\Helpers;
use App\Http\Controllers\Utilities\Messaging\{SMS, WhatsApp};
use App\Models\Campaigns\Campaign;
use App\Models\Campaigns\Commission;
use App\Models\Campaigns\Donations;
use App\Models\Campaigns\FundraiserAccount;
use App\Models\UnpaidDonationsReceipts;
use App\Models\User;
use App\Models\UserAccountNotification;
use Attribute;
use GrahamCampbell\ResultType\Success;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HubtelApiServices extends Controller
{
    public const CALLBACKURL = 'https://webhook.site/af650e89-62f9-4694-8fad-fc92279f098e' ?: 'https://bundanatechnologies.com/';
    public $totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $clientReference;

    public function newPaymentInvoice($ref = null, $totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl){
        $this->totalAmount = $totalAmount;
        $this->description = $description;
        $this->callbackUrl = $callbackUrl;
        $this->returnUrl = $returnUrl;
        $this->cancellationUrl = $cancellationUrl;
        $this->clientReference = $ref ? : Str::random(24);

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
            return  json_encode(['error' => $errorData, 'message'=> "Hubtel $errorData:: Something went wrong. Please try again later."]);
        }
    }
     public function generateInvoice($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl){
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
            redirect()->back()->with('error', "Hubtel $errorData:: Something went wrong. Please try again later.");
        }
    }

    public function handleFormSubmit(Request $request){
        $user = Auth::user();
        $campaignId = $request->id;

        // Find campaign
        $campaign = Campaign::where('campaign_id', $campaignId)->first();

        if (!$campaign) {
            return redirect()->back()->with('error', 'Campaign not found');
        }

        $donations = session()->has('selected_donations') ? session()->get('selected_donations') : [];

        // Store donation references in an array
        $donationReferences = [];

        foreach ($donations as $donationRef) {
            $data = Donations::where('donation_ref', $donationRef)
                ->where('status', 'unpaid')
                ->first();

            if ($data) {
                // Store only the donation reference in the array
                $donationReferences[] = $donationRef;
            }
        }

        $totalAmount = 0; // Initialize total amount outside the loop

        // Calculate total amount based on donation references
        foreach ($donationReferences as $donationRef) {
            $data = Donations::where('donation_ref', $donationRef)
                ->where('status', 'unpaid')
                ->first();

            if ($data) {
                $totalAmount += $data->amount;
            }
        }

        $totalAmount = 0.1;
        // Assuming $totalAmount is calculated correctly
        $description = "Payment of donation receipts of ₵$totalAmount for $campaign->title";
        $callbackUrl = self::CALLBACKURL;

        $returnUrl = route("manager.campaign-donation-receipts", [$campaignId]);
        $cancellationUrl = route("manager.campaign-donation-receipts", [$campaignId]);


        $res =  $this->generateInvoice($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl);

        if ($res) {
            $res = json_decode($res, true);
            $checkoutUrl = $res['checkoutUrl'];
            $checkoutId = $res['checkoutId'];
            $checkoutDirectUrl = $res['checkoutDirectUrl'];
            $clientReference = $res['clientReference'];
            $donationReferencesJson = json_encode($donationReferences, true);

            // Assuming $donationReferences is not empty
            UnpaidDonationsReceipts::updateOrInsert(
                ['user_id' => $user->user_id, 'reference' => $clientReference, 'campaign_id' => $campaignId],
                ['user_id' => $user->user_id, 'data' => $donationReferencesJson, 'amount' => $totalAmount, 'type' => 'receipt', 'phone' => $user->phone_number]
            );


            return redirect($checkoutUrl);
        } else {
            return redirect()->back()->with('error', 'Hubtel:: Something went wrong. Please try again later.');
        }
    }

    public function handleDirectPaymentLinkFormSubmit(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'momo_number' => ['required', 'numeric', 'digits:10'],
            'amount' => ['required', 'numeric'],
            'donor_name' => ['required'],
        ], [
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a valid amount.',
            'momo_number.required' => 'The phone number field is required.',
            'momo_number.digits' => 'The momo number must be exactly 10 digits.',
            'donor_name.required' => 'The Donor name field is required.',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $campaign_id = $request->id;
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }
        $user = Auth::user();

        if (!$user) {
            return back()->with('error', 'User not found');
        }

        $donarName = $request->input('donor_name');
        $amount = $request->input('amount');
        $momoNumber = $request->input('momo_number');

        $transRef = str::random(18);

        Donations::create(
            [
                'creator' => $campaign->manager_id,
                'campaign_id' => $campaign_id,
                'donation_ref' => $transRef,
                'momo_number' => $momoNumber,
                'amount' => $amount,
                'donor_name' => $donarName,
                'method' => 'web',
                'agent_id' => $user->user_id,
                'status' => 'unpaid'
            ]
        );

        UserAccountNotification::create([
            'user_id' => auth()->user()->user_id,
            'type' => 'campaign',
            'title' => 'Payment Link generated for ',
            'message' => "$momoNumber ($donarName) of GHS $amount. #$transRef, proceed to payment"
        ]);


        $description = "Payment Link generated for $donarName of {$campaign->name}";

        $returnUrl = "http://donation.local/campaigns/{$campaign->campaign_id}";
        $cancellationUrl = "http://donation.local/campaigns/{$campaign->campaign_id}";

        $res =  $this->generateInvoice($amount, $description, self::CALLBACKURL, $returnUrl, $cancellationUrl);

        if (!$res) {
            return redirect()->back()->with('error', 'Hubtel:: Something went wrong. Please try again later.');
        }
        $userPhone = $request->input('momo_number');
        $donationReferences[] = $transRef;
        $res = json_decode($res, true);
        $checkoutUrl = $res['checkoutUrl'];
        $checkoutId = $res['checkoutId'];
        $checkoutDirectUrl = $res['checkoutDirectUrl'];
        $clientReference = $res['clientReference'];
        $donationReferencesJson = json_encode($donationReferences, true);

        // Assuming $donationReferences is not empty
        UnpaidDonationsReceipts::updateOrInsert(
            ['user_id' => $user->user_id, 'reference' => $clientReference, 'campaign_id' => $campaign_id],
            ['user_id' => $user->user_id, 'data' => $donationReferencesJson, 'amount' => $amount, 'type' => 'direct', 'phone' => $userPhone]
        );

        $link = $checkoutUrl;
        $shortName = Helpers::getFirstName($donarName);

        $sms_content = "Hi $shortName, Click $link to complete your generous donation of GHS $amount";
        $sms_content .= "Ref: $transRef";
        $sms_content .= "Your support means a lot!";
        // $sms = new SMS($userPhone, $sms_content);

        // send success message
        return redirect($checkoutUrl);
    }


    public function generatePaymentLink(Request $request)
    {
        $method = $request->input('donor_name');

        // Validate the request
        $validator = Validator::make($request->all(), [
            'momo_number' => ['required', 'numeric', 'digits:10'],
            'amount' => ['required', 'numeric'],
            'donor_name' => ['required'],
            'channel' => ['required', Rule::in(['sms', 'whatsapp'])], // Add custom validation rule
        ], [
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a valid amount.',
            'momo_number.required' => 'The phone number field is required.',
            'momo_number.digits' => 'The momo number must be exactly 10 digits.',
            'donor_name.required' => 'The Donor name field is required.',
            'channel.required' => 'Please select a valid channel (SMS or WhatsApp).',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $campaign_id = $request->id;
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }
        $user = Auth::user();

        if (!$user) {
            return back()->with('error', 'User not found');
        }

        $donarName = $request->input('donor_name');
        $amount = $request->input('amount');
        $momoNumber = $request->input('momo_number');

        $transRef = str::random(18);

        Donations::create(
            [
                'creator' => $campaign->manager_id,
                'campaign_id' => $campaign_id,
                'donation_ref' => $transRef,
                'momo_number' => $momoNumber,
                'amount' => $amount,
                'donor_name' => $donarName,
                'method' => 'web',
                'agent_id' => $user->user_id,
                'status' => 'unpaid'
            ]
        );

        UserAccountNotification::create([
            'user_id' => auth()->user()->user_id,
            'type' => 'campaign',
            'title' => 'Payment Link generated for ',
            'message' => "$momoNumber ($donarName) of GHS $amount. #$transRef, proceed to payment"
        ]);


        $description = "Payment Link generated for $donarName of {$campaign->name}";

        $returnUrl = "http://donation.local/campaigns/{$campaign->campaign_id}";
        $cancellationUrl = "http://donation.local/campaigns/{$campaign->campaign_id}";

        $res =  $this->generateInvoice($amount, $description, self::CALLBACKURL, $returnUrl, $cancellationUrl);

        if (!$res) {
            return redirect()->back()->with('error', 'Hubtel:: Something went wrong. Please try again later.');
        }

        $res = json_decode($res, true);
        $checkoutUrl = $res['checkoutUrl'];
        $checkoutId = $res['checkoutId'];
        $checkoutDirectUrl = $res['checkoutDirectUrl'];
        $clientReference = $res['clientReference'];
        $donationReferencesJson = json_encode($transRef, true);
        // Assuming $donationReferences is not empty
        UnpaidDonationsReceipts::updateOrInsert(
            ['user_id' => $user->user_id, 'reference' => $clientReference, 'campaign_id' => $campaign_id],
            ['user_id' => $user->user_id, 'data' => $donationReferencesJson, 'amount' => $amount, 'phone'=> $momoNumber, 'type', 'direct']
        );

        $userPhone = $request->input('momo_number');
        $link = $checkoutUrl;
        $shortName = Helpers::getFirstName($donarName);

        switch ($method) {
            case 'sms':
                //send sms notices  
                $sms_content = "Hi $shortName, Click $link to complete your generous donation of GHS $amount";
                $sms_content .= "Ref: $transRef";
                $sms_content .= "Your support means a lot!";
                $sms = new SMS($userPhone, $sms_content);
                break;
            case 'whatsapp':
                $message = " Hi! $shortName, Click $link to complete your generous donation of GH₵$amount\n\n";
                $message .= "*Details:*\n";
                $message .= "```• Amount: {$amount}\n";
                $message .= "• Ref: $transRef \n";
                $message .= "Your support means a lot! 🙌```";
                $whatsapp = WhatsApp::to($userPhone)->message($message)->send();

                break;
        }
        // send success message
        return redirect()->back()->with('success', 'Payment link generated successfully ' . $link);
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
            $amount  = $unpaidDonations->amount;

            // Find the campaign with the given ID
            $campaign = Campaign::where('campaign_id', $campaign_id)->first();

            // Check if the campaign exists; if not, redirect back with an error message
            if (!$campaign) {
                return response()->json(['success' => false, 'message' => 'Campaign not found'], 404);
            }

            $commission =  Commission::where('user_id',  $campaign->manager_id)->where('campaign_id', $campaign_id)->first();
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
            $remainingAmount  = str_replace(',', '', $remainingAmount);

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
                // $sms->singleSendSMS(); 
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

    private function insertUSSDDonation(Request $request, $data){ 

        if(!array($request->campaignId) || !isset($request->campaignId) ){
            return json_encode(['status' => '400', 'message' => 'Please provide a valid campaign ID'], 400); 
        }

        $campaign_id = $request->campaignId;
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();
        if(!$campaign){
            return json_encode(['status' => '400', 'message' => 'Campaign not found'], 400);
        }

       
          // Validate the request
          $validator = Validator::make($request->all(), [
            'phone' => ['required', 'numeric', 'digits:10'],
            'amount' => ['required', 'numeric'],
            'donor_name' => ['required'],
            'agent_id' => ['required'],
            'campaignId' => ['required'],
            'reference' => ['required'],
        ], [
            'campaignId.required' => 'The campaign ID is required.',
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a valid amount.',
            'phone.required' => 'The Donor phone number is required.',
            'phone.digits' => 'The Donor momo number must be exactly 10 digits.',
            'donor_name.required' => 'The Donor name is required.',
            'agent_id.required' => 'The agent ID is required.',
            'reference.required' => 'The reference is required.',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            return json_encode(['status' => '400', 'message' => $errorMessage], 400); 
        }
        
        $amount = $request->amount;
        $momo_number = $request->phone;
        $donor_name = $request->donor_name;
        $transRef = $request->reference;
        $agent_id = $request->agent_id;
        
     $affected = Donations::create(
            [
                'creator' => $campaign->manager_id,
                'campaign_id' => $campaign_id,
                'donation_ref' => $transRef,
                'momo_number' => $momo_number,
                'amount' => $amount,
                'donor_name' => $donor_name,
                'method' => 'ussd',
                'agent_id' => $agent_id,
                'status' => 'paid'
            ]
        );
        if(!$affected){
            return json_encode(['status' => '400', 'message' => 'Donation wasnt inserted'], 400);
        }

        $user = User::where('user_id', $campaign->manager_id)->first();
       
        $commission =  Commission::where('user_id',  $campaign->manager_id)->where('campaign_id', $campaign_id)->first();
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
        $remainingAmount  = str_replace(',', '', $remainingAmount);

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
 
            $agentName = Helpers::getFirstName($user->name);
            $agentNumber = $momo_number;
            $sms_content = "Hi thanks for your donation of ₵{$amount} for campaign { $campaign->name}, God bless you\n";
            $sms = new SMS($agentNumber, $sms_content);
            // $sms->singleSendSMS();
        //inser user notice
        UserAccountNotification::create([
            'user_id' => $user->user_id,
            'type' => 'campaign',
            'title' => 'New USSD Donation',
            'message' => "$amount received from $donor_name of {$campaign->name} campaign"
        ]);
        return json_encode(['status' => '200', 'message' => 'Donation saved successfully'], 200);
       
    }

    public function HandleUSSDRequest(Request $request){
        $data = $request->data;
        $is_true = $this->insertUSSDDonation($request, $data);
        $res = json_decode($is_true, true); 
        // print_r($res);
        // exit;
        if(!$res || !isset($res['status']) || $res['status'] == '400'){ 
            return json_encode(['status' => '400', 'message' => $res['message']], 400);
        } 
        
        if($is_true){

            return json_encode(['status' => '200', 'message' => 'Donation saved successfully'], 200);
        }else{
            return json_encode(['status' => '400', 'message' => 'Donation not saved'], 400);
        }   
    }

}
