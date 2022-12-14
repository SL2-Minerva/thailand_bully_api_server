<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\DailyMessage;
use App\Models\PercentageOfMessages;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overAll(Request $request)
    {
        $campaign_id = $request->campaign_id;

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $data = null;
        $period = $this->get_period($request->period);
        $data['daily_message'] = $this->dailyMessage($campaign_id, '2022-11-30', '2022-11-30');
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($campaign_id, '2022-11-30', '2022-11-30');
//        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages(
//            $campaign_id, $this->get_previous_date('2022-11-30', $period),
//            $this->get_previous_date('2022-11-30', $period)
//        );

        return parent::handleRespond($data);
    }

    private function  get_period ($preiod = null)
    {
        if ($preiod == null) {
            return 1;
        }
        return 1;
    }


    private function get_previous_date($date, $period)
    {
        $date = Carbon::parse($date);
        $date->subDays($period);
        return $date->format('Y-m-d');
    }


    private function dailyMessage($campaign_id, $start_date, $end_date) {
        $data = null;
        $daily_messages = DailyMessage::where('campaign_id', $campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$start_date, $end_date]);

        foreach ($daily_messages->get() as $daily_message) {

            $keyword_id = $daily_message->keyword_id;
            $data[$keyword_id]['keyword_id'] = $daily_message->keyword_id;
            $data[$keyword_id]['keyword_name'] = $daily_message->keyword_name;
            $data[$keyword_id]['campaign_id'] = $daily_message->campaign_id;
            $data[$keyword_id]['campaign_name'] = $daily_message->campaign_name;
            $data[$keyword_id]['organization_id'] = $daily_message->organization_id;
            $data[$keyword_id]['organizations_name'] = $daily_message->organizations_name;

            $nestData = [
                'source_id' => $daily_message->source_id,
                'source_name' => $daily_message->source_name,
                'date_m' => $daily_message->date_m,
                'total_at_date' => $daily_message->total_at_date
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        return array_values($data);
    }

    private function percentageOfMessages($campaign_id, $start_date, $end_date) {
        $data = null;
        $percentage_of_messages = PercentageOfMessages::where('campaign_id', $campaign_id);
        $percentage_of_messages->whereBetween('date_m', [$start_date, $end_date]);

        foreach ($percentage_of_messages->get() as $percentage_of_message) {
            $keyword_id = $percentage_of_message->keyword_id;

            $data[$keyword_id]['keyword_id'] = $percentage_of_message->keyword_id;
            $data[$keyword_id]['keyword_name'] = $percentage_of_message->keyword_name;
            $data[$keyword_id]['campaign_id'] = $percentage_of_message->campaign_id;
            $data[$keyword_id]['campaign_name'] = $percentage_of_message->campaign_name;
            $data[$keyword_id]['organization_id'] = $percentage_of_message->organization_id;
            $data[$keyword_id]['organizations_name'] = $percentage_of_message->organizations_name;

            $nestData = [
                'date' => $start_date .' - '. $end_date,
                'percentage' => $percentage_of_message->total_at_keyword
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        return array_values($data);

    }

    private function totalMessages($campaign_id, $start_date, $end_date) {

    }

    private function totalEngagement($campaign_id, $start_date, $end_date) {

    }

    private function totalAccounts($campaign_id, $start_date, $end_date) {

    }

    private function KeyWords($campaign_id, $start_date, $end_date) {

    }

    private function mainKeyWords($campaign_id, $start_date, $end_date) {

    }

    private function SubKeyword($campaign_id, $start_date, $end_date) {

    }

    private function topSites($campaign_id, $start_date, $end_date) {

    }

    private function topHashtag() {

    }

    private function sentimentScore() {

    }

    private function commentSentiment() {

    }

    private function shareOfVoice() {

    }








}
