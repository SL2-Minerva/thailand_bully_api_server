<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\DailyMessage;
use App\Models\Message;
use App\Models\PercentageOfMessages;
use App\Models\SNA;
use App\Models\SNAChildNode;
use App\Models\SNARootNode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;

class DashboardController extends Controller
{
    public function overAll(Request $request)
    {
        $campaign_id = $request->campaign_id;
        $source = $request->source;
        $period = $request->period;
        $start_date = null;
        $end_date = null;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        if ($request->start_date) {
            $start_date = $this->date_carbon($request->start_date);
        }

        if ($request->end_date) {
            $end_date = $this->date_carbon($request->end_date);
        }

        $data = null;
        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);
        $data['daily_message'] = $this->dailyMessage($campaign_id, $start_date, $end_date, $source);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($campaign_id, $start_date, $end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($campaign_id, $start_date_previous, $end_date_previous);

        return parent::handleRespond($data);
    }

    public function wordClouds(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id ?? "";
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $select = $request->select ?? null;

        $data['word_clouds'] = $this->wordCloudsMessage($campaign_id, $start_date, $end_date, $select);

        return parent::handleRespond($data);
    }

    public function wordCloudsPlateform(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id ?? "";
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $select = $request->select ?? null;

        $data['word_clouds_platform'] = $this->wordCloudsMessage($campaign_id, $start_date, $end_date, $select);

        return parent::handleRespond($data);
    }

    public function wordCloudsPosition(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id ?? "";
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $select = $request->select ?? null;

        $data['word_clouds_position'] = $this->wordCloudsMessage($campaign_id, $start_date, $end_date, $select);

        return parent::handleRespond($data);
    }

    private function wordCloudsMessage($campaign_id, $start_date, $end_date, $select)
    {
        $dummy_data = $this->wordCloudsData();
        switch ($select) {
            case "top10":
                $data = array_slice($dummy_data, 0, 10);
                break;
            case "top20":
                $data = array_slice($dummy_data, 0, 20);
                break;
            case "top50":
                $data = array_slice($dummy_data, 0, 50);
                break;
            case "top100":
                $data = array_slice($dummy_data, 0, 100);
                break;
            default:
                $data = $dummy_data;
        }

        return $data;
    }


    private function date_carbon($date)
    {
        return Carbon::parse($date)->format('Y-m-d');
    }


    private function get_previous_date($date, $period)
    {
        switch ($period) {
            case "daily":
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
                break;
            case "yesterday":
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
                break;
            case "last7Days":
                $date = Carbon::parse($date)->subDays(7)->format('Y-m-d');
                break;
            case "last30Days":
                $date = Carbon::parse($date)->subDays(30)->format('Y-m-d');
                break;
            case "thisMonth":
                $date = Carbon::parse($date)->subMonths(1)->format('Y-m-d');
                break;
            case "lastMonth":
                $date = Carbon::parse($date)->subMonths(1)->format('Y-m-d');
                break;
            case "customrange":
                $date = Carbon::parse($date)->format('Y-m-d');
                break;
            default:
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
        }

        return $date;
    }

    private function diff_date($start_date, $end_date)
    {
        $start_date = Carbon::createFromFormat('Y-m-d H:s:i', $start_date . ' 00:00:00');
        $end_date = Carbon::createFromFormat('Y-m-d H:s:i', $end_date . ' 23:59:59');
        $length = $start_date->diffInDays($end_date);
        return $length != 0 ? $length : 1;
    }

    private function point_two_digits($number)
    {
        return $number !== null ? number_format($number, 2) : null;
    }


