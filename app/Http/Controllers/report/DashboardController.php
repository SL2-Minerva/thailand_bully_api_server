<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\DailyMessage;
use App\Models\PercentageOfMessages;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;

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
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages(
            $campaign_id, $this->get_previous_date('2022-11-30', $period),
            $this->get_previous_date('2022-11-30', $period)
        );

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
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') .' - '. Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage_of_message->total_at_keyword
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        return array_values($data);

    }

    public function keyStats(Request $request) {
        $data = null;
        $campaign_id = $request->campaign_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data['total_messages'] = $this->totalMessages($campaign_id, $start_date, $end_date);
        $data['total_engagement'] = $this->totalEngagement($campaign_id, $start_date, $end_date);
        $data['total_accounts'] = $this->totalAccounts($campaign_id, $start_date, $end_date);

        return parent::handleRespond($data);
    }

    public function sentimentScore(Request $request) {
        return parent::handleRespond([
            "neutral_value" => 4.5,
            "sentiment_percentage" => 65,
            "pervious_sentiment" => 2.3,
        ]);
    }

    public function sentimentType(Request $request) {
        return parent::handleRespond([
            "positive_percentage" => 10,
            "neutral_percentage" => 65,
            "negative_percentage" => 40,
        ]);
    }

    public function keywordSummary(Request $request) {
        $data = null;
        $campaign_id = $request->campaign_id;
        $result = $this->keywordsTable($campaign_id, '2022-11-30', '2022-11-30');

        return $result;
    }

    public function keywordSummaryTop(Request $request) {
        $data = null;
        $campaign_id = $request->campaign_id;
        $data['main_keyword'] = $this->mainKeyWords($campaign_id, '2022-11-30', '2022-11-30');
        $data['top_sites'] = $this->topSites($campaign_id, '2022-11-30', '2022-11-30');
        $data['top_hastag'] = $this->topHashtag($campaign_id, '2022-11-30', '2022-11-30');

        return parent::handleRespond($data);
    }

    private function totalMessages($campaign_id, $start_date, $end_date) {

        //todo
        $start_date = Carbon::parse($start_date)->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->format('Y-m-d');

        $start_date_sub = Carbon::parse($start_date)->subMonths(1)->format('Y-m-d');
        $end_date_sub = Carbon::parse($end_date)->subMonths(1)->format('Y-m-d');
        
        $total_current = DB::table('percentage_of_messages')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date, $end_date])->
            get();
        
        $total_previous = DB::table('percentage_of_messages')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date_sub, $end_date_sub])->
            get();

        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);
        $interval = $start_date->diff($end_date);
        
        $total_message = 0;
        foreach ($total_current as $total_list) {
            $total_message = $total_message + $total_list->total_at_keyword;
        }

        $total_message_previous = 0;
        foreach ($total_previous as $total_list_previous) {
            $total_message_previous = $total_message_previous + $total_list_previous->total_at_keyword;
        }

        $comparison = $total_message - $total_message_previous;
        $percentage = ($total_message - $total_message_previous) / ($total_message_previous === 0 ? 1 : $total_message_previous);

        return [
            "total_message" => $total_message,
            "average_message" => $total_message / ($interval->days + 1),
            "comparison" => $comparison,
            "percentage" => $percentage,
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function totalEngagement($campaign_id, $start_date, $end_date) {

        //todo
        $start_date = Carbon::parse($start_date)->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->format('Y-m-d');

        $start_date_sub = Carbon::parse($start_date)->subMonths(1)->format('Y-m-d');
        $end_date_sub = Carbon::parse($end_date)->subMonths(1)->format('Y-m-d');

        $total_current = DB::table('total_engagement_of_campaign')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date, $end_date])->
            get();

        $total_previous = DB::table('total_engagement_of_campaign')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date_sub, $end_date_sub])->
            get();

        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);
        $interval = $start_date->diff($end_date);
        
        $total_engagement = 0;
        foreach ($total_current as $total_list) {
            $total_engagement = $total_engagement + $total_list->engagement;
        }

        $total_engagement_previous = 0;
        foreach ($total_previous as $total_list_previous) {
            $total_engagement_previous = $total_engagement_previous + $total_list_previous->engagement;
        }

        $comparison = $total_engagement - $total_engagement_previous;
        $percentage = ($total_engagement - $total_engagement_previous) / ($total_engagement_previous === 0 ? 1 : $total_engagement_previous);

        
        return [
            "total_engagement" => $total_engagement,
            "average_engagement" => $total_engagement / ($interval->days + 1),
            "comparison" => $comparison,
            "percentage" => $percentage,
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function totalAccounts($campaign_id, $start_date, $end_date) {

        //todo
        $start_date = Carbon::parse($start_date)->format('Y-m-d');
        $end_date = Carbon::parse($end_date)->format('Y-m-d');

        $start_date_sub = Carbon::parse($start_date)->subMonths(1)->format('Y-m-d');
        $end_date_sub = Carbon::parse($end_date)->subMonths(1)->format('Y-m-d');

        $total_current = DB::table('total_account_of_campaign')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date, $end_date])->
            get();

        $total_previous = DB::table('total_account_of_campaign')->
            where('campaign_id', $campaign_id)->
            whereBetween('date_m', [$start_date_sub, $end_date_sub])->
            get();

        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);
        $interval = $start_date->diff($end_date);
        
        $total_account = 0;
        foreach ($total_current as $total_list) {
            $total_account = $total_account + $total_list->total_account;
        }

        $total_account_previous = 0;
        foreach ($total_previous as $total_list_previous) {
            $total_account_previous = $total_account_previous + $total_list_previous->total_account;
        }

        $comparison = $total_account - $total_account_previous;
        $percentage = ($total_account - $total_account_previous) / ($total_account_previous === 0 ? 1 : $total_account_previous);

        return [
            "total_account" => $total_account,
            "average_account" => $total_account / ($interval->days + 1),
            "comparison" => $comparison,
            "percentage" => $percentage,
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function keywordsTable($campaign_id, $start_date, $end_date) {

        $dummy_data[] = [
            "id" =>  1,
            "keyword" => "Keyword 1",
            "message" => 1000,
            "engagement" => 1000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];
        $dummy_data[] = [
            "id" =>  2,
            "keyword" => "Keyword 2",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];
        $dummy_data[] = [
            "id" =>  3,
            "keyword" => "Keyword 3",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];

        $dummy_data[] = [
            "id" =>  4,
            "keyword" => "Keyword 4",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];

        $dummy_data[] = [
            "id" =>  5,
            "keyword" => "Keyword 5",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];

        $dummy_data[] = [
            "id" =>  6,
            "keyword" => "Keyword 6",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];

        $dummy_data[] = [
            "id" =>  7,
            "keyword" => "Keyword 7",
            "message" => 2000,
            "engagement" => 2000,
            "accounts" => 1000,
            "average_message" => 93.2,
            "average_engagement" =>  967.3,
        ];


        return $dummy_data;
    }

    public function mainKeyWords($campaign_id, $start_date, $end_date) {
        $dummy_data[] = [
            "id" =>  1,
            "keyword" => "Keyword 1",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  2,
            "keyword" => "Keyword 2",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  3,
            "keyword" => "Keyword 3",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  4,
            "keyword" => "Keyword 4",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  5,
            "keyword" => "Keyword 5",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  6,
            "keyword" => "Keyword 6",
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        return $dummy_data;
    }

    private function topSites($campaign_id, $start_date, $end_date) {
        $dummy_data[] = [
            "id" =>  1,
            "site_domain" =>  'www.google.com',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  2,
            "site_domain" =>  'www.google.com',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  3,
            "site_domain" =>  'www.google.com',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  4,
            "site_domain" =>  'www.google.com',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  5,
            "site_domain" =>  'www.google.com',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        return $dummy_data;
    }

    private function topHashtag($campaign_id, $start_date, $end_date) {
        $dummy_data[] = [
            "id" =>  1,
            "hashtag" =>  '#hashtag1',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  2,
            "hashtag" =>  '#hashtag2',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  3,
            "hashtag" =>  '#hashtag3',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  4,
            "hashtag" =>  '#hashtag4',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  5,
            "hashtag" =>  '#hashtag5',
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        return $dummy_data;
    }


//    private function commentSentiment($campaign_id, $start_date, $end_date) {
//
//    }
//
    public function shareOfVoice(Request $request) {

        $data[0]['keyword_id'] = 0;
        $data[0]['keyword_name'] = 'all';
        $data[0]['campaign_id'] = 1;
        $data[0]['campaign_name'] = 'campaign_name 1';
        $data[0]['organization_id'] = 1;
        $data[0]['organizations_name'] = 'organizations_name 1';
        $data[0]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[0]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[0]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[0]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[0]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];


        $data[1]['keyword_id'] = 1;
        $data[1]['keyword_name'] = 'keyword_name 1';
        $data[1]['campaign_id'] = 1;
        $data[1]['campaign_name'] = 'campaign_name 1';
        $data[1]['organization_id'] = 1;
        $data[1]['organizations_name'] = 'organizations_name 1';
        $data[1]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[1]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[1]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[1]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[1]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];


        $data[2]['keyword_id'] = 2;
        $data[2]['keyword_name'] = 'keyword_name 1';
        $data[2]['campaign_id'] = 2;
        $data[2]['campaign_name'] = 'campaign_name 1';
        $data[2]['organization_id'] = 2;
        $data[2]['organizations_name'] = 'organizations_name 1';
        $data[2]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[2]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[2]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[2]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[2]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];

        $data[3]['keyword_id'] = 3;
        $data[3]['keyword_name'] = 'keyword_name 1';
        $data[3]['campaign_id'] = 3;
        $data[3]['campaign_name'] = 'campaign_name 1';
        $data[3]['organization_id'] = 3;
        $data[3]['organizations_name'] = 'organizations_name 1';
        $data[3]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[3]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[3]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[3]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[3]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];

        $data[4]['keyword_id'] = 4;
        $data[4]['keyword_name'] = 'keyword_name 1';
        $data[4]['campaign_id'] = 4;
        $data[4]['campaign_name'] = 'campaign_name 1';
        $data[4]['organization_id'] = 4;
        $data[4]['organizations_name'] = 'organizations_name 1';
        $data[4]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[4]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[4]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[4]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[4]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];

        $data[5]['keyword_id'] = 5;
        $data[5]['keyword_name'] = 'keyword_name 1';
        $data[5]['campaign_id'] = 5;
        $data[5]['campaign_name'] = 'campaign_name 1';
        $data[5]['organization_id'] = 5;
        $data[5]['organizations_name'] = 'organizations_name 1';
        $data[5]['value'][] = ['channel' => 'facebook', 'percentage' => 30, 'highlight' => true];
        $data[5]['value'][] = ['channel' => 'twitter', 'percentage' => 30, 'highlight' => true];
        $data[5]['value'][] = ['channel' => 'youtube', 'percentage' => 10, 'highlight' => false];
        $data[5]['value'][] = ['channel' => 'intagrame', 'percentage' => 20, 'highlight' => false];
        $data[5]['value'][] = ['channel' => 'pantip', 'percentage' => 10, 'highlight' => false];

        return parent::handleRespond(array_values($data));
    }

    public function sentimentLevel(Request $request) {

        $data[0]['keyword_id'] = 0;
        $data[0]['keyword_name'] = 'all';
        $data[0]['campaign_id'] = 1;
        $data[0]['campaign_name'] = 'campaign_name 1';
        $data[0]['organization_id'] = 1;
        $data[0]['organizations_name'] = 'organizations_name 1';
        $data[0]['negative'] = 10;
        $data[0]['neutral'] = 30;
        $data[0]['positive'] = 60;


        $data[1]['keyword_id'] = 1;
        $data[1]['keyword_name'] = 'keyword_name 1';
        $data[1]['campaign_id'] = 1;
        $data[1]['campaign_name'] = 'campaign_name 1';
        $data[1]['organization_id'] = 1;
        $data[1]['organizations_name'] = 'organizations_name 1';
        $data[1]['negative'] = 10;
        $data[1]['neutral'] = 30;
        $data[1]['positive'] = 60;


        $data[2]['keyword_id'] = 2;
        $data[2]['keyword_name'] = 'keyword_name 1';
        $data[2]['campaign_id'] = 2;
        $data[2]['campaign_name'] = 'campaign_name 1';
        $data[2]['organization_id'] = 2;
        $data[2]['organizations_name'] = 'organizations_name 1';
        $data[2]['negative'] = 10;
        $data[2]['neutral'] = 30;
        $data[2]['positive'] = 60;

        $data[3]['keyword_id'] = 3;
        $data[3]['keyword_name'] = 'keyword_name 1';
        $data[3]['campaign_id'] = 3;
        $data[3]['campaign_name'] = 'campaign_name 1';
        $data[3]['organization_id'] = 3;
        $data[3]['organizations_name'] = 'organizations_name 1';
        $data[3]['negative'] = 10;
        $data[3]['neutral'] = 30;
        $data[3]['positive'] = 60;

        $data[4]['keyword_id'] = 4;
        $data[4]['keyword_name'] = 'keyword_name 1';
        $data[4]['campaign_id'] = 4;
        $data[4]['campaign_name'] = 'campaign_name 1';
        $data[4]['organization_id'] = 4;
        $data[4]['organizations_name'] = 'organizations_name 1';
        $data[4]['negative'] = 10;
        $data[4]['neutral'] = 30;
        $data[4]['positive'] = 60;

        $data[5]['keyword_id'] = 5;
        $data[5]['keyword_name'] = 'keyword_name 1';
        $data[5]['campaign_id'] = 5;
        $data[5]['campaign_name'] = 'campaign_name 1';
        $data[5]['organization_id'] = 5;
        $data[5]['organizations_name'] = 'organizations_name 1';
        $data[5]['negative'] = 10;
        $data[5]['neutral'] = 30;
        $data[5]['positive'] = 60;

        return parent::handleRespond(array_values($data));
    }

    public function sna (Request $request) {

        $data = [];

        $data['nodes'][] = ["id" => "test-tr", "label" => "node 1", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        $data['nodes'][] = ["id" => 2, "label" => "node 2", "title" => "Word 1 change color,shape & size", "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        $data['nodes'][] = ["id" => 3, "label" => "node 3", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        $data['nodes'][] = ["id" => 4, "label" => "node 4", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        $data['nodes'][] = ["id" => 5, "label" => "node 5", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        $data['edges'][] = [ "from" => "test-tr", "to" => 2, "length" => 0, "color" => "red"];
        $data['edges'][] = [ "from" => 2, "to" => 3, "length" => 200, "color" => "red"];
        $data['edges'][] = [ "from" => "3", "to" => 2, "length" => 300, "color" => "red"];
        $data['edges'][] = [ "from" => "test-tr", "to" => 2, "length" => 0, "color" => "red"];

        return parent::handleRespond($data);

    }

}
