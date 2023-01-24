<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\DailyMessage;
use App\Models\Message;
use App\Models\MessageResultSemetic;
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

    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;
    private $source_id;
    private $keyword_id;

    public function __construct(Request $request)
    {
        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source_id;
        
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',' , $fillter_keywords);
        }

    }

    public function overAll(Request $request)
    {
       
        $data = null;
        
        $data['daily_message'] = $this->dailyMessage($this->start_date, $this->end_date);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->campaign_id, $this->start_date, $this->end_date, $this->keyword_id ?? null, $this->source_id ?? null);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->campaign_id, $this->start_date_previous, $this->end_date_previous, $this->keyword_id ?? null, $this->source_id ?? null);

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

    public function dailyMessageLevelThree(Request $request)
    {

        $campaign_id = $request->campaign_id ?? null;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;
        $keyword_id = $request->keyword_id ?? null;
        $source = $request->source ?? null;
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;

        $data = null;
        $total = Message::where('keyword_id', $keyword_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date);

        $message = Message::where('keyword_id', $keyword_id)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->offset($start)->limit($limit);

        if ($source !== 'all') {
            $message = $message->where('source_id', $source);
            $total = $total->where('source_id', $source);
        }

        foreach ($message->get() as $item) {
            $data_push = [
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->created_at)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->created_at)->format('h:i'),
                "day" => Carbon::parse($item->created_at)->diffInDays(Carbon::now()),
                "device" => $item->device,
                "channel" => $item->source_id,
                "bully_level" => "level 3",
                "bully_type" => $item->message_type

            ];

            $data['message'][] = $data_push;
        }
        $data['total'] = $total->get()->count();

        return parent::handleRespond($data);
    }


    private function factoryDataLevelFour($start_date, $end_date, $condition = null)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('message_type', 'Post')
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if (isset($condition['classification_type_id']) && $condition['classification_type_id']) {
            $raw = $raw->whereIn('classification_type_id', $condition['classification_type_id']);
        }

        if (isset($condition['source_id']) && $condition['source_id']) {
            $raw = $raw->whereIn('source_id', $condition['source_id']);
        }

        if (isset($condition['keyword_id']) && $condition['keyword_id']) {
            $raw = $raw->whereIn('keyword_id', $condition['keyword_id']);
        }

        $items = $raw->get();

    }

    public function dailyMessageLevelFour(Request $request)
    {

        $campaign_id = $request->campaign_id;
        $keyword_id = $request->keyword_id ?? null;
        $message_id = $request->message_id ?? null;

        $report_number = $request->report_number ?? null;
        $data = null;

//
        if (!$this->campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $condition_root = [
            'root' => true,
            "message_id" => $message_id,
            "keyword_id" => $keyword_id,
            "report_number" => $report_number,
            "classification_type_id" => [1]
        ];

        $parent = $this->factoryDataLevelFour($this->start_date, $this->end_date, $condition_root);

        $data = [];
        $roots = $this->getRootNode($campaign_id, $keyword_id, $message_id, $start_date, $end_date);
        $childs = $this->getChildNode($campaign_id, $keyword_id, $message_id, $start_date, $end_date);
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


    private function dailyMessage($start_date, $end_date)
    {
        
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $data = null;

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->keyword_name])) {
                
                if (isset($data[$item->keyword_name]['value'][$date_format])) {
                    $data[$item->keyword_name]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->keyword_name]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }
               
            } else {
                $data[$item->keyword_name] = [
                    "keyword_id" =>  $item->keyword_id,
                    "keyword_name" => $item->keyword_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "source_id" => $item->source_id,
                    "source_name" => $item->source_name,
                ];
                $data[$item->keyword_name]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'date_m' => $item->date_m,
                    'total_at_date' => 1
                ];
            }
        }

     
        foreach($data as $key => $item) {
            if ($item) {
               $data[$key]['value'] = array_values($item['value']);     
            }
        }

        if ($data) {
            $data = array_values($data);
        }

    
        return $data;
    }

    private function percentageOfMessages($campaign_id, $start_date, $end_date, $keyword_id, $source_id = null)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $data = null;

        $message_keyword = [];
        $message_total = 0;

        foreach ($items as $item) {

            if (isset($message_keyword[$item->keyword_id])) {
                $message_keyword[$item->keyword_id] += 1;
            } else {
                $message_keyword[$item->keyword_id] = 1;
            }

            $message_total += 1;
        }

        $data = null;

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
            ];

        }

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;
            $data[$keyword_id]['keyword_id'] = $keyword_id;
            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
        }   


        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    public function keyStats(Request $request)
    {
        $data = null;
        $source = $request->source ?? null;

        $data['total_messages'] = $this->totalMessages($this->campaign_id, $this->start_date, $this->end_date, $source, $this->start_date_previous, $this->end_date_previous);
        $data['total_engagement'] = $this->totalEngagement($this->campaign_id, $this->start_date, $this->end_date, $source, $this->start_date_previous, $this->end_date_previous);
        $data['total_accounts'] = $this->totalAccounts($this->campaign_id, $this->start_date, $this->end_date, $source, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    public function sentimentScore(Request $request)
    {

        $current = $this->findSentiment('message_result_semetic', $this->start_date, $this->end_date, $this->campaign_id, $this->source_id, $this->source_id);
        $pervious = $this->findSentiment('message_result_semetic', $this->start_date_previous, $this->end_date_previous, $this->campaign_id, $this->source_id);

        return parent::handleRespond([
            "neutral_value" => (float)self::point_two_digits($current['results']),
            "sentiment_percentage" => $current['sentiment_percentage'],
            "pervious_sentiment" => (float)self::point_two_digits($pervious['results']),
            "text" => $current['text']
        ]);
    }

    private function findSentiment($table, $start_date, $end_date, $campaign_id, $source_id = null)
    {
        $positive = 0;
        $negative = 0;
        $neutral = 0;
        $sentiment_score = 0;

        $results = DB::table($table)->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_name', ["Positive", 'Negative', 'Neutral'])
            ->get();

        if ($results->count() > 0) {

            foreach ($results as $result) {
                if ($result->classification_name == "Positive") {
                    $positive += 1;
                } else if ($result->classification_name == "Negative") {
                    $negative += 1;
                } else if ($result->classification_name == "Neutral") {
                    $neutral += 1;
                }
            }

            $sentiment_score = (((1 * $positive ?? 1) + (-1 * $negative ?? 1)) / ($positive + $negative + $neutral)) * 5;
        }


        $data['neutral'] = $neutral;
        $data['positive'] = $positive;
        $data['negative'] = $negative;
        $data['results'] = $sentiment_score;
        $percentage = 20;

        if ($sentiment_score === 0) {
            $percentage = 0;
        }

        if ($sentiment_score >= 2 && $sentiment_score <= 3) {
            $percentage = 40;
        } else if ($sentiment_score >= 3 && $sentiment_score <= 3.0) {
            $percentage = 60;
        } else if ($sentiment_score >= 4) {
            $percentage = 80;
        }

        $data['sentiment_percentage'] = ($sentiment_score * 1) + $percentage;
        $data['text'] = $this->closest_sentiment_score($data['sentiment_percentage'] ?? 0);

        return $data;


    }

    private function closest_sentiment_score($target)
    {

        if ($target <= 40) {
            return 'Negative';
        }

        if (($target >= 41 && $target <= 70)) {
            return 'Neutral';
        }

        if ($target > 70) {
            return 'Positive';
        }
    }

    public function sentimentType(Request $request)
    {

        $current = $this->findSentiment('message_result_semetic', $this->start_date, $this->end_date, $this->campaign_id, $this->source_id);
        $message_total = $current['positive'] + $current['negative'] + $current['neutral'];

        return parent::handleRespond([
            "positive_percentage" => (float)self::point_two_digits(($current['positive'] / $message_total) * 100),
            "neutral_percentage" => (float)self::point_two_digits(($current['neutral'] / $message_total) * 100),
            "negative_percentage" => (float)self::point_two_digits(($current['negative'] / $message_total) * 100),
        ]);
    }

    public function keywordSummary(Request $request)
    {
        $data = null;
        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('keyword_id')
            ->get();

        $diff_date = $this->diff_date($this->start_date, $this->end_date);
        $id = 1;
        
        foreach ($total_keywords as $item) {
            $message = $this->messagesTable($this->campaign_id, $this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
            $engagement = $this->engagementTable($this->campaign_id, $this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
            $accounts = $this->accountTable($this->campaign_id, $this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
            $id + 1;

            $data_push = [
                "id" => $id++,
                "keyword" => $item->keyword_name,
                "keyword_id" => $item->keyword_id,
                "message" => $this->point_two_digits($message),
                "engagement" => $this->point_two_digits($engagement),
                "accounts" => $this->point_two_digits($accounts),
                "average_message" => $this->point_two_digits($message / $diff_date),
                "average_engagement" => $this->point_two_digits($engagement / $diff_date),
            ];

            $data[] = $data_push;
        }
        

        return parent::handleRespond($data);
    }

    public function keywordSummaryTop(Request $request)
    {
        $data = null;
        
        $data['main_keyword'] = $this->mainKeyWords($this->campaign_id, $this->start_date, $this->end_date);
        $data['top_sites'] = $this->topSites($this->campaign_id, $this->start_date, $this->end_date);
        $data['top_hastag'] = $this->topHashtag($this->campaign_id, $this->start_date, $this->end_date);

        return parent::handleRespond($data);
    }


    // private function totalData($table, $colum, $campaign_id, $start_date, $end_date, $period, $start_date_period, $end_date_period)
    // {

    //     $start_date = $this->date_carbon($start_date);
    //     $end_date = $this->date_carbon($end_date);

    //     $start_date_previous = $this->get_previous_date($start_date, $period);
    //     $end_date_previous = $this->get_previous_date($end_date, $period);

    //     if ($period === "customrange") {
    //         $start_date_previous = $this->get_previous_date($start_date_period, $period);
    //         $end_date_previous = $this->get_previous_date($end_date_period, $period);
    //     }

    //     $total_current = DB::table('percentage_of_messages')
    //         ->where('campaign_id', $campaign_id)
    //         ->whereBetween('date_m', [$start_date, $end_date])
    //         ->sum('total_at_keyword');

    //     $total_previous = DB::table('percentage_of_messages')
    //         ->where('campaign_id', $campaign_id)
    //         ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
    //         ->sum('total_at_keyword');

    //     $diff_date = $this->diff_date($start_date, $end_date);

    //     $comparison = $total_current - $total_previous;
    //     $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

    //     if ($percentage == -100) {
    //         $percentage = 0;
    //     }

    //     return [
    //         "total_message" => $this->point_two_digits($total_current),
    //         "average_message" => $this->point_two_digits($total_current / $diff_date),
    //         "comparison" => $this->point_two_digits($comparison),
    //         "percentage" => $this->point_two_digits($percentage),
    //         "type" => ($comparison >= 0 ? "plus" : "minus")
    //     ];
    // }

    private function totalMessages($campaign_id, $start_date, $end_date, $source, $start_date_previous, $end_date_previous)
    {

        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])->get()->count();

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1])->get()->count();

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
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

    private function totalEngagement($campaign_id, $start_date, $end_date, $source, $start_date_previous, $end_date_previous)
    {
        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1])
            ->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_engagement" => $this->point_two_digits($total_current),
            "average_engagement" => $this->point_two_digits($total_current / $diff_date),
            "comparison" => $this->point_two_digits($comparison),
            "percentage" => $this->point_two_digits($percentage),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];
    }

    private function totalAccounts($campaign_id, $start_date, $end_date, $source, $start_date_previous, $end_date_previous)
    {
        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('author')
            ->get()
            ->count();

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1])
            ->groupBy('author')
            ->get()
            ->count();

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
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

    // private function keywordsTable($campaign_id, $start_date, $end_date)
    // {
    //     $data = null;
    //     $total_keywords = DB::table('percentage_of_messages')
    //         ->where('campaign_id', $campaign_id)
    //         ->whereBetween('date_m', [$start_date, $end_date])
    //         ->groupBy('keyword_name')
    //         ->get();

    //     $diff_date = $this->diff_date($start_date, $end_date);
    //     $id = 1;

    //     foreach ($total_keywords as $item) {

    //         $message = $this->messagesTable($campaign_id, $start_date, $end_date, $item->keyword_id);
    //         $engagement = $this->engagementTable($campaign_id, $start_date, $end_date, $item->keyword_id);
    //         $accounts = $this->accountTable($campaign_id, $start_date, $end_date, $item->keyword_id);
    //         $id + 1;

    //         $data_push = [
    //             "id" => $id++,
    //             "keyword" => $item->keyword_name,
    //             "keyword_id" => $item->keyword_id,
    //             "message" => $this->point_two_digits($message),
    //             "engagement" => $this->point_two_digits($engagement),
    //             "accounts" => $this->point_two_digits($accounts),
    //             "average_message" => $this->point_two_digits($message / $diff_date),
    //             "average_engagement" => $this->point_two_digits($engagement / $diff_date),
    //         ];

    //         $data[] = $data_push;
    //     }

    //     return $data;
    // }

    public function mainKeyWords($campaign_id, $start_date, $end_date)
    {

        $data = null;
        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('keyword_id')
            ->get();
        $id = 1;

        foreach ($total_keywords as $item) {
            $message = $this->messagesTable($campaign_id, $start_date, $end_date, $item->keyword_id);
            $total_message = DB::table('message_result_full_data')
                ->where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->whereIn('classification_type_id', [1])
                ->count();

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
        $data = null;

        $message_keyword = [];
        $message_total = 0;

        $total_keywords = DB::table('message_top_sites')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name')
            ->get();


        foreach ($total_keywords as $object) {
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']] += $item['engagement'];
            } else {
                $message_keyword[$item['keyword_id']] = $item['engagement'];
            }

            $message_total += $item['engagement'];
        }

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }
            $data[$keyword_id]['value'][] = [
                'keyword' => $value['keyword_name'],
                'keyword_id' => $value['keyword_id'],
                'percentage' => $message_total,
                "no_of_message" => $this->point_two_digits($message_total),
                "type" => ($message_total >= 0 ? "plus" : "minus"),
            ];
        }
        return $data;
