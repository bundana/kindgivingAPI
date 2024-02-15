<?php

namespace App\Http\Controllers\Campaigns;

use App\Exports\AgentReportExport;
use App\Http\Controllers\Controller;
use App\Models\Campaigns\Campaign as CampaignModel;
use App\Models\Campaigns\CampaignAgent;
use App\Models\Campaigns\Donations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    // public function generateReport(Request $request)
    // {
    //     $campaign = CampaignModel::where('campaign_id', 'A5vlcPRdG1iV')->first();
    //     $campaign_id = $campaign->campaign_id;
    //     $donations = Donations::where('campaign_id', $campaign_id)->get() ?: [];
    //     $agents = CampaignAgent::where('campaign_id', $campaign_id)->get() ?? [];

    //     $agentReports = [];

    //     // Loop through agents
    //     foreach ($agents as $agent) {
    //         $agentDonations = $donations->where('agent_id', $agent->agent_id);
    //         $weeklyDonations = $agentDonations->groupBy(function ($donation) {
    //             return $donation->created_at->format('W Y-m-d'); // Group by week with date
    //         });

    //         // Loop through weekly donations
    //         foreach ($weeklyDonations as $week => $weekDonations) {
    //             // Extract week and date from the grouped key
    //             [$week, $date] = explode(' ', $week);

    //             $agentTotalDonations = $weekDonations->sum('amount');
    //             $agentCommission = ($campaign->agent_commission / 100) * $agentTotalDonations;

    //             $agentReports[] = [
    //                 'week' => "WEEK $week $date",
    //                 'agent_name' => $agent->name,
    //                 'total_donations' => $agentTotalDonations,
    //                 'commission' => $agentCommission,
    //             ];
    //         }
    //     }

    //     $export = new AgentReportExport($agentReports);
    //     // return Excel::download($export, 'agent_report.xlsx'); 
    //     $pdf = PDF::loadView('layouts.pdfs.campaigns.reports', ['agentReports' => $agentReports]);
    //     return $pdf->download('agent_report.pdf');
    //     if ($request->ajax()) {
    //         return Excel::download($export, 'agent_report.xlsx');
    //     }
    // }

    public function weeklyData(Request $request)
    {
        // Retrieve the campaign ID from the request
        $campaign_id = $request->id;
        // Find the campaign with the given ID
        $campaign = CampaignModel::where('campaign_id', $campaign_id)->first();

        // Check if the campaign exists; if not, redirect back with an error message
        if (!$campaign) {
            return response()->json(['success' => false, 'message' => 'Campaign not found']);
        }

        $donations = Donations::where('campaign_id', $campaign_id)->get() ?: [];
        $agents = CampaignAgent::where('campaign_id', $campaign_id)->with('user')->get() ?? [];

        $agentReports = [];
        $grandTotalDonations = 0;
        $grandTotalCommission = 0;
        $date = '';
        // Loop through agents
        foreach ($agents as $agent) {
            $agentDonations = $donations->where('agent_id', $agent->agent_id);
            $weeklyDonations = $agentDonations->groupBy(function ($donation) {
                return $donation->created_at->format('W Y-m-d'); // Group by week with date
            });

            // Initialize agent report array
            $agentReport = [];
            $agentTotalDonations = 0;
            $agentTotalCommission = 0;

            // Loop through weekly donations
            foreach ($weeklyDonations as $key => $weekDonations) {
                // Extract week and date from the grouped key
                [$week, $date] = explode(' ', $key);

                $agentTotalDonations += $weekDonations->sum('amount');
                $agentCommission = ($campaign->agent_commission / 100) * $agentTotalDonations;

                // Extract month from the date
                $month = date('F, Y', strtotime($date));

                // Add the report to the agentReport array
                $agentReport[] = [
                    'week' => $week,
                    'month' => $month,
                    'total_donations' => $agentTotalDonations,
                    'commission' => $agentCommission,
                    'date_range' => $this->getWeekDateRange($date),

                ];
            }

            // Add the agent total to the grand total
            $grandTotalDonations += $agentTotalDonations;
            $grandTotalCommission += $agentTotalCommission;

            // Add the agent report to the agentReports array
            $agentReports[$agent->user->name . " ID:" . $agent->agent_id. " Phone: ".$agent->user->phone_number] = $agentReport;
        }
        $hehe = Str::random(5);
        $signature = Str::random(3) . "*" . Str::random(3) . "_" . Str::random(10) . "@" . Str::random(4);
        //filename
        $filename = Str::snake($campaign->name . "Weekly Data _" .now().Str::random(10)); 
        $date_range = $this->getWeekDateRange($date);

        $pdf = PDF::loadView('layouts.pdfs.campaigns.reports', compact('agentReports', 'grandTotalDonations', 'grandTotalCommission', 'campaign', 'signature', )); 
        

        if ($request->isMethod('post')) {
            return $pdf->download($filename . '.pdf');
        }else{
            return $pdf->inline($filename . '.pdf');

        }
    }

    private function getWeekDateRange($date)
{
    $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

    $startDay = date('jS', strtotime($startOfWeek));
    $endDay = date('jS', strtotime($endOfWeek));
    $month = date('M', strtotime($startOfWeek));

    return "{$startDay} - {$endDay} {$month}";
}
}
