<?php

namespace App\Http\Controllers\Api\Campaigns;

use App\Http\Controllers\Api\Auth\Bearer;
use App\Http\Controllers\Controller;
use App\Http\Traits\Auth\{BearerTrait, RequestMethod};
use App\Models\Campaigns\Campaign as CampaignsCampaign;
use App\Models\Campaigns\Donations;
use App\Models\User;
use App\Models\Campaigns\{Campaign as CampaignModel, CampaignAgent};
use App\Models\UnpaidDonationsReceipts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Donation extends Controller
{
    use BearerTrait, RequestMethod;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $requestMethod = $this->post($request);
        if ($requestMethod instanceof \Illuminate\Http\JsonResponse) {
            return $requestMethod;
        }
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        // Validation rules
        $rules = [
            'creator' => ['required', 'string'],
            'campaign_id' => ['required', 'string'],
            'donor_contact' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'donor_name' => ['nullable', 'string'],
            'email_address' => ['nullable', 'email'],
            'donation_method' => ['required', 'string'],
            'agent' => ['required', 'string'],
            'tip' => ['nullable', 'numeric'],
            'hide_donor' => ['nullable', 'string', 'in:yes,no'],
            'country' => ['nullable', 'string'],
        ];

        $messages = [
            'creator.required' => 'The creator field is required.',
            'campaign_id.required' => 'The campaign ID field is required.',
            'donor_contact.required' => 'The donor contact field is required.',
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
            'donor_name.string' => 'The donor name must be a string.',
            'email_address.email' => 'The email address must be a valid email.',
            'donation_method.required' => 'The donation method field is required.',
            'agent.required' => 'The agent field is required.',
            'tip.numeric' => 'The tip must be a number.',
            'hide_donor.in' => 'The hide donor field must be either "yes" or "no".',
            'country.string' => 'The country field must be a string.',
        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }
        $campaign = CampaignModel::where('campaign_id', $request->campaign_id)->first();
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found', 'errorCode' => 404], 404);
        }

        $agent = CampaignAgent::where('campaign_id', $request->campaign_id)->where('agent_id', $request->agent)->first();
        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Agent not found', 'errorCode' => 404], 404);
        }
        $donationReference = Str::random(6);
        try {
            // Create a new donation record
            Donations::create([
                'creator' => $request->creator,
                'campaign_id' => $request->campaign_id,
                'donation_ref' => $donationReference,
                'momo_number' => $request->donor_contact,
                'amount' => $request->amount,
                'donor_name' => $request->donor_name,
                'email' => $request->email_address,
                'method' => $request->donation_method,
                'agent_id' => $request->agent,
                'donor_public_name' => $request->donor_public_name,
                'platform_tip' => $request->tip,
                'hide_donor' => $request->hide_donor,
                'comment' => $request->comment,
                'country' => $request->country,
                'status' => 'unpaid',
            ]);

            // Fetch campaign info based on donation_id
            $donation = Donations::select('creator', 'campaign_id', 'donation_ref as reference', 'momo_number as donor_contact', 'amount', 'donor_name', 'email as email_address', 'method as donation_method', 'agent_id as agent', 'donor_public_name', 'platform_tip as tip', 'hide_donor', 'comment', 'country', 'status', 'created_at')
                ->where('donation_ref', $donationReference)
                ->first();


            // Check if donation exists
            if (!$donation) {
                return response()->json(['success' => false, 'message' => 'Error retriving newly created or  Donation not found', 'errorCode' => 404], 404);
            }

            // Return success response
            return response()->json(['success' => true, 'message' => 'Donation created successfully', 'data' => $donation], 201);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on KindGiving\'s end.' . $e, 'errorCode' => 500], 500);
        }
    }

    public function storeWithPayment(Request $request)
    {
        $requestMethod = $this->post($request);
        if ($requestMethod instanceof \Illuminate\Http\JsonResponse) {
            return $requestMethod;
        }
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        // Validation rules
        $rules = [
            'creator' => ['required', 'string'],
            'campaign_id' => ['required', 'string'],
            'donor_contact' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'networkProvider' => ['required', 'string', 'in:mtn,vodafone,airtel-tigo'],
            'donor_name' => ['nullable', 'string'],
            'email_address' => ['nullable', 'email'],
            'donation_method' => ['required', 'string'],
            'agent' => ['required', 'string'],
            'tip' => ['nullable', 'numeric'],
            'hide_donor' => ['nullable', 'string', 'in:yes,no'],
            'country' => ['nullable', 'string'],
        ];

        $messages = [
            'creator.required' => 'The creator field is required.',
            'campaign_id.required' => 'The campaign ID field is required.',
            'donor_contact.required' => 'The donor contact field is required.',
            'amount.required' => 'The amount field is required.',
            'amount.numeric' => 'The amount must be a number.',
            'networkProvider.required' => 'The network provider field is required.',
            'networkProvider.in' => 'The network provider must be either "mtn", "vodafone" or "airtel-tigo".',
            'donor_name.string' => 'The donor name must be a string.',
            'email_address.email' => 'The email address must be a valid email.',
            'donation_method.required' => 'The donation method field is required.',
            'agent.required' => 'The agent field is required.',
            'tip.numeric' => 'The tip must be a number.',
            'hide_donor.in' => 'The hide donor field must be either "yes" or "no".',
            'country.string' => 'The country field must be a string.',
        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }
        $campaign = CampaignModel::where('campaign_id', $request->campaign_id)->first();
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found', 'errorCode' => 404], 404);
        }

        $user = User::where('user_id', $request->agent)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User or agent not found', 'errorCode' => 404], 404);
        }
        $donationReference = Str::random(6);
        $paymentData = [
            "payee_name" => $request->donor_name,
            "msisdn" => $request->donor_contact,
            "email" => $request->email_address,
            "channel" => $request->networkProvider,
            "amount" => $request->amount,
            "callback_url" => "https://webhook.site/ef925b1d-7d73-4320-911b-e99c062d87c5",
            "description" => "Donation to {$campaign->name} of {$request->amount} via USSD",
            "reference" => $donationReference
        ];

        try {
            // Make a POST request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://api.kindgiving.org/v1/payments/hubtel/direct', $paymentData);
            // return  $response->json();
            // // Check if the response code is 0001
            // $responseData = $response->json();

            // if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0001') {
            //     // Transaction is pending, return a success message
            //     return response()->json(['success' => true, 'message' => 'Transaction pending. Expect callback request for final state.', 'data' => $request->all()], 200);
            // } 



            // Assuming $donationReferences is not empty
            UnpaidDonationsReceipts::updateOrInsert(
                ['user_id' => $user->user_id, 'reference' => $donationReference, 'campaign_id' => $campaign->campaign_id],
                ['user_id' => $user->user_id, 'data' => json_encode([$donationReference]), 'amount' => $request->amount, 'type' => 'direct', 'phone' => $request->donor_contact]
            );


            // Create a new donation record
            Donations::create([
                'creator' => $request->creator,
                'campaign_id' => $request->campaign_id,
                'donation_ref' => $donationReference,
                'momo_number' => $request->donor_contact,
                'amount' => $request->amount,
                'donor_name' => $request->donor_name,
                'email' => $request->email_address,
                'method' => $request->donation_method,
                'agent_id' => $request->agent,
                'donor_public_name' => $request->donor_public_name,
                'platform_tip' => $request->tip,
                'hide_donor' => $request->hide_donor,
                'comment' => $request->comment,
                'country' => $request->country,
                'status' => 'unpaid',
            ]);

            // Fetch campaign info based on donation_id
            $donation = Donations::select('creator', 'campaign_id', 'donation_ref as reference', 'momo_number as donor_contact', 'amount', 'donor_name', 'email as email_address', 'method as donation_method', 'agent_id as agent', 'donor_public_name', 'platform_tip as tip', 'hide_donor', 'comment', 'country', 'status', 'created_at')
                ->where('donation_ref', $donationReference)
                ->first();


            // Check if donation exists
            if (!$donation) {
                return response()->json(['success' => false, 'message' => 'Error retriving newly created or  Donation not found', 'errorCode' => 404], 404);
            }

            // Return success response
            return response()->json(['success' => true, 'message' => 'Donation created successfully', 'data' => $donation], 201);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on KindGiving\'s end.' . $e, 'errorCode' => 500], 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $this->get($request);
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        // Validation rules
        $rules = [
            'donation_id' => ['required', 'string'],
        ];

        // Validation custom error messages
        $messages = [
            'donation_id.string' => 'The Campaign ID must be a string.',
            'donation_id.required' => 'The Campaign ID is required',

        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }



        try {
            // Fetch campaign info based on donation_id
            $donation = Donations::select('creator', 'campaign_id', 'donation_ref as reference', 'momo_number as donor_contact', 'amount', 'donor_name', 'email as email_address', 'method as donation_method', 'agent_id as agent', 'donor_public_name', 'platform_tip as tip', 'hide_donor', 'comment', 'country', 'status', 'created_at')
                ->where('donation_ref', $request->donation_id)
                ->first();


            // Check if donation exists
            if (!$donation) {
                return response()->json(['success' => false, 'message' => 'Donation not found', 'errorCode' => 404], 404);
            }

            $campaign = CampaignModel::where('campaign_id', $donation->campaign_id)->first();
            if (!$campaign) {
                return response()->json(['success' => false, 'message' => 'Campaign not found', 'errorCode' => 404], 404);
            }

            $donationData = $donation->toArray();
            $donationData['campaign_name'] = $campaign->name;


            // Return donation info
            return response()->json(['success' => true, 'message' => 'Donation retrieved successfully', 'data' => $donationData]);
        } catch (\Exception $e) {
            // Handle errors
            return response()->json(['success' => false, 'message' => 'Request could not be fulfilled due to an error on KindGiving\'s end.', 'errorCode' => 500], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