//        $dummy_data[] = [
//            "id" =>  1,
//            "site_domain" =>  'www.google.com',
//            "keyword_id" => 1,
//            "no_of_message" => 1000,
//            "percentage" => 1000,
//            "type" => 'plus'
//        ];

    }

    private function topHashtag($campaign_id, $start_date, $end_date)
    {
        $dummy_data[] = [
            "id" => 1,
            "hashtag" => '#hashtag1',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" => 2,
            "hashtag" => '#hashtag2',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" => 3,
            "hashtag" => '#hashtag3',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" => 4,
            "hashtag" => '#hashtag4',
            "keyword_id" => 1,
            "no_of_message" => 1000,
            "percentage" => 1000,
            "type" => 'plus'
        ];

        $dummy_data[] = [
            "id" => 5,
            "hashtag" => '#hashtag5',
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

        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereIn('classification_type_id', [1])
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('keyword_id')
            ->get();

        foreach ($total_keywords as $item) {
            $message = $this->shareOfVoiceByNumber($this->campaign_id, $this->start_date, $this->end_date, $item->keyword_id);
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

        $total_keywords = DB::table('message_result_full_data')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('keyword_name', 'source_id')
            ->get();

        foreach ($total_keywords as $item) {
            $keyword_id = $item->keyword_id;
            $data[$keyword_id]['keyword_id'] = $item->keyword_id;
            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            $data[$keyword_id]['organization_id'] = 1;
            $data[$keyword_id]['organization_name'] = 'organizations_name 1';
            $keyword_id = $item->keyword_id;
            $message = $this->shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
            $total_message = DB::table('message_result_full_data')->where('campaign_id', $campaign_id)
                ->where('keyword_id', $item->keyword_id)
                ->whereIn('classification_type_id', [1])
                ->whereBetween('date_m', [$start_date, $end_date])
                ->get()
                ->count();

    
            $percentage = ($message / $total_message) * 100;
    
            $push_data = [
                'channel' => $item->source_name,
                'percentage' => self::point_two_digits($percentage),
                'number_of_message' => $message,
                // 'highlight' =>
            ];
    
            $data[$keyword_id]['value'][] = $push_data;
        }

        // $data = $this->factorListData($total_keywords, 'shareofvoice', $campaign_id, $start_date, $end_date, null, 'daily_message', 'total_at_date');

//        foreach ($total_keywords as $item) {
//            $keyword_id = $item->keyword_id;
//            $data[$keyword_id]['keyword_id'] = $item->keyword_id;
//            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
//            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
//            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
//            $data[$keyword_id]['organization_id'] = $item->organization_id;
//            $data[$keyword_id]['organizations_name'] = $item->organizations_name;
//
//                $message = $this->shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
//                $total_message = DailyMessage::where('campaign_id', $campaign_id)
//                    ->where('keyword_id', $item->keyword_id)
//                    ->where('organization_id', $item->organization_id)
//                    ->where('campaign_name', $item->campaign_name)
//                    ->whereBetween('date_m', [$start_date, $end_date])
//                    ->sum('total_at_date');
//
//                $percentage = ($message / $total_message) * 100;
//
//                $push_data = [
//                    'channel' => $item->source_name,
//                    'percentage' => $this->point_two_digits($percentage),
//                    'number_of_message' => $message,
//                    // 'highlight' =>
//                ];
//
//                $data[$keyword_id]['value'][] = $push_data;
//
//        }
//
       if ($data) {
          $data = array_values($data);
       }

        return parent::handleRespond($data);
    }

    public function sentimentLevel(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $raw_query = MessageResultSemetic::where('campaign_id', $campaign_id)
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        if ($start_date && $end_date) {
            $raw_query->whereBetween('date_m', [$start_date, $end_date]);
        }

        $results = $raw_query->groupBy('campaign_id')->groupBy('keyword_id')->groupBy('total_sem')->get();


        if ($results) {

            foreach ($results as $index => $result) {

                if (isset($data[$result->keyword_id])) {
                    $data[$result->keyword_id][$result->classification_name] += $result->total_sem;
                } else {

                    $data[$result->keyword_id] = [
                        'keyword_id' => $result->keyword_id,
                        'keyword_name' => $result->keyword_name,
                        'campaign_id' => $result->campaign_id,
                        'campaign_name' => $result->campaign_name,
                        'organization_id' => 1,
                        'organizations_name' => 'organizations_name 1',
                        'Negative' => 0,
                        'Neutral' => 0,
                        'Positive' => 0,
                    ];
                }

            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }
    }


    private function getRootNode($campaign_id, $keyword_id, $message_id, $start_date, $end_date)
    {
        $data = [];
        $snas = SNARootNode::where('campaign_id', $campaign_id)
            ->where('keyword_id', $keyword_id)
            ->where('message_id', $message_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('message_id')
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

    private function getChildNode($campaign_id, $keyword_id, $message_id, $start_date, $end_date)
    {
        $data = [];
        $snas = SNAChildNode::where('campaign_id', $campaign_id)
            ->where('keyword_id', $keyword_id)
            ->where('reference_message_id', $message_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('message_id')
            ->get();

        foreach ($snas as $sna) {
            $data[] = [
                "id" => $sna->message_id,
                "label" => $sna->author,
                "title" => $sna->author,
                "parent_id" => $sna->reference_message_id,
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
                "text" => "ถูก",
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
                "text" => "เวลา",
                "value" => 77
            ],
            [
                "text" => "thing",
                "value" => 45
            ],
            [
                "text" => "ซ้าย",
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
                "text" => "ปัญหา",
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
                "text" => "ชั่วโมง",
                "value" => 35
            ],
            [
                "text" => "wait",
                "value" => 38
            ],
            [
                "text" => "โรงพยาบาล",
                "value" => 11
            ],
            [
                "text" => "eye",
                "value" => 13
            ],
            [
                "text" => "ทดสอบ",
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
                "text" => "คำถาม",
                "value" => 20
            ],
            [
                "text" => "ออฟฟิศ",
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
                "text" => "สมุนไพร",
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
                "text" => "งาน",
                "value" => 64
            ],
            [
                "text" => "ยิ้ม",
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
                "text" => "ความสุข",
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
                "text" => "ข้อความ",
                "value" => 35
            ],
            [
                "text" => "best",
                "value" => 54
            ],
            [
                "text" => "เดือน",
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
                "text" => "จ่ายแล้ว",
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

    private function messagesTable($campaign_id, $start_date, $end_date, $keyword_id, $table = null, $colum = null)
    {
        return DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->whereIn('classification_type_id', [1])
            ->get()
            ->count();
    }

    private function engagementTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        return DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->whereIn('classification_type_id', [1])
            ->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));
    }

    private function accountTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        return DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->where('keyword_id', $keyword_id)
            ->groupBy('author')
            ->get()
            ->count();
    }

    private function shareOfVoiceByNumber($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_account = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->where('keyword_id', $keyword_id)
            ->get()
            ->count();

        return $total_account;
    }

    private function getDataByCondition(
        $table,
        $campaign_id,
        $start_date,
        $end_date,
        $keyword_id = null,
        $source_id = null,
        $column = null,
        $type = null, $condition = null)
    {


        $items = DB::table($table)
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }

        if ($keyword_id) {
            $items->where('keyword_id', $keyword_id);
        }


        if ($source_id && $source_id !== 'all') {
            $items->where('source_id', $source_id);
        }


        return $this->factorListData($items->get(), $type, $campaign_id, $start_date, $end_date, $keyword_id, $table, $column, $condition);
    }

    private function factorListData($items, $type, $campaign_id = null, $start_date = null, $end_date = null, $keyword_id = null, $table = null, $column = null, $condition = null)
    {

        $data = null;

        if ($type === 'percentage') {
            $data = $this->findPercentage($items, $column, $start_date, $end_date);
        }

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;

            if ($type !== 'engagement') {
                $data[$keyword_id]['keyword_id'] = $item->keyword_id;
                $data[$keyword_id]['keyword_name'] = $item->keyword_name;
                $data[$keyword_id]['campaign_id'] = $item->campaign_id;
                $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            }

            if ($type !== 'engagement' && isset($item->source_id) && $item->source_id) {
                $data[$keyword_id]['source_id'] = $item->source_id;
                $data[$keyword_id]['source_name'] = $item->source_name;
            }

            if ($type === 'source' || $type === 'daily_message') {
                $nestData = [
                    'source_id' => $item->source_id,
                    'source_name' => $item->source_name,
                    'date_m' => $item->date_m,
                    'total_at_date' => $item->total_at_date
                ];

                $data[$keyword_id]['value'][] = $nestData;
            }

            if ($type === 'engagement') {

                $nestData['keyword_id'] = $item->keyword_id;
                $nestData['keyword_name'] = $item->keyword_name;
                $nestData['campaign_id'] = $item->campaign_id;
                $nestData['campaign_name'] = $item->campaign_name;


                if (isset($nestData["value"][$item->source_id])) {
                    $nestData["value"][$item->source_id][] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                } else {
                    $nestData["value"][$item->source_id] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                }


                if (isset($data[$item->keyword_id])) {
                    $data[$item->keyword_id]['value'][] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                } else {
                    $data[$item->keyword_id] = $nestData;
                }

                if (isset($data[$item->keyword_id]['value'])) {
                    $data[$item->keyword_id]['value'] = array_values($data[$item->keyword_id]['value']);
                }

            }

            if ($type === 'shareofvoice') {
                $message = $this->shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
                $total_message = DB::table($table)->where('campaign_id', $campaign_id)
                    ->where('keyword_id', $item->keyword_id)
                    ->where('organization_id', $item->organization_id)
                    ->where('campaign_name', $item->campaign_name)
                    ->whereBetween('date_m', [$start_date, $end_date])
                    ->sum($column);

                $percentage = ($message / $total_message) * 100;

                $push_data = [
                    'channel' => $item->source_name,
                    'percentage' => self::point_two_digits($percentage),
                    'number_of_message' => $message,
                    // 'highlight' =>
                ];

                $data[$keyword_id]['value'][] = $push_data;
            }

            if ($type === '') {

            }

        }


        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    private function shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $total_message = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->where('source_id', $source_id)
            ->whereIn('classification_type_id', [1])
            ->get()
            ->count();

        return $total_message;
    }

    private function findPercentage($items, $column, $start_date, $end_date)
    {
        $message_keyword = [];
        $message_total = 0;


        foreach ($items as $object) {
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']] += $item[$column];
            } else {
                $message_keyword[$item['keyword_id']] = $item[$column];
            }

            $message_total += $item[$column];
        }

        $data = null;

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
            ];
        }

        return $data;
    }
}
