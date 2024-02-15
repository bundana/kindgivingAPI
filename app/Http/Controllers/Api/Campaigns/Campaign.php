<?php

namespace App\Http\Controllers\Api\Campaigns;

use App\Http\Controllers\Api\Auth\Bearer;
use App\Http\Controllers\Controller;
use App\Http\Traits\Auth\BearerTrait;
use App\Models\Campaigns\Campaign as CampaignsCampaign;
use App\Models\User;
use App\Models\Campaigns\{Campaign as CampaignModel};

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Campaign extends Controller
{
    use BearerTrait;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        // Verify the bearer token
        $decodedToken = $this->verifyToken($request);
        if ($decodedToken instanceof \Illuminate\Http\JsonResponse) {
            return $decodedToken; // Return error response
        }

        // Validation rules
        $rules = [
            'campaign_id' => ['required', 'string'],
        ];

        // Validation custom error messages
        $messages = [
            'campaign_id.string' => 'The Campaign ID must be a string.',
            'campaign_id.required' => 'The Campaign ID is required',

        ];

        // Run the validation
        $credentials = Validator::make($request->all(), $rules, $messages);

        if ($credentials->fails()) {
            $errorMessage = $credentials->errors()->first();
            return response()->json(['success' => false, 'message' => $errorMessage, 'errorCode' => 400], 400);
        }

        try {
            // Fetch campaign info based on campaign_id
            $campaign = CampaignModel::select('manager_id as creator', 'campaign_id as campaignID', 'name', 'category', 'description', 'target', 'image', 'slug', 'end_date', 'status', 'created_at')
                ->where('campaign_id', $request->campaign_id)
                ->first();


            // Check if campaign exists
            if (!$campaign) {
                return response()->json(['success' => false, 'message' => 'Campaign not found', 'errorCode' => 404], 404);
            }

            // Return campaign info
            return response()->json(['success' => true, 'message' => 'Campaign retrieved successfully', 'data' => $campaign]);
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
