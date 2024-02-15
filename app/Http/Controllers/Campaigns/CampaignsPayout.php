<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Utilities\Payment\Hubtel;
use App\Http\Controllers\Utilities\VerifyUserName;
use App\Mail\Campaigns\ManagePayoutRequest;
use App\Mail\Campaigns\NewPayoutRequest;
use App\Models\Campaigns\Campaign;
use App\Models\Campaigns\CampaignAgent;
use App\Models\Campaigns\Commission;
use App\Models\Campaigns\Donations;
use App\Models\Campaigns\FundraiserAccount;
use App\Models\Campaigns\PayoutBankList;
use App\Models\Campaigns\PayoutSettingsInfo;
use App\Models\Campaigns\PayoutSettlement;
use App\Models\User;
use App\Models\UserAccountNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CampaignsPayout extends Controller
{
    public function payoutIndex(Request $request)
    {
        //get user
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        //selec payout info
        $payout = PayoutSettingsInfo::where('user_id', $user->user_id)->first();
        $banks =  PayoutBankList::latest()->get();

        $keyword = $request->input('keyword');
        $status = $request->input('status') ?: ''; // Using the null coalescing operator

        $campaigns = Campaign::where('manager_id', auth()->user()->user_id)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->where(function ($query) use ($keyword) {
                $query->where('campaign_id', 'like', "%$keyword%")
                    ->orWhere('name', 'like', "%$keyword%")
                    ->orWhere('target', "$keyword")
                    ->orWhere('status', 'like', "%$keyword%");
            })
            ->latest()
            ->paginate(10);
        return view('manager.payouts.index', compact('user', 'payout', 'campaigns', 'keyword', 'banks', 'status'));
    }

    public function campaignPayout(Request $request)
    {
        // Retrieve the campaign ID from the request
        $campaign_id = $request->id;
        //get user
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }

        // Retrieve donations related to the campaign
        $donations = Donations::where('campaign_id', $campaign_id)->get() ?: [];

        $keyword = $request->input('keyword');
        $status = $request->input('status') ?: ''; // Using the null coalescing operator

        $settlements = PayoutSettlement::where('campaign_id', $campaign_id)->where('user_id', $campaign->manager_id)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->where(function ($query) use ($keyword) {
                $query->where('reference', 'like', "%$keyword%")
                    ->orWhere('settlement_id', 'like', "%$keyword%")
                    ->orWhere('amount', 'like', "%$keyword%")
                    ->orWhere('acc_name', "$keyword")
                    ->orWhere('acc_number', 'like', "%$keyword%")
                    ->orWhere('bank', "$keyword")
                    ->orWhere('status', "$keyword");
            })
            ->latest()
            ->paginate(10);


        $balance = FundraiserAccount::where('campaign_id', $campaign_id)->where('user_id', $campaign->manager_id)->first();
        // Retrieve campaign agents associated with the campaign
        $agents = CampaignAgent::where('campaign_id', $campaign_id)->get() ?? [];
        //selec payout info
        $payout = PayoutSettingsInfo::where('user_id', $campaign->manager_id)->first();
        $banks =  PayoutBankList::latest()->get();
        $commission =  Commission::where('user_id', $campaign->manager_id)->where('campaign_id', $campaign_id)->first();

        // Render the 'view' template and pass the campaign, donations, and agents data
        return view('manager.payouts.request', compact('balance', 'campaign', 'donations', 'agents', 'banks', 'payout', 'settlements', 'keyword', 'status', 'commission'));
    }

    public function newCampaignPayout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        // Retrieve the campaign ID from the request
        $campaign_id = $request->id;
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }
        // Validate the request
        $validator = Validator::make(
            $request->all(),
            [
                'amount' => ['required', 'numeric', 'min:1']
            ],
            [
                'amount.required' => 'Please enter amount.',
                'amount.numeric' => 'Please enter a valid amount.',

            ]
        );
        $amount = $request->input('amount');

        // Check if validation fails
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $payout = PayoutSettingsInfo::where('user_id', $user->user_id)->first();
        if (!$payout) {
            return back()->with('error', 'Please set your payout information first.')->withInput();
        }
        $balance = FundraiserAccount::where('campaign_id', $campaign_id)->where('user_id', $user->user_id)->first();
        $agents = CampaignAgent::with('user')
            ->where('campaign_id', $campaign_id)->get();

        $donations = Donations::where('campaign_id', $campaign_id)->get() ?: [];
        $commission =  Commission::where('user_id', $user->user_id)->where('campaign_id', $campaign_id)->first();
        $totalAmount = 0;
        $totalAgentsCommission = 0;
        $accBalance = 0;
        // Check if $balance is set before accessing it
        if (isset($balance)) {
            $accBalance = $balance->balance;
        }


        foreach ($donations as $donation) {
            // Add each donation amount to the totalAmount
            $totalAmount += $donation->amount;
        }

        foreach ($agents as $agent) {
            // Get the total donations made by each agent
            $agentTotalDonations = $donations->where('agent_id', $agent->agent_id)->sum('amount');
            // Assuming each agent is entitled to the specified percentage of their total donations
            $agentCommission = ($campaign->agent_commission / 100) * $agentTotalDonations;
            // Add the commission of each agent to the totalAgentsCommission
            $totalAgentsCommission += $agentCommission;
        }

        //platform usage comission is 15%
        $plaftformCommisionPercentage = '';

        if (isset($commission)) {
            $plaftformCommisionPercentage = $commission->commission;
        } else {
            $plaftformCommisionPercentage = 15;
        }


        $deduction  = max(0, number_format($accBalance - $amount, 2));
        $deduction  = str_replace(',', '', $deduction);


        if (!$balance || $amount > $accBalance || $balance->balance < $amount) {
            return back()->with('error', 'You have insufficient balance to withdraw.')->withInput();
        }

        // Validate passed
        $reference = Str::random(12);
        $settlement_id = Str::random(6);

        // Create a new payout settlement
        $settlement = PayoutSettlement::create([
            'settlement_id' => $settlement_id,
            'reference' => $reference,
            'campaign_id' => $campaign_id,
            'user_id' => $user->user_id,
            'amount' => $amount,
            'acc_name' => $payout->account_name,
            'acc_number' => $payout->account_number,
            'bank' => $payout->bank_name,
            'status' => 'pending'
        ]);

        if ($settlement) {
            // Deduct the requested amount from the fundraiser account
            $balance->balance = $deduction;

            $balance->save();

            // Send email notification
            $subject = "Your payout request has been received";
            Mail::to($user->email)->send(new NewPayoutRequest($subject, $settlement_id, $campaign_id));

            //send success
            return back()->with('success', 'Payout request sent successfully');
        } else {
            return back()->with('error', 'Payout request failed.')->withInput();
        }
    }
    public function payoutSettingIndex(Request $request)
    {
        //get user
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        //selec payout info
        $payout = PayoutSettingsInfo::where('user_id', $user->user_id)->first();
        $banks =  PayoutBankList::latest()->get();
        // dd($banks);
        return view('manager.payouts.settings')
            ->with('user', $user)
            ->with('payout', $payout)
            ->with('banks', $banks);
    }

    public function verifyAccountInfo(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
     

        // Validate the request
        if ($request->account_type == 'mobile_money') {
            // Validate the request
            $validator = Validator::make(
                $request->all(),
                [
                    'account_type' => ['required', 'string'],
                    'mobile_money_type' => ['required'],
                    'mobile_money_account_number' => ['required', 'digits:10']
                ],
                [
                    // 'manager_id.required' => 'The manager_id is required.', 
                ]
            );

            // Check if validation fails
            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $teleco = $request->input('mobile_money_type');
            $accountNumber = $request->input('mobile_money_account_number');
            $accountNameResult = new VerifyUserName($accountNumber, $teleco);
            $accountNameResponse = $accountNameResult->verifyAccountName();

            if (isset($accountNameResponse['success']) && $accountNameResponse['success'] === false) {
                return back()->with('error', 'Account name failed to verify or invalid account info provided.')->withInput();
            }
            $userName = $accountNameResponse['message'];
            $bank =  PayoutBankList::where('bank_code', $request->input('mobile_money_type'))->first();
            //insert in db
            PayoutSettingsInfo::updateOrInsert(
                ['user_id' => $user->user_id],
                [
                    'user_id' => $user->user_id,
                    'payout_method' => $teleco . ' Mobile Money',
                    'account_name' => $userName,
                    'account_number' => $accountNumber,
                    'bank_name' => $bank->bank_name
                ]
            );
            return back()->with('success', 'Payout information verified and updated successfully');
        } elseif ($request->account_type == 'bank') {
            

            // Validate the request
            $validator = Validator::make($request->all(), [
                'account_type' => ['required', 'string'],
                'bank_code' => ['required'],
                'bank_account_number' => ['required'],
            ], [
                // 'manager_id.required' => 'The manager_id is required.', 
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            $teleco = $request->input('bank_code');
            $accountNumber = $request->input('bank_account_number');
            $accountNameResult = new VerifyUserName($accountNumber, $teleco);
            $accountNameResponse = $accountNameResult->verifyAccountName();

            if (isset($accountNameResponse['success']) && $accountNameResponse['success'] === false) {
                return back()->with('error', 'Account name failed to verify or invalid account info provided.')->withInput();
            }
            $userName = $accountNameResponse['message'];
            $bank =  PayoutBankList::where('bank_code', $request->input('bank_code'))->first();
            //insert in db
            PayoutSettingsInfo::updateOrInsert(
                ['user_id' => $user->user_id],
                [
                    'user_id' => $user->user_id,
                    'payout_method' => $bank->bank_name,
                    'account_name' => $userName,
                    'account_number' => $accountNumber,
                    'bank_name' => $bank->bank_name
                ]
            );
            //return success
            return back()->with('success', 'Payout information verified and updated successfully');
        }
    }

    public function pay()
    {
        $totalAmount = '0.01';
        $description = 'test payment';
        $callbackUrl = 'https://webhook.site/dea851c6-03d6-4a69-aefe-0b2d65a99342';
        $returnUrl = 'http://donation.local/';
        $cancellationUrl = 'http://donation.local/';
        $clientReference = Str::random(18);
        // $clientReference = 'vNj9v4Yfco6SPgtIgT';
        // Instantiate the Hubtel class with your parameters
        $hubtel = new Hubtel($totalAmount, $description, $callbackUrl, $returnUrl, $cancellationUrl, $clientReference);

        // Call the initiate method to make the API request
        $response = $hubtel->initiate();

        // Decode the JSON response
        $responseArray = json_decode($response, true);

        // Check the status and handle the response accordingly
        if ($responseArray['status']) {
            // Success case 
            $responseData = $responseArray['data'];
            $clientReference = $responseData['clientReference'];
            $checkoutUrl = $responseData['checkoutUrl'];
            $checkoutId = $responseData['checkoutId'];
            $checkoutDirectUrl = $responseData['checkoutDirectUrl'];
        } else {
            // Error case
            $errorData = isset($responseArray['error']) ? $responseArray['error'] : 'Unknown error';
            dd($errorData[0]['errorMessage']);
            echo "Error: " . $errorData[0]['errorMessage'];
        }
    }

    public function adminCampaignPayoutIndex(Request $request)
    {

        $keyword = $request->input('keyword');
        $status = $request->input('status') ?: ''; // Using the null coalescing operator

        $campaigns = Campaign::when($status, function ($query) use ($status) {
            $query->where('status', $status);
        })
            ->where(function ($query) use ($keyword) {
                $query->where('campaign_id', 'like', "%$keyword%")
                    ->orWhere('name', 'like', "%$keyword%")
                    ->orWhere('target', "$keyword")
                    ->orWhere('manager_id', "$keyword")
                    ->orWhere('status', 'like', "%$keyword%");
            })
            ->latest()
            ->paginate(10);
        return view('admin.campaigns.payouts.index', compact('campaigns', 'keyword', 'status'));
    }

    public function adminCampaignPayout(Request $request)
    {          // Retrieve the campaign ID from the request
        $campaign_id = $request->id;
        //get user
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }

        // Retrieve donations related to the campaign
        $donations = Donations::where('campaign_id', $campaign_id)->get() ?: [];

        $keyword = $request->input('keyword');
        $status = $request->input('status') ?: ''; // Using the null coalescing operator

        $settlements = PayoutSettlement::where('campaign_id', $campaign_id)->where('user_id', $campaign->manager_id)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->where(function ($query) use ($keyword) {
                $query->where('reference', 'like', "%$keyword%")
                    ->orWhere('settlement_id', 'like', "%$keyword%")
                    ->orWhere('amount', 'like', "%$keyword%")
                    ->orWhere('acc_name', "$keyword")
                    ->orWhere('acc_number', 'like', "%$keyword%")
                    ->orWhere('bank', "$keyword")
                    ->orWhere('status', "$keyword");
            })
            ->latest()
            ->paginate(10);


        $balance = FundraiserAccount::where('campaign_id', $campaign_id)->where('user_id', $user->user_id)->first();
        // Retrieve campaign agents associated with the campaign
        $agents = CampaignAgent::where('campaign_id', $campaign_id)->get() ?? [];
        //selec payout info
        $payout = PayoutSettingsInfo::where('user_id', $user->user_id)->first();
        $banks =  PayoutBankList::latest()->get();
        $commission =  Commission::where('user_id', $user->user_id)->where('campaign_id', $campaign_id)->first();

        // Render the 'view' template and pass the campaign, donations, and agents data
        return view('admin.campaigns.payouts.view-request', compact('balance', 'campaign', 'donations', 'agents', 'banks', 'payout', 'settlements', 'keyword', 'status', 'commission'));
    }

    public function adminManageStatus(Request $request)
    {
        // Retrieve the campaign ID from the request
        $campaign_id = $request->id;

        // Find the campaign with the given ID
        $campaign = Campaign::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return back()->with('error', 'Campaign not found');
        }

        //get user
        $user = User::where('user_id', $campaign->manager_id)->first();

        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }

        $settlement_id = $request->settlement_id;
        $reference = $request->reference;

        $payout = PayoutSettlement::where('campaign_id', $campaign_id)->where('settlement_id', $settlement_id)->orWhere('reference', $reference)->first();
        $account = FundraiserAccount::where('campaign_id', $campaign_id)->where('user_id', $campaign->manager_id)->first();

        if ($request->has('action') && $request->action == 'approve') {
            //update the status to approve
            $payout->status = 'approved';
            $payout->save();
            $subject = 'Payout request approved successfully';
            Mail::to($user->email)->send(new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'approved'));


            UserAccountNotification::create([
                'user_id' => $user->user_id,
                'type' => 'account',
                'title' => 'Payout request approved',
                'message' => "Your payout request  of ₵{$payout->amount} and ID {$payout->settlement_id} has been approved."
            ]);
            // return (new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'approved'))->render();
            return back()->with('success', 'Payout request approved successfully');
        } elseif ($request->has('action') && $request->action == 'reject') {
            //update the status to rejected and reload the request amount back
            $payout->status = 'rejected';
            $payout->save();


            if ($account) {
                $account->balance = $account->balance + $payout->amount;
                $account->save();
            }
            $subject = 'Payout request rejected';

            Mail::to($user->email)->send(new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'rejected'));

            UserAccountNotification::create([
                'user_id' => $user->user_id,
                'type' => 'account',
                'title' => 'Payout request rejected',
                'message' => "Sorry, Your payout request  of ₵{$payout->amount} and ID {$payout->settlement_id} was rejected"
            ]);
            // return (new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'rejected'))->render();
            return back()->with('success', 'Payout request rejected successfully');
        } elseif ($request->has('action') && $request->get('action') == 'paid') {
            //update the status to paid
            $payout->status = 'paid';
            $payout->save();
            $subject = 'Payout request paid successfully';

            Mail::to($user->email)->send(new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'paid'));
            UserAccountNotification::create([
                'user_id' => $user->user_id,
                'type' => 'account',
                'title' => 'Payout request paid',
                'message' => "Your payout request  of ₵{$payout->amount} and ID {$payout->settlement_id} has been paid."
            ]);
            // return (new ManagePayoutRequest($subject, $payout, $campaign, $account, $user, 'paid'))->render();
            return back()->with('success', 'Payout request paid successfully');
        } else {
            // invalid request
            return back()->with('error', 'Invalid request');
        }
    }
}