    private function dailyMessage($campaign_id, $start_date, $end_date, $source)
    {
        $data = null;
        $daily_messages = DailyMessage::where('campaign_id', $campaign_id)->where('source_id', $source);
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

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    private function percentageOfMessages($campaign_id, $start_date, $end_date)
    {
        $data = null;
        $percentage_of_messages = PercentageOfMessages::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name');

        foreach ($percentage_of_messages->get() as $percentage_of_message) {
            $keyword_id = $percentage_of_message->keyword_id;
            $data[$keyword_id]['keyword_id'] = $percentage_of_message->keyword_id;
            $data[$keyword_id]['keyword_name'] = $percentage_of_message->keyword_name;
            $data[$keyword_id]['campaign_id'] = $percentage_of_message->campaign_id;
            $data[$keyword_id]['campaign_name'] = $percentage_of_message->campaign_name;
            $data[$keyword_id]['organization_id'] = $percentage_of_message->organization_id;
            $data[$keyword_id]['organizations_name'] = $percentage_of_message->organizations_name;

            $message_keyword = $this->messagesTable($campaign_id, $start_date, $end_date, $keyword_id);
            $message_total = PercentageOfMessages::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->sum('total_at_keyword');

            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $this->point_two_digits(($message_keyword / $message_total) * 100),
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    public function keyStats(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $source = $request->source ?? null;
        $period = $request->period ?? null;
        $start_date_period = $request->start_date_period ?? null;
        $end_date_period = $request->end_date_period ?? null;

        $data['total_messages'] = $this->totalMessages($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period);
        $data['total_engagement'] = $this->totalEngagement($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period);
        $data['total_accounts'] = $this->totalAccounts($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period);

        return parent::handleRespond($data);
    }

    public function sentimentScore(Request $request)
    {
        return parent::handleRespond([
            "neutral_value" => 4.5,
            "sentiment_percentage" => 65,
            "pervious_sentiment" => 2.3,
        ]);
    }

    public function sentimentType(Request $request)
    {
        return parent::handleRespond([
            "positive_percentage" => 10,
            "neutral_percentage" => 65,
            "negative_percentage" => 40,
        ]);
    }

    public function keywordSummary(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $data = $this->keywordsTable($campaign_id, $start_date, $end_date);

        return parent::handleRespond($data);
    }

    public function keywordSummaryTop(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date ?? "";
        $end_date = $request->end_date ?? "";
        $data['main_keyword'] = $this->mainKeyWords($campaign_id, $start_date, $end_date);
        $data['top_sites'] = $this->topSites($campaign_id, '2022-11-30', '2022-11-30');
        $data['top_hastag'] = $this->topHashtag($campaign_id, '2022-11-30', '2022-11-30');

        return parent::handleRespond($data);
    }

    private function totalMessages($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period)
    {

        //todo
        $start_date = $this->date_carbon($start_date);
        $end_date = $this->date_carbon($end_date);

        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);

        if ($period === "customrange") {
            $start_date_previous = $this->get_previous_date($start_date_period, $period);
            $end_date_previous = $this->get_previous_date($end_date_period, $period);
        }

        $total_current = DB::table('percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->sum('total_at_keyword');

        $total_previous = DB::table('percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->sum('total_at_keyword');

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_message" => $this->point_two_digits($total_current),
            "average_message" => $this->point_two_digits($total_current / $diff_date),
            "comparison" => $this->point_two_digits($comparison),
            "percentage" => $this->point_two_digits($percentage),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function totalEngagement($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period)
    {

        //todo
        $start_date = $this->date_carbon($start_date);
        $end_date = $this->date_carbon($end_date);

        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);
        if ($period === "customrange") {
            $start_date_previous = $this->get_previous_date($start_date_period, $period);
            $end_date_previous = $this->get_previous_date($end_date_period, $period);
        }

        $total_engagement = DB::table('total_engagement_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->sum('engagement');

        $total_engagement_previous = DB::table('total_engagement_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->sum('engagement');

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_engagement - $total_engagement_previous;
        $percentage = (($total_engagement - $total_engagement_previous) / ($total_engagement_previous === 0 ? 1 : $total_engagement_previous)) * 100;

        if($percentage == -100) {
            $percentage = 0;
        }


        return [
            "total_engagement" => $this->point_two_digits($total_engagement),
            "average_engagement" => $this->point_two_digits($total_engagement / $diff_date),
            "comparison" => $this->point_two_digits($comparison),
            "percentage" => $this->point_two_digits($percentage),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function totalAccounts($campaign_id, $start_date, $end_date, $source, $period, $start_date_period, $end_date_period)
    {

        //todo
        $start_date = $this->date_carbon($start_date);
        $end_date = $this->date_carbon($end_date);

        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);

        if ($period === "customrange") {
            $start_date_previous = $this->get_previous_date($start_date_period, $period);
            $end_date_previous = $this->get_previous_date($end_date_period, $period);
        }

        $total_current = DB::table('total_account_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->sum('total_account');

        $total_previous = DB::table('total_account_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->sum('total_account');

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_account" => $this->point_two_digits($total_current),
            "average_account" => $this->point_two_digits($total_current / $diff_date),
            "comparison" => $this->point_two_digits($comparison),
            "percentage" => $this->point_two_digits($percentage),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function messagesTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_message = DB::table('percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_at_keyword');

        return $total_message;
    }

    private function engagementTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_engagement = DB::table('total_engagement_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('engagement');

        return $total_engagement;
    }

    private function accountTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_account = DB::table('total_account_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_account');

        return $total_account;
    }

    private function shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $total_message = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->where('source_id', $source_id)
            ->sum('total_at_date');

        return $total_message;
    }

    private function shareOfVoiceByNumber($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_account = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_at_date');

        return $total_account;
    }

    private function keywordsTable($campaign_id, $start_date, $end_date)
    {
        $data = null;
        $total_keywords = DB::table('percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name')
            ->get();

        $diff_date = $this->diff_date($start_date, $end_date);
        $id = 1;

        foreach ($total_keywords as $item) {

            $message = $this->messagesTable($campaign_id, $start_date, $end_date, $item->keyword_id);
            $engagement = $this->engagementTable($campaign_id, $start_date, $end_date, $item->keyword_id);
            $accounts = $this->accountTable($campaign_id, $start_date, $end_date, $item->keyword_id);
            $id + 1;

            $data_push = [
                "id" => $id++,
                "keyword" => $item->keyword_name,
                "keyword_id" => $item->keyword_id,
                "message" => $this->point_two_digits($message),
                "engagement" => $this->point_two_digits($engagement),
                "accounts" => $this->point_two_digits($accounts),
                "average_message" => $this->point_two_digits($message / $diff_date),
                "average_engagement" =>  $this->point_two_digits($engagement / $diff_date),
            ];

            $data[] = $data_push;
        }

        return $data;
    }

    public function mainKeyWords($campaign_id, $start_date, $end_date)
    {

        $data = null;
        $total_keywords = DB::table('percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name')
            ->get();
        $id = 1;

        foreach ($total_keywords as $item) {

            $message = $this->messagesTable($campaign_id, $start_date, $end_date, $item->keyword_id);
            $total_message = PercentageOfMessages::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->sum('total_at_keyword');
            
            $percentage = ($message / $total_message) * 100;
            $id + 1;

            $data_push = [
                "id" => $id++,
                "keyword" => $item->keyword_name,
                "keyword_id" => $item->keyword_id,
                "no_of_message" => $this->point_two_digits($message),
                "percentage" => $this->point_two_digits($percentage),
                "type" => ($message >= 0 ? "plus" : "minus"),
            ];

            $data[] = $data_push;
        }

        return $data;
    }

    private function topSites($campaign_id, $start_date, $end_date)
    {
        $dummy_data[] = [
            "id" =>  1,
            "site_domain" =>  'www.google.com',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  2,
            "site_domain" =>  'www.google.com',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  3,
            "site_domain" =>  'www.google.com',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  4,
            "site_domain" =>  'www.google.com',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        $dummy_data[] = [
            "id" =>  5,
            "site_domain" =>  'www.google.com',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];
        return $dummy_data;
    }

    private function topHashtag($campaign_id, $start_date, $end_date)
    {
        $dummy_data[] = [
            "id" =>  1,
            "hashtag" =>  '#hashtag1',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  2,
            "hashtag" =>  '#hashtag2',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  3,
            "hashtag" =>  '#hashtag3',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  4,
            "hashtag" =>  '#hashtag4',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" =>  5,
            "hashtag" =>  '#hashtag5',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        return $dummy_data;
    }

    public function shareOfVoiceNumber(Request $request) 
    {
        $data = null;
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $total_keywords = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name')
            ->get();

        $total_message = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->sum('total_at_date');

        foreach ($total_keywords as $item) {
            $message = $this->shareOfVoiceByNumber($campaign_id, $start_date, $end_date, $item->keyword_id);
            $push_data = [
                'keyword_name' => $item->keyword_name,
                'number_of_massage' => $message,
            ];

            $data[] = $push_data;
        }

        return parent::handleRespond($data);

    }

    public function shareOfVoice(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $total_keywords = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name', 'source_id')
            ->get();

        foreach ($total_keywords as $item) {
            $keyword_id = $item->keyword_id;
            $data[$keyword_id]['keyword_id'] = $item->keyword_id;
            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            $data[$keyword_id]['organization_id'] = $item->organization_id;
            $data[$keyword_id]['organizations_name'] = $item->organizations_name;

                $message = $this->shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
                $total_message = DailyMessage::where('campaign_id', $campaign_id)
                    ->where('keyword_id', $item->keyword_id)
                    ->where('organization_id', $item->organization_id)
                    ->where('campaign_name', $item->campaign_name)
                    ->whereBetween('date_m', [$start_date, $end_date])
                    ->sum('total_at_date');

                $percentage = ($message / $total_message) * 100;
    
                $push_data = [
                    'channel' => $item->source_name,
                    'percentage' => $this->point_two_digits($percentage),
                    'number_of_message' => $message,
                    // 'highlight' => 
                ];
    
                $data[$keyword_id]['value'][] = $push_data;
                
        }

        if ($data) {
           $data = array_values($data); 
        }

        return parent::handleRespond($data);
    }

    public function sentimentLevel(Request $request)
    {

        $data[0]['keyword_id'] = 1;
        $data[0]['keyword_name'] = 'keyword_name 1';
        $data[0]['campaign_id'] = 1;
        $data[0]['campaign_name'] = 'campaign_name 1';
        $data[0]['organization_id'] = 1;
        $data[0]['organizations_name'] = 'organizations_name 1';
        $data[0]['negative'] = 10;
        $data[0]['neutral'] = 30;
        $data[0]['positive'] = 60;


        $data[1]['keyword_id'] = 2;
        $data[1]['keyword_name'] = 'keyword_name 1';
        $data[1]['campaign_id'] = 2;
        $data[1]['campaign_name'] = 'campaign_name 1';
        $data[1]['organization_id'] = 2;
        $data[1]['organizations_name'] = 'organizations_name 1';
        $data[1]['negative'] = 10;
        $data[1]['neutral'] = 30;
        $data[1]['positive'] = 60;

        $data[2]['keyword_id'] = 3;
        $data[2]['keyword_name'] = 'keyword_name 1';
        $data[2]['campaign_id'] = 3;
        $data[2]['campaign_name'] = 'campaign_name 1';
        $data[2]['organization_id'] = 3;
        $data[2]['organizations_name'] = 'organizations_name 1';
        $data[2]['negative'] = 10;
        $data[2]['neutral'] = 30;
        $data[2]['positive'] = 60;

        $data[3]['keyword_id'] = 4;
        $data[3]['keyword_name'] = 'keyword_name 1';
        $data[3]['campaign_id'] = 4;
        $data[3]['campaign_name'] = 'campaign_name 1';
        $data[3]['organization_id'] = 4;
        $data[3]['organizations_name'] = 'organizations_name 1';
        $data[3]['negative'] = 10;
        $data[3]['neutral'] = 30;
        $data[3]['positive'] = 60;

        $data[4]['keyword_id'] = 5;
        $data[4]['keyword_name'] = 'keyword_name 1';
        $data[4]['campaign_id'] = 5;
        $data[4]['campaign_name'] = 'campaign_name 1';
        $data[4]['organization_id'] = 5;
        $data[4]['organizations_name'] = 'organizations_name 1';
        $data[4]['negative'] = 10;
        $data[4]['neutral'] = 30;
        $data[4]['positive'] = 60;

        return parent::handleRespond(array_values($data));
    }

    public function sna(Request $request)
    {
        $campaign_id = $request->campaign_id;

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $data = [];
        $roots = $this->getRootNode($campaign_id);
        $childs = $this->getChildNode($campaign_id);
        $data['nodes'] = array_merge($roots, $childs);

        foreach ($childs as $child) {
            foreach ($roots as $root) {
                if ($child['parent_id'] == $root['id']) {
                    $data['edges'][] = [
                        "from" => $child['id'],
                        "to" => $root['id'],
                        "width" => (int)$child['length'] >= 30 ? (int)$child['length'] / 10 : (int)$child['length'],
                        "length" => (int)$child['length'] ? (int)$child['length'] * 10 : 150,
                        "color" => $child['color']
                    ];
                }
            }
        }

        return parent::handleRespond($data);

        //        Message::where(SNA::CAMPAIGN_ID, $campaign_id)
        //            ->chunk(100, function ($messages) use (&$data) {
        //                foreach ($messages as $message) {
        //                    $data[] = [
        //                        'id' => $message->id,
        //                        'name' => $message->name,
        //                        'value' => $message->value,
        //                        'type' => $message->type,
        //                        'created_at' => $message->created_at,
        //                        'updated_at' => $message->updated_at,
        //                    ];
        //                }
        //            });
        //        $snas = SNA::where(SNA::CAMPAIGN_ID,$campaign_id)->where(SNA::REFERENCE_MESSAGE_ID, '')->groupby(SNA::MESSAGE_ID)->get();
        //
        //        foreach ($snas as $sna) {
        //            $data['nodes'][] = [
        //                "id" => $sna->message_id,
        //                "label" => $sna->author,
        //                "title" => $sna->author,
        //                "color" => $sna->classification_color,
        //                "shape" => "dot",
        //                "size" => $sna->engagement
        //            ];
        //
        //            $data = $this->getReferSna($campaign_id, $sna->message_id, $data);
        //        }


        //        $data['nodes'][] = ["id" => "test-tr", "label" => "node 1", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        //        $data['nodes'][] = ["id" => 2, "label" => "node 2", "title" => "Word 1 change color,shape & size", "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        //        $data['nodes'][] = ["id" => 3, "label" => "node 3", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        //        $data['nodes'][] = ["id" => 4, "label" => "node 4", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        //        $data['nodes'][] = ["id" => 5, "label" => "node 5", "title" => "Word 1 change color,shape & size" , "color" => "#f7f0c8", "shape" => "dot", "size" => 40];
        //
        //        $data['edges'][] = [ "from" => "test-tr", "to" => 2, "length" => 0, "color" => "red"];
        //        $data['edges'][] = [ "from" => 2, "to" => 3, "length" => 200, "color" => "red"];
        //        $data['edges'][] = [ "from" => "3", "to" => 2, "length" => 300, "color" => "red"];
        //        $data['edges'][] = [ "from" => "test-tr", "to" => 2, "length" => 0, "color" => "red"];
        return parent::handleRespond($data);
    }

    private function getRootNode($campaign_id)
    {
        $snas = SNARootNode::where(SNA::CAMPAIGN_ID, $campaign_id)
            ->groupBy(SNA::MESSAGE_ID)
            //            ->limit(10)
            ->get();

        foreach ($snas as $sna) {
            $data[] = [
                "id" => $sna->message_id,
                "label" => $sna->author,
                "title" => $sna->author,
                "color" => $sna->classification_color,
                "shape" => "dot",
                "size" => (int)$sna->engagement >= 30 ? (int)$sna->engagement / 10 : ((int)$sna->engagement == 0 ? 10 : (int)$sna->engagement)
            ];
        }

        return $data;
    }

    private function getChildNode($campaign_id)
    {
        $snas = SNAChildNode::where(SNA::CAMPAIGN_ID, $campaign_id)
            ->groupBy(SNA::MESSAGE_ID)
            ->limit(100)
            ->get();

        foreach ($snas as $sna) {
            $data[] = [
                "id" => (int)$sna->message_id,
                "label" => $sna->author,
                "title" => $sna->author,
                "parent_id" => (int)$sna->reference_message_id,
                "color" => $sna->classification_color,
                "shape" => "dot",
                "size" => (int)$sna->engagement <= 0 ? 10 : (int)$sna->engagement / 10,
                "length" => (int)$sna->engagement <= 0 ? 10 : (int)$sna->engagemen + 10
            ];
        }

        return $data;
    }

    private function getReferSna($campaign_id, $message_id, $data_node = [])
    {
        $data = $data_node;
        $snas = SNA::where(SNA::CAMPAIGN_ID, $campaign_id)->where(SNA::REFERENCE_MESSAGE_ID, $message_id)->get();


        foreach ($snas as $sna) {
            $data['nodes'][] = [
                "id" => $sna->message_id,
                "label" => $sna->author,
                "title" => $sna->author,
                "color" => $sna->classification_color,
                "shape" => "dot",
                "size" => $sna->engagement,
            ];


            //            $data_node = $this->getReferSna($campaign_id, $sna->message_id, $data_node);
        }

        return $data;
    }

    private function wordCloudsData()
    {

        $dummy_data = [
            [
                "text" => "told",
                "value" => 64
            ],
            [
                "text" => "mistake",
                "value" => 11
            ],
            [
                "text" => "thought",
                "value" => 16
            ],
            [
                "text" => "bad",
                "value" => 17
            ],
            [
                "text" => "correct",
                "value" => 10
            ],
            [
                "text" => "day",
                "value" => 54
            ],
            [
                "text" => "prescription",
                "value" => 12
            ],
            [
                "text" => "time",
                "value" => 77
            ],
            [
                "text" => "thing",
                "value" => 45
            ],
            [
                "text" => "left",
                "value" => 19
            ],
            [
                "text" => "pay",
                "value" => 13
            ],
            [
                "text" => "people",
                "value" => 32
            ],
            [
                "text" => "month",
                "value" => 22
            ],
            [
                "text" => "again",
                "value" => 35
            ],
            [
                "text" => "review",
                "value" => 24
            ],
            [
                "text" => "call",
                "value" => 38
            ],
            [
                "text" => "doctor",
                "value" => 70
            ],
            [
                "text" => "asked",
                "value" => 26
            ],
            [
                "text" => "finally",
                "value" => 14
            ],
            [
                "text" => "insurance",
                "value" => 29
            ],
            [
                "text" => "week",
                "value" => 41
            ],
            [
                "text" => "called",
                "value" => 49
            ],
            [
                "text" => "problem",
                "value" => 20
            ],
            [
                "text" => "going",
                "value" => 59
            ],
            [
                "text" => "help",
                "value" => 49
            ],
            [
                "text" => "felt",
                "value" => 45
            ],
            [
                "text" => "discomfort",
                "value" => 11
            ],
            [
                "text" => "lower",
                "value" => 22
            ],
            [
                "text" => "severe",
                "value" => 12
            ],
            [
                "text" => "free",
                "value" => 38
            ],
            [
                "text" => "better",
                "value" => 54
            ],
            [
                "text" => "muscle",
                "value" => 14
            ],
            [
                "text" => "neck",
                "value" => 41
            ],
            [
                "text" => "root",
                "value" => 24
            ],
            [
                "text" => "adjustment",
                "value" => 16
            ],
            [
                "text" => "therapy",
                "value" => 29
            ],
            [
                "text" => "injury",
                "value" => 20
            ],
            [
                "text" => "excruciating",
                "value" => 10
            ],
            [
                "text" => "chronic",
                "value" => 13
            ],
            [
                "text" => "chiropractor",
                "value" => 35
            ],
            [
                "text" => "treatment",
                "value" => 59
            ],
            [
                "text" => "tooth",
                "value" => 32
            ],
            [
                "text" => "chiropractic",
                "value" => 17
            ],
            [
                "text" => "dr",
                "value" => 77
            ],
            [
                "text" => "relief",
                "value" => 19
            ],
            [
                "text" => "shoulder",
                "value" => 26
            ],
            [
                "text" => "nurse",
                "value" => 17
            ],
            [
                "text" => "room",
                "value" => 22
            ],
            [
                "text" => "hour",
                "value" => 35
            ],
            [
                "text" => "wait",
                "value" => 38
            ],
            [
                "text" => "hospital",
                "value" => 11
            ],
            [
                "text" => "eye",
                "value" => 13
            ],
            [
                "text" => "test",
                "value" => 10
            ],
            [
                "text" => "appointment",
                "value" => 49
            ],
            [
                "text" => "medical",
                "value" => 19
            ],
            [
                "text" => "question",
                "value" => 20
            ],
            [
                "text" => "office",
                "value" => 64
            ],
            [
                "text" => "care",
                "value" => 54
            ],
            [
                "text" => "minute",
                "value" => 29
            ],
            [
                "text" => "waiting",
                "value" => 16
            ],
            [
                "text" => "patient",
                "value" => 59
            ],
            [
                "text" => "health",
                "value" => 49
            ],
            [
                "text" => "alternative",
                "value" => 24
            ],
            [
                "text" => "holistic",
                "value" => 19
            ],
            [
                "text" => "traditional",
                "value" => 20
            ],
            [
                "text" => "symptom",
                "value" => 29
            ],
            [
                "text" => "internal",
                "value" => 17
            ],
            [
                "text" => "prescribed",
                "value" => 26
            ],
            [
                "text" => "acupuncturist",
                "value" => 16
            ],
            [
                "text" => "pain",
                "value" => 64
            ],
            [
                "text" => "integrative",
                "value" => 10
            ],
            [
                "text" => "herb",
                "value" => 13
            ],
            [
                "text" => "sport",
                "value" => 22
            ],
            [
                "text" => "physician",
                "value" => 41
            ],
            [
                "text" => "herbal",
                "value" => 11
            ],
            [
                "text" => "eastern",
                "value" => 12
            ],
            [
                "text" => "chinese",
                "value" => 32
            ],
            [
                "text" => "acupuncture",
                "value" => 45
            ],
            [
                "text" => "prescribe",
                "value" => 14
            ],
            [
                "text" => "medication",
                "value" => 38
            ],
            [
                "text" => "western",
                "value" => 35
            ],
            [
                "text" => "sure",
                "value" => 38
            ],
            [
                "text" => "work",
                "value" => 64
            ],
            [
                "text" => "smile",
                "value" => 17
            ],
            [
                "text" => "teeth",
                "value" => 26
            ],
            [
                "text" => "pair",
                "value" => 11
            ],
            [
                "text" => "wanted",
                "value" => 20
            ],
            [
                "text" => "frame",
                "value" => 13
            ],
            [
                "text" => "lasik",
                "value" => 10
            ],
            [
                "text" => "amazing",
                "value" => 41
            ],
            [
                "text" => "fit",
                "value" => 14
            ],
            [
                "text" => "happy",
                "value" => 22
            ],
            [
                "text" => "feel",
                "value" => 49
            ],
            [
                "text" => "glasse",
                "value" => 19
            ],
            [
                "text" => "vision",
                "value" => 12
            ],
            [
                "text" => "pressure",
                "value" => 16
            ],
            [
                "text" => "find",
                "value" => 29
            ],
            [
                "text" => "experience",
                "value" => 59
            ],
            [
                "text" => "year",
                "value" => 70
            ],
            [
                "text" => "massage",
                "value" => 35
            ],
            [
                "text" => "best",
                "value" => 54
            ],
            [
                "text" => "mouth",
                "value" => 20
            ],
            [
                "text" => "staff",
                "value" => 64
            ],
            [
                "text" => "gum",
                "value" => 10
            ],
            [
                "text" => "chair",
                "value" => 12
            ],
            [
                "text" => "ray",
                "value" => 22
            ],
            [
                "text" => "dentistry",
                "value" => 11
            ],
            [
                "text" => "canal",
                "value" => 13
            ],
            [
                "text" => "procedure",
                "value" => 100
            ],
            [
                "text" => "filling",
                "value" => 26
            ],
            [
                "text" => "gentle",
                "value" => 19
            ],
            [
                "text" => "cavity",
                "value" => 17
            ],
            [
                "text" => "crown",
                "value" => 14
            ],
            [
                "text" => "cleaning",
                "value" => 38
            ],
            [
                "text" => "hygienist",
                "value" => 24
            ],
            [
                "text" => "dental",
                "value" => 59
            ],
            [
                "text" => "charge",
                "value" => 24
            ],
            [
                "text" => "cost",
                "value" => 29
            ],
            [
                "text" => "charged",
                "value" => 13
            ],
            [
                "text" => "spent",
                "value" => 17
            ],
            [
                "text" => "paying",
                "value" => 14
            ],
            [
                "text" => "pocket",
                "value" => 12
            ],
            [
                "text" => "dollar",
                "value" => 11
            ],
            [
                "text" => "business",
                "value" => 32
            ],
            [
                "text" => "refund",
                "value" => 10
            ]
        ];

        return $dummy_data;
    }
}
