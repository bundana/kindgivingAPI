<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Campaigns\PayoutSettingsInfo;

class CampaignsPayout extends Controller
{
    public function payoutSettingIndex(Request $request)
    {
        //get user
        $user = $request->user();
        if (!$user) {
            //send back to previous page
            return redirect()->back()->with('error', 'You are not allowed to access this page');
        }
        //selec payout info
        $payout = PayoutSettingsInfo::where('user_id', $user->id)->first();

        //bank list
        $banks = new \App\Http\Controllers\Utilities\Payment\BanksList();
        $banks = json_decode($banks->all(), true);
        $banks = $banks['data'];
       

        return view('manager.payouts.settings')->with('user', $user)->with('payout', $payout);
    }
}
