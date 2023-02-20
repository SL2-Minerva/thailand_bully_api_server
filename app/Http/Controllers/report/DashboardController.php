<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Message;
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
        $this->source_id = $request->source === "all" ? "" : $request->source;

        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

    }

    public function dailyBy()
    {

    }

    public function overAll(Request $request)
    {
        $data = null;

        $data['daily_message'] = $this->dailyMessage($this->start_date, $this->end_date);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->start_date, $this->end_date, $this->source_id ?? null);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->start_date_previous, $this->end_date_previous, $this->source_id ?? null);

        return parent::handleRespond($data);
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

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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
                    "keyword_id" => $item->keyword_id,
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


        if ($data) {
            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            $data = array_values($data);
        }


        return $data;
    }

    private function percentageOfMessages($start_date, $end_date, $source_id_id = null)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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
                $percentage = $message_total ? self::point_two_digits(($value / $message_total) * 100) : $message_total;
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
            $data[$keyword_id]['total'] = $message_total;
        }


        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    public function keyStats(Request $request)
    {
        $data = null;
        $source_id = $request->source ?? null;

        $data['total_messages'] = $this->totalMessages($this->start_date, $this->end_date, $source_id, $this->start_date_previous, $this->end_date_previous);
        $data['total_engagement'] = $this->totalEngagement($this->start_date, $this->end_date, $source_id, $this->start_date_previous, $this->end_date_previous);
        $data['total_accounts'] = $this->totalAccounts($this->start_date, $this->end_date, $source_id, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    public function sentimentScore(Request $request)
    {

        $current = $this->findSentiment('message_result_full_data', $this->start_date, $this->end_date, $this->source_id);
        $pervious = $this->findSentiment('message_result_full_data', $this->start_date_previous, $this->end_date_previous, $this->source_id);

        return parent::handleRespond([
            "neutral_value" => (float)self::point_two_digits($current['results']),
            "sentiment_percentage" => $current['sentiment_percentage'],
            "pervious_sentiment" => (float)self::point_two_digits($pervious['results']),
            "text" => $current['text']
        ]);
    }

    private function findSentiment($table, $start_date, $end_date, $source_id_id = null)
    {
        $positive = 0;
        $negative = 0;
        $neutral = 0;
        $sentiment_score = 0;
        $table = 'message_result_full_data';

        $results = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->whereIn('classification_name', ["Positive", 'Negative', 'Neutral']);

        if ($this->keyword_id) {
            $results->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $results->where('source_id', $this->source_id);
        }

        $results = $results->get();

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

            $sentiment_score = (((1 * $positive ?? 0) + (-1 * $negative ?? 1)) / ($positive + $negative + $neutral)) * 5;
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

        $current = $this->findSentiment('message_result_semetic', $this->start_date, $this->end_date, $this->source_id);
        $message_total = $current['positive'] + $current['negative'] + $current['neutral'];

        return parent::handleRespond([
            "positive_percentage" => $message_total ? (float)self::point_two_digits(($current['positive'] / $message_total) * 100) : 0,
            "neutral_percentage" => $message_total ? (float)self::point_two_digits(($current['neutral'] / $message_total) * 100) : 0,
            "negative_percentage" => $message_total ? (float)self::point_two_digits(($current['negative'] / $message_total) * 100) : 0,
        ]);
    }

    public function keywordSummary(Request $request)
    {
        $data = null;
        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('keyword_id');

        if ($this->keyword_id) {
            $total_keywords->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        $diff_date = $this->diff_date($this->start_date, $this->end_date);
        $id = 1;

        foreach ($total_keywords->get() as $item) {
            $message = $this->messagesTable($this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
            $engagement = $this->engagementTable($this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
            $accounts = $this->accountTable($this->start_date, $this->end_date, $item->keyword_id, 'message_result_full_data');
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

        $data['main_keyword'] = $this->mainKeyWords($this->start_date, $this->end_date);
        $data['top_sites'] = $this->topSites($this->start_date, $this->end_date);
        $data['top_hastag'] = $this->topHashtag($this->start_date, $this->end_date);

        return parent::handleRespond($data);
    }

    private function totalMessages($start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {

        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $total_current->whereIn('keyword_id', $this->keyword_id);
            $total_previous->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_current->where('source_id', $this->source_id);
            $total_previous->where('source_id', $this->source_id);
        }

        $total_current = $total_current->get()->count();
        $total_previous = $total_previous->get()->count();

        $diff_date = $this->diff_date($start_date, $end_date);

        $comparison = $total_current - $total_previous;
        $percentage = (($total_current - $total_previous) / ($total_previous === 0 ? 1 : $total_previous)) * 100;

        if ($percentage == -100) {
            $percentage = 0;
        }

        return [
            "total_message" => $this->point_two_digits($total_current),
            "average_message" => $diff_date ? $this->point_two_digits($total_current / $diff_date) : 0,
            "comparison" => $this->point_two_digits($comparison),
            "percentage" => $this->point_two_digits($percentage),
            "type" => ($comparison >= 0 ? "plus" : "minus")
        ];

    }

    private function totalEngagement($start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {
        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $total_current->whereIn('keyword_id', $this->keyword_id);
            $total_previous->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_current->where('source_id', $this->source_id);
            $total_previous->where('source_id', $this->source_id);
        }

        $total_current = $total_current->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));
        $total_previous = $total_previous->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));

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

    private function totalAccounts($start_date, $end_date, $source_id, $start_date_previous, $end_date_previous)
    {
        $total_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('author');

        $total_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
            ->whereIn('classification_type_id', [1])
            ->groupBy('author');

        if ($this->keyword_id) {
            $total_current->whereIn('keyword_id', $this->keyword_id);
            $total_previous->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_current->where('source_id', $this->source_id);
            $total_previous->where('source_id', $this->source_id);
        }

        $total_current = $total_current->get()->count();
        $total_previous = $total_previous->get()->count();

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

    public function mainKeyWords($start_date, $end_date)
    {

        $data = null;
        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('keyword_id');

        if ($this->keyword_id) {
            $total_keywords->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        $id = 1;

        foreach ($total_keywords->get() as $item) {
            $message = $this->messagesTable($start_date, $end_date, $item->keyword_id);
            $total_message = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
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

    private function topSites($start_date, $end_date)
    {
        $data = null;

        $message_keyword = [];
        $message_total = 0;

        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('source_id', 5)
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $total_keywords->whereIn('keyword_id', $this->keyword_id);
        }


//        if ($this->source_id) {
//            $total_keywords->where('source_id', $this->source_id);
//        }

        $total_keywords = $total_keywords->get();

        $id = 1;

        foreach ($total_keywords as $object) {
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']] += $item['number_of_comments'] + $item['number_of_shares'] + $item['number_of_reactions'];
            } else {
                $message_keyword[$item['keyword_id']] = $item['number_of_comments'] + $item['number_of_shares'] + $item['number_of_reactions'];
            }

            $message_total += $item['number_of_comments'] + $item['number_of_shares'] + $item['number_of_reactions'];
        }

        foreach ($message_keyword as $keyword_id => $value) {
            $id + 1;
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }

            $data[$keyword_id] = [
                'id' => $id++,
                'keyword' => $this->find_keyword_name($keyword_id),
                'keyword_id' => $keyword_id,
                'percentage' => $percentage,
                "no_of_message" => $this->point_two_digits($message_total),
                "type" => ($message_total >= 0 ? "plus" : "minus"),
            ];
        }

        if ($data) {
            $data = array_values($data);
        }

        return $data;

    }

    private function topHashtag($start_date, $end_date)
    {

        $keywords = Keyword::where('campaign_id', $this->campaign_id)->get('id');

        $raw_total = DB::table('hashtags')
            ->whereIn('keyword_id', $keywords->pluck('id')->toArray())
            ->whereBetween('date_count', [$start_date, $end_date]);

        $raw = DB::table('hashtags')
            ->whereIn('keyword_id', $keywords->pluck('id')->toArray())
            ->whereBetween('date_count', [$start_date, $end_date])->orderBy('count_number', 'desc');


        if ($this->source_id) {
            $raw_total->where('source_id', $this->source_id);
        }

        $total_keywords = $raw_total->sum('count_number');

        $hashtags = $raw->get();
        $data = [];

        foreach ($hashtags as $hashtag) {
            $data[] = [
                "id" => $hashtag->id,
                "hashtag" => $hashtag->hashtag,
                "keyword_id" => $hashtag->keyword_id,
                "no_of_message" => $hashtag->count_number,
                "total_keyword" => $total_keywords,
                "percentage" => $total_keywords > 0 ? $hashtag->count_number / $total_keywords * 100 : 0,
                "type" => $hashtag->count_number >= 0 ? 'plus' : 'minus',
            ];
        }


        $dummy_data[] = [

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

        return $data;
    }

    public function shareOfVoiceNumber(Request $request)
    {
        $data = null;

        $total_keywords = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereIn('classification_type_id', [1])
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('keyword_id');

        if ($this->keyword_id) {
            $total_keywords->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        foreach ($total_keywords->get() as $item) {
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

        $total_keywords = DB::table('message_result_full_data')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('keyword_name', 'source_id');

        if ($this->keyword_id) {
            $total_keywords->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        foreach ($total_keywords->get() as $item) {
            $keyword_id = $item->keyword_id;
            $data[$keyword_id]['keyword_id'] = $item->keyword_id;
            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            $data[$keyword_id]['organization_id'] = 1;
            $data[$keyword_id]['organization_name'] = 'organizations_name 1';
            $keyword_id = $item->keyword_id;
            $message = $this->shareOfVoiceByPlatform($this->campaign_id, $this->start_date, $this->end_date, $item->keyword_id, $item->source_id);
            $total_message = DB::table('message_result_full_data')->where('campaign_id', $this->campaign_id)
                ->where('keyword_id', $item->keyword_id)
                ->whereIn('classification_type_id', [1])
                ->whereBetween('date_m', [$this->start_date, $this->end_date]);

            if ($this->keyword_id) {
                $total_message->whereIn('keyword_id', $this->keyword_id);
            }

            if ($this->source_id) {
                $total_message->where('source_id', $this->source_id);
            }

            $total_message = $total_message->get()->count();


            $percentage = ($message / $total_message) * 100;

            $push_data = [
                'channel' => $item->source_name,
                'percentage' => self::point_two_digits($percentage),
                'number_of_message' => $message,
                // 'highlight' =>
            ];

            $data[$keyword_id]['value'][] = $push_data;
        }
//
        if ($data) {
            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    public function sentimentLevel(Request $request)
    {
        $data = null;

        $raw_query = DB::table('message_result_full_data')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw_query->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_query->where('source_id', $this->source_id);
        }

        $raw_query = $raw_query->get();

        foreach ($raw_query as $index => $result) {

            if (isset($data[$result->keyword_id])) {
                $data[$result->keyword_id][$result->classification_name] += 1;
                $data[$result->keyword_id]['total'] += 1;
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
                    'total' => 0,
                ];
            }

        }

        if ($data) {

            foreach ($data as $item) {
                $data[$item['keyword_id']]['Negative'] = $item['total'] ? self::point_two_digits(($item['Negative'] / $item['total']) * 100) : 0;
                $data[$item['keyword_id']]['Positive'] = $item['total'] ? self::point_two_digits(($item['Positive'] / $item['total']) * 100) : 0;
                $data[$item['keyword_id']]['Neutral'] = $item['total'] ? self::point_two_digits(($item['Neutral'] / $item['total']) * 100) : 0;
            }
        }


        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
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

        $keywords = Keyword::where('campaign_id', $this->campaign_id)->get('id');


        $raw_total = DB::table('word_clouds')
            ->whereIn('keyword_id', $keywords->pluck('id')->toArray())
            ->whereBetween('date_count', [$this->start_date, $this->end_date])->orderBy('count_number');

        if ($this->source_id) {
            $raw_total->where('source_id', $this->source_id);
        }


        $worlds = $raw_total->get();

        $dummy_data = [];

        foreach ($worlds as $world) {
            $dummy_data[] = [
                'text' => $world->word,
                'value' => $world->count_number
            ];
        }

        return $dummy_data;
    }

    private function messagesTable($start_date, $end_date, $keyword_id, $table = null, $colum = null)
    {
        $count = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $count->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $count->where('source_id', $this->source_id);
        }

        $count = $count->get()->count();

        return $count;
    }

    private function engagementTable($start_date, $end_date, $keyword_id)
    {
        $count = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $count->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $count->where('source_id', $this->source_id);
        }

        $count = $count->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));;

        return $count;
    }

    private function accountTable($start_date, $end_date, $keyword_id)
    {
        $count = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->where('keyword_id', $keyword_id)
            ->groupBy('author');

        if ($this->keyword_id) {
            $count->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $count->where('source_id', $this->source_id);
        }

        $count = $count->get()->count();

        return $count;
    }

    private function shareOfVoiceByNumber($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_account = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->where('keyword_id', $keyword_id);

        if ($this->keyword_id) {
            $total_account->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_account->where('source_id', $this->source_id);
        }

        $total_account = $total_account->get()->count();

        return $total_account;
    }

    private function shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $keyword_id, $source_id_id)
    {
        $total_message = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->where('source_id', $source_id_id)
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $total_message->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $total_message->where('source_id', $this->source_id);
        }

        $total_message = $total_message->get()->count();

        return $total_message;
    }

    private function find_keyword_name($keyword_id)
    {
        $keyword_name = Keyword::where('id', $keyword_id)->first();
        return $keyword_name->name;
    }




    private function fillterBy($option)
    {

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

        //todo something
        $report_number = $request->report_number ?? null;
        $type = 1;

        if ($report_number === 'sna' || $report_number === '4.2.007') {

            $data = [
                "sentiment" => $this->getSNAbyType($request, 1),
                "bullyLevel" => $this->getSNAbyType($request, 2),
                "bullyType" => $this->getSNAbyType($request, 3),
            ];

            return parent::handleRespond($data);
        } else {
            return parent::handleRespond($this->getSNAbyType($request, $type));
        }


    }



    private function getChildNode($message_id, $start_date, $end_date)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('reference_message_id', $message_id)
            ->whereBetween('date_m', [$start_date, $end_date]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        foreach ($items as $sna) {
//            $size = (int)$sna->engagement <= 0 ? 10 : (int)$sna->engagement / 10;
//            $length = (int)$sna->engagement <= 0 ? 10 : (int)$sna->engagemen + 10;

            $data[] = [
                "id" => $sna->message_id,
                "label" => $sna->author,
                "title" => $sna->author,
                "parent_id" => $sna->reference_message_id,
                "color" => $sna->classification_color,
                "shape" => "dot"
            ];
        }

        return $data;
    }

//    private function getReferSna($data_node = [])
//    {
//        $data = $data_node;
////        $snas = SNA::where(SNA::CAMPAIGN_ID, $campaign_id)->where(SNA::REFERENCE_MESSAGE_ID, $message_id)->get();
//
//
//        foreach ($snas as $sna) {
//            $data['nodes'][] = [
//                "id" => $sna->message_id,
//                "label" => $sna->author,
//                "title" => $sna->author,
//                "color" => $sna->classification_color,
//                "shape" => "dot",
//                "size" => $sna->engagement,
//            ];
//
//
//            //            $data_node = $this->getReferSna($campaign_id, $sna->message_id, $data_node);
//        }
//
//        return $data;
//    }

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
        $data['word_clouds_table'] = $this->wordCloudsMessageTable($request);

        return parent::handleRespond($data);
    }

    private function wordCloudsMessageTable($request)
    {
        $data = null;

        $keywords = Keyword::where('campaign_id', $this->campaign_id)->get(['id', 'name']);

        $raw = DB::table('word_clouds')
            ->whereIn('keyword_id', $keywords->pluck('id')->toArray())
            ->whereBetween('date_count', [$this->start_date, $this->end_date])->orderBy('count_number');

        $raw_total = DB::table('message_result_full_data')
            ->whereIn('keyword_id', $keywords->pluck('id')->toArray())
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);


        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
            $raw_total->where('source_id', $this->source_id);
        }


        $wordclouds = $raw->get();
        $total = $raw_total->count();
        $data = [];
        $list_keywords = $keywords->pluck('name', 'id')->toArray();
        foreach ($wordclouds as $wordcloud) {

            $data[] = [
                'keyword' => $wordcloud->word,
                'keyword_id' => $wordcloud->keyword_id,
                'keyword_name' => $list_keywords[$wordcloud->keyword_id],
                'total' => $wordcloud->count_number,
                'percent' => round(($wordcloud->count_number / $total * 100), 2)
            ];
        }
        $select = $request->select ?? null;

        if (count($data) > 0) {
            switch ($select) {
                case "top10":
                    $data = array_slice($data, 0, 10);
                    break;
                case "top20":
                    $data = array_slice($data, 0, 20);
                    break;
                case "top50":
                    $data = array_slice($data, 0, 50);
                    break;
                default:
                    $data = array_slice($data, 0, 100);
            }
        }


        return $data;
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
        $data['wordCloudByAccount'] = $this->wordCloudByAccount($request);

        return parent::handleRespond($data);
    }

    private function wordCloudByAccount($request) {


        $data =  [
            [
                "author" => "Nguyễn Văn A",
                "source_id" => 1,
                "source_name" => "Facebook",
                "total_message" => 100,
                "engagements" => 12000
            ],
            [
                "author" => "Nguyễn Văn A",
                "source_id" => 6,
                "source_name" => "Pantip",
                "total_message" => 100,
                "engagements" => 12000
            ],

            [
                "author" => "aadads",
                "source_id" => 1,
                "source_name" => "Facebook",
                "total_message" => 100,
                "engagements" => 12000
            ],
        ];

        return $data;
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
        $data['wordCloudBySentimentType'] = $this->WordCloudBySentimentType($campaign_id, $start_date, $end_date, $select);

        return parent::handleRespond($data);
    }

    private function wordCloudBySentimentType($request) {
        $data =  [
            [
                "author" => "Nguyễn Văn A",
                "source_id" => 1,
                "source_name" => "Facebook",
                "total_message" => 100,
                "engagements" => 12000
            ],
            [
                "author" => "Nguyễn Văn A",
                "source_id" => 6,
                "source_name" => "Pantip",
                "total_message" => 100,
                "engagements" => 12000
            ],

            [
                "author" => "aadads",
                "source_id" => 1,
                "source_name" => "Facebook",
                "total_message" => 100,
                "engagements" => 12000
            ],
        ];

        return $data;
    }

    private function wordCloudsMessage($campaign_id, $start_date, $end_date, $select, $type)
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
//            case "top100":
//                $data = array_slice($dummy_data, 0, 100);
//                break;
//            default:
//                $data = $dummy_data;
            default:
                $data = array_slice($dummy_data, 0, 100);
        }

        return $data;
    }

}
