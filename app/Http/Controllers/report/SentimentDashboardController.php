<?php

namespace App\Http\Controllers\report;

use App\Models\Classification;
use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Sources;

class SentimentDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;
    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;

    private $keyword_id;
    private $source_id;

    private $table = 'message_result_full_data';

    public function __construct(Request $request)
    {

        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);

        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        if ($request->secure !== 'all') {
            $this->source_id = $request->source_id;
        }

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();


            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

        if ($request->period === 'customrange') {
            $this->start_date_previous =  $this->date_carbon($request->start_date_period);
            $this->end_date_previous =  $this->date_carbon($request->end_date_period);
        }
    }

    public function sentimentBy(Request $request)
    {
        return parent::handleRespond([
            "SentimentByDay" => $this->SentimentByDay($request, true),
            "SentimentByTime" => $this->SentimentByTime($request, true),
            "SentimentByDevice" => $this->SentimentByDevice($request, true),
            "SentimentByAccount" => $this->SentimentByAccount($request, true),
            "SentimentByChannel" => $this->SentimentByChannel($request, true),
            "SentimentBullyLevel" => $this->SentimentBullyLevel($request, true),
            "SentimentBullyType" => $this->SentimentBullyType($request, true),
            "SentimentScore" => $this->SentimentScore($request, true),
            "sentimentComparison" => $this->SentimentComparison($request, true),
        ]);
    }

    public function DailySeniment(Request $request)
    {
        $table = 'message_result_full_data';
        $data = null;
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        $raw_pre = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
            $raw_pre->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
            $raw_pre->whereIn('keyword_id', $this->keyword_id);
        }


        $data['sentiment'] = $this->sentiment($raw_current);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($raw_current, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($raw_pre, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }


    private function percentageOfMessages($raw, $start_date, $end_date)
    {

        $raw->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $items = $raw->get();

        $data = null;
        if ($items->count() <= 0) return null;

        $message_keyword = [];
        $message_total = 0;


        foreach ($items as $object) {
            $item = (array)$object;

            if (!isset($data[$item['classification_id']])) {

                $data[$item['classification_id']] = [
                    'keyword_id' => $item['classification_id'],
                    'keyword_name' => $item['classification_name'],
                    'campaign_id' => $item['campaign_id'],
                    'campaign_name' => $item['campaign_name'],
                ];
            }

            if (isset($message_keyword[$item['classification_id']])) {

                $message_keyword[$item['classification_id']] += 1;
            } else {

                $message_keyword[$item['classification_id']] = 1;
            }

            $message_total += 1;
        }


        foreach ($message_keyword as $classification_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }

            $data[$classification_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
                'total' => self::point_two_digits($message_total, 0)
            ];
        }


        if ($data) {
            $data = array_values($data);
        }


        return $data;

    }

    private function sentiment($raw)
    {
        $labels = [
            "Positive",
            "Neutral",
            "Negative"
        ];

        $raw->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $items = $raw->get();

        $data = null;
        if ($items->count() <= 0) return null;

        foreach ($items as $item) {
            $date_m = Carbon::parse($item->date_m)->format('Y-m-d');
            $index_label = array_search($item->classification_name, $labels);

            if (isset($data[$index_label])) {
                if (isset($data[$index_label]['value'][$date_m])) {
                    $data[$index_label]['value'][$date_m]['total_at_date'] += 1;
                } else {
                    $data[$index_label]['value'][$date_m] = [
                        'date' => $date_m,
                        'source_id' => $item->source_id,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$index_label] = [
                    'keyword_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'value' => []
                ];

                $data[$index_label]['value'][$date_m] = [
                    'date' => $date_m,
                    'source_id' => $item->source_id,
                    'total_at_date' => 1
                ];
            }
        }


        if ($data) {
            $data = array_values($data);

            foreach ($data as $key => $item) {
                $data[$key]['value'] = array_values($item['value']);
            }
        }
        return $data;
    }

    public function SentimentByDay(Request $request, $only_data = false)
    {

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }


        $items = $raw->get();
        foreach ($items as $item) {
            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
                $data['value'][$item->classification_id]['data'][$index_label] = 1;

            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentByTime(Request $request, $only_data = false)
    {

        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }


        $items = $raw->get();
        foreach ($items as $item) {

            $sixAM = Carbon::parse("06:00:00");
            $time = Carbon::parse($item->date_m)->format('H:i:s');
            $index_label = 3;

            if (Carbon::parse($time)->lt($sixAM)) {
                $index_label = 0;
            }

            if (Carbon::parse($time)->between($sixAM, Carbon::parse("12:00:00"))) {
                $index_label = 1;
            }

            if (Carbon::parse($time)->between(Carbon::parse("12:00:00"), Carbon::parse("18:00:00"))) {
                $index_label = 2;
            }

            if (Carbon::parse($time)->gt(Carbon::parse("18:00:00"))) {
                $index_label = 3;
            }


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentByDevice(Request $request, $only_data = false)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->where('keyword_id', $this->keyword_id);
        }


        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];


        $items = $raw->get();

        $check = [];

        foreach ($items as $item) {

            $index_label = null;

            if (!in_array($item->device, $check)) {
                $check[] = $item->device;
            }

            if ($item->device === 'android') {
                $index_label = 0;
            }

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp') {
                $index_label = 2;
            }

            if ($index_label !== null) {

                if (isset($data['value'][$item->classification_id])) {
                    $data['value'][$item->classification_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->classification_id] = [
                        'id' => $item->classification_id,
                        'keyword_name' => $item->classification_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'data' => [0, 0, 0]
                    ];

                    if ($index_label) {
                        $data['value'][$item->classification_id]['data'][$index_label] += 1;
                    }
                }
            }
        }


        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);

    }

    public function SentimentByAccount(Request $request, $only_data = false)
    {

        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        $table = 'message_result_full_data';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 1)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);

        if ($this->source_id) {
            $infulencer_root->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $infulencer_root->whereIn('keyword_id', $this->keyword_id);
        }

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {
            if ($infulencer->reference_message_id === null || $infulencer->reference_message_id === '') {
                if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                } else {
                    $data['value'][$infulencer->classification_id]['id'] = $infulencer->keyword_id;
                    $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->keyword_name;
                    $data['value'][$infulencer->classification_id]['data'][0] = 1;

                }
            }
        }

        $table = 'message_result_full_data';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 1)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);

        if ($this->source_id) {
            $follower_raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $follower_raw->whereIn('keyword_id', $this->keyword_id);
        }

        $followers = $follower_raw->get();


        foreach ($followers as $follower) {
            if ($infulencer->reference_message_id !== null || $infulencer->reference_message_id !== '') {
                if (isset($data['value'][$follower->classification_id]['data'][1])) {
                    $data['value'][$follower->classification_id]['data'][1] += 1;
                } else {
                    $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                    $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                    $data['value'][$follower->classification_id]['data'][1] = 1;
                }
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    public function SentimentByChannel(Request $request, $only_data = false)
    {


        $data = parent::listSource();
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        foreach ($items as $item) {
            $index_label = array_search($item->source_name, $data['labels']);
            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][$item->classification_id]['data'][] = 0;
                }

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {

            foreach ($data['value'] as $key => $value) {
                $data['value'][$key]['data'] = array_values($value['data']);
            }

            $data['value'] = array_values($data['value']);
        }


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentBullyLevel(Request $request, $only_data = false)
    {

        $data['labels'] = [
            "Level 0",
            "Level 1",
            "Level 2",
            "Level 3",
        ];

        $data['value'] = [
            [
                'id' => 1,
                'keyword_name' => 'Negative',
                'data' => [0, 0, 0, 0]
            ],
            [
                'id' => 2,
                'keyword_name' => 'Neutral',
                'data' => [0, 0, 0, 0]
            ],
            [
                'id' => 3,
                'keyword_name' => 'Positive',
                'data' => [0, 0, 0, 0]
            ],
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1, 3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        $items = $raw->get();


        $anylsys = [];

        foreach ($items as $item) {

            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        }


        foreach ($anylsys as $anylsy) {
            $index_data = 0;
            if ($anylsy[1] === 'Positive') {
                $index_data = 2;
            }

            if ($anylsy[1] === 'Neutral') {
                $index_data = 1;
            }

            $index_label = array_search($anylsy[3], $data['labels']);
            $data['value'][$index_data]['data'][$index_label] += 1;

        }

//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                19,
//                38,
//                47,
//                16,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                32,
//                78,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                15,
//                45,
//                23,
//                53,
//            ]
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SentimentBullyType(Request $request, $only_data = false)
    {

        $sentiment = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $data['value'] = [
            [
                'id' => 1,
                'keyword_name' => 'Negative',

            ],
            [
                'id' => 2,
                'keyword_name' => 'Neutral',

            ],
            [
                'id' => 3,
                'keyword_name' => 'Positive',

            ],
        ];

        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['value'][0]['data'][$i] = 0;
            $data['value'][1]['data'][$i] = 0;
            $data['value'][2]['data'][$i] = 0;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1, 2]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        $items = $raw->get();


        $anylsys = [];

        foreach ($items as $item) {

            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        }

//        dd($anylsys);
        foreach ($anylsys as $anylsy) {
            $index_data = 0;
            if ($anylsy[1] === 'Positive') {
                $index_data = 2;
            }

            if ($anylsy[1] === 'Neutral') {
                $index_data = 1;
            }

            $index_label = array_search($anylsy[2], $data['labels']);
            $data['value'][$index_data]['data'][$index_label] += 1;

        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    public function periodAndComparison(Request $request)
    {
        return parent::handleRespond([
            "PeriodOverPeriod" => $this->PeriodOverPeriod($request, true),
            "ComparisonByChannel" => $this->ComparisonByChannel($request, true),
            "ComparisonByEngagementType" => $this->ComparisonByEngagementType($request, true),
        ]);
    }

    public function PeriodOverPeriod(Request $request, $only_data = false)
    {

        $data = null;
        $table = 'message_result_full_data';
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
            $raw_previous->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw_current->whereIn('source_id', $source_ids);
            $raw_previous->whereIn('source_id', $source_ids);
        }


        $total_share_current = $raw_current->where('classification_name', 'Positive')->count();
        $total_share_previous = $raw_previous->where('classification_name', 'Positive')->count();

        $total_comment_current = $raw_current->where('classification_name', 'Neutral')->count();
        $total_comment_previous = $raw_previous->where('classification_name', 'Neutral')->count();

        $total_reactions_current = $raw_current->where('classification_name', 'Negative')->count();
        $total_reactions_previous = $raw_previous->where('classification_name', 'Negative')->count();

        $totalEngagement_current = $total_share_current + $total_comment_current + $total_reactions_current;
        $totalEngagement_previous = $total_share_previous + $total_comment_previous + $total_reactions_previous;




//        $current = $raw_current->get();
//        $previous = $raw_previous->get();


        $data['totalSentiment'] = [
            "totalValue" => $this->custom_number_format((int)$totalEngagement_current),
            "comparison" => (float)parent::point_two_digits($totalEngagement_current - $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous) : 0),
            "type" => $totalEngagement_current - $totalEngagement_previous > 0 ? "plus" : "minus",
        ];


        $data['positive'] = [
            "totalValue" => $this->custom_number_format((int)$total_share_current),
            "comparison" => $total_share_previous ? (float)parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous * 100) : 0) : 0,
            "type" => $total_share_current - $total_share_previous > 0 ? "plus" : "minus",
        ];

        $data['neutral'] = [
            "totalValue" => $this->custom_number_format((int)$total_comment_current),
            "comparison" => (float)parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0),
            "type" => $total_comment_current - $total_comment_previous > 0 ? "plus" : "minus",
        ];

        $data['negative'] = [
            "totalValue" => $this->custom_number_format((int)$total_reactions_current),
            "comparison" => (float)parent::point_two_digits($this->overPeriodComparison($total_reactions_current, $total_reactions_previous)),
            "type" => $total_reactions_current - $total_reactions_previous > 0 ? "plus" : "minus",
        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function ComparisonByChannel(Request $request, $only_data = false)
    {

        $data = parent::listSource();

        $analysis_current = $this->factoryComparisonByChangel($this->start_date, $this->end_date);
        $analysis_previous = $this->factoryComparisonByChangel($this->start_date_previous, $this->end_date_previous);


        $data['value'][0] = [
            "id" => 1,
            "keyword_name" => "Current",
        ];

        $data['value'][1] = [
            "id" => 2,
            "keyword_name" => "Previous",
        ];

        for ($i = 0; $i <= count($data['labels']); $i++) {
            $data['value'][0]['data'][$i] = 0;
            $data['value'][1]['data'][$i] = 0;
            $data['positive'][$i] = 0;
            $data['neutral'][$i] = 0;
            $data['negative'][$i] = 0;


        }

        foreach ($analysis_current as $index => $value) {
            $analysis_previous_position = 0;
            $analysis_previous_neutral = 0;
            $analysis_previous_negative = 0;

            if (isset($analysis_previous[$index])) {
                $analysis_previous_position = $analysis_previous[$index]['positive'];
                $analysis_previous_neutral = $analysis_previous[$index]['neutral'];
                $analysis_previous_negative = $analysis_previous[$index]['negative'];
            }

            $index_label = array_search($value['source_name'], $data['labels']);
            $data['value'][0]['data'][$index_label] = $value['total'];

            $data['positive'][$index_label] = self::overPeriodComparison($value['positive'], $analysis_previous_position);
            $data['neutral'][$index_label] = self::overPeriodComparison($value['neutral'], $analysis_previous_neutral);
            $data['negative'][$index_label] = self::overPeriodComparison($value['negative'], $analysis_previous_negative);
        }


        foreach ($analysis_previous as $value) {
            $index_label = array_search($value['source_name'], $data['labels']);
            $data['value'][1]['data'][$index_label] = $value['total'];
        }


        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    private function factoryComparisonByChangel($start_date, $end_data)
    {

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_data . " 23:59:59"]);

        $raw_current->where('classification_type_id', 1);
        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw_current->whereIn('source_id', $source_ids);
        }

        $items = $raw_current->get();


        $analysis = [];
        foreach ($items as $item) {


            if (isset($analysis[$item->source_id])) {

                $analysis[$item->source_id]['total'] += 1;

                if ($item->classification_name == 'Positive') {
                    $analysis[$item->source_id]['positive'] += 1;
                } else if ($item->classification_name == 'Neutral') {
                    $analysis[$item->source_id]['neutral'] += 1;
                } else if ($item->classification_name == 'Negative') {
                    $analysis[$item->source_id]['negative'] += 1;
                }


            } else {
                $analysis[$item->source_id] = [
                    "source_name" => $item->source_name,
                    "total" => 0,
                    "positive" => 0,
                    "neutral" => 0,
                    "negative" => 0,
                ];

                $analysis[$item->source_id]['total'] += 1;

                if ($item->classification_name == 'Positive') {
                    $analysis[$item->source_id]['positive'] += 1;
                } else if ($item->classification_name == 'Neutral') {
                    $analysis[$item->source_id]['neutral'] += 1;
                } else if ($item->classification_name == 'Negative') {
                    $analysis[$item->source_id]['negative'] += 1;
                }

            }
        }

        return $analysis;

    }


    public function ComparisonByEngagementType(Request $request, $only_data = false)
    {


        $analysis_current = $this->factoryComparisnByEngagementType($this->start_date, $this->end_date);
        $analysis_previous = $this->factoryComparisnByEngagementType($this->start_date_previous, $this->end_date_previous);

        $data['labels'] = [
            "Share",
            "Comment",
            "Reaction"
        ];

        $data['value'][0] = [
            "id" => 1,
            "keyword_name" => "Current",
            "data" => [
                0,
                0,
                0,
            ]
        ];

        $data['value'][1] = [
            "id" => 2,
            "keyword_name" => "Previous",
            "data" => [
                0,
                0,
                0,
            ]
        ];


        $data['value'][0]['data'][0] = $analysis_current['share'];
        $data['value'][0]['data'][1] = $analysis_current['comment'];
        $data['value'][0]['data'][2] = $analysis_current['reaction'];

        $data['value'][1]['data'][0] = $analysis_previous['share'];
        $data['value'][1]['data'][1] = $analysis_previous['comment'];
        $data['value'][1]['data'][2] = $analysis_previous['reaction'];

//        dd($analysis_current);


        $data['positive'][0] = self:: overPeriodComparison($analysis_current['share_data']['positive'], $analysis_previous['share_data']['positive']);
        $data['positive'][1] = self:: overPeriodComparison($analysis_current['comment_data']['positive'], $analysis_previous['comment_data']['positive']);
        $data['positive'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['positive'], $analysis_previous['reaction_data']['positive']);
//
        $data['neutral'][0] = self:: overPeriodComparison($analysis_current['share_data']['neutral'], $analysis_previous['share_data']['neutral']);
        $data['neutral'][1] = self:: overPeriodComparison($analysis_current['comment_data']['neutral'], $analysis_previous['comment_data']['neutral']);
        $data['neutral'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['neutral'], $analysis_previous['reaction_data']['neutral']);

        $data['negative'][0] = self:: overPeriodComparison($analysis_current['share_data']['negative'], $analysis_previous['share_data']['negative']);
        $data['negative'][1] = self:: overPeriodComparison($analysis_current['comment_data']['negative'], $analysis_previous['comment_data']['negative']);
        $data['negative'][2] = self:: overPeriodComparison($analysis_current['reaction_data']['negative'], $analysis_previous['reaction_data']['negative']);


//        $data['reaction'][2] = self:: overPeriodComparison($analysis_current['reaction'], $analysis_previous['reaction']);
//        $data['neutral'] = self:: overPeriodComparison($analysis_current['neutral'], $analysis_previous['neutral']);
//        $data['negative'] = self:: overPeriodComparison($analysis_current['negative'], $analysis_previous['negative']);


//        foreach ($analysis_current as $value) {
//        dd($value);


//            dd($value);
//
//            $data['value'][0]['data'][$index_label] = $value['total'];
//
//            $data['positive'] = self::point_two_digits(($value['positive'] / $value['total']) * 100);
//            $data['neutral'] = self::point_two_digits(($value['neutral'] / $value['total']) * 100);
//            $data['negative'] = self::point_two_digits(($value['negative'] / $value['total']) * 100);
//        }


//        if ($data['value']) {
//            $data['value']= array_values($data['value']);
//        }


//        $data = [
//            "labels" => [
//                "Share",
//                "Comment",
//                "Reaction"
//            ],
//            "value" => [
//                [
//                    "id" => 1,
//                    "keyword_name" => "Previous",
//                    "data" => [
//                        47,
//                        16,
//                        30
//                    ]
//                ],
//                [
//                    "id" => 2,
//                    "keyword_name" => "Current",
//                    "data" => [
//                        12,
//                        16,
//                        78
//                    ]
//                ]
//            ],
//            "positive" => [
//                "-30%",
//                "-30%",
//                "-30%"
//            ],
//            "neutral" => [
//                "-23%",
//                "-23%",
//                "-23%"
//            ],
//            "negative" => [
//                "-56%",
//                "-56%",
//                "-56%"
//            ]
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    private function factoryComparisnByEngagementType($start_date, $end_data)
    {

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_data . " 23:59:59"]);


        $raw_current->whereIn('classification_type_id', [1, 3]);

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
        }
        $items = $raw_current->get();

        $analysis = [
            "share" => 0,
            "comment" => 0,
            "reaction" => 0,
            "total" => 0,
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'share_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
            'comment_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
            'reaction_data' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ],
        ];


        foreach ($items as $item) {

            if ($item->classification_id === 1) {
                $analysis['positive'] += 1;
            } else if ($item->classification_id === 2) {
                $analysis['negative'] += 1;
            } else if ($item->classification_id === 3) {
                $analysis['neutral'] += 1;
            }

            if ($item->number_of_shares) {

                if ($item->classification_id === 1) {
                    $analysis['share_data']['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis['share_data']['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis['share_data']['neutral'] += 1;
                }
            }


            if ($item->number_of_comments) {
                if ($item->classification_id === 2) {
                    $analysis['comment_data']['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis['comment_data']['negative'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis['comment_data']['neutral'] += 1;
                }
            }


            if ($item->number_of_reactions) {
                if ($item->classification_id === 3) {
                    $analysis['reaction_data']['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis['reaction_data']['negative'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis['reaction_data']['neutral'] += 1;
                }
            }


            $analysis['share'] += $item->number_of_shares ? 1 : 0;
            $analysis['comment'] += $item->number_of_comments ? 1 : 0;
            $analysis['reaction'] += $item->number_of_reactions ? 1 : 0;
            $analysis['total'] += ($item->number_of_shares || $item->number_of_comments || $item->number_of_reactions) ? 1 : 0;
        }

        return $analysis;
    }


    private function factorySentimentScore($start_date, $end_data)
    {
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_data . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
        }

        $items = $raw_current->get();
        $analysis = [];
        $message_total = 0;
        $max = ['value' => 0, 'hightlightColor' => ''];
        $check = [];
        foreach ($items as $item) {
            $message_total += 1;

            if (!in_array($item->keyword_id, $check)) {
                $check[] = $item->keyword_id;
            }

            if (isset($analysis[$item->keyword_id])) {
                $analysis[$item->keyword_id]['total'] += 1;
                if ($item->classification_id === 1) {
                    $analysis[$item->keyword_id]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->keyword_id]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->keyword_id]['neutral'] += 1;
                }
            } else {
                $analysis[$item->keyword_id] = [
                    'total' => 1,
                    'keyword_name' => $item->keyword_name,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
                if ($item->classification_id === 1) {
                    $analysis[$item->keyword_id]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->keyword_id]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->keyword_id]['neutral'] += 1;
                }
            }

        }


        $results = [];

        foreach ($analysis as $keyword_id => $item) {

            $max = max($item['positive'], $item['neutral'], $item['negative']);
            $index_max = array_search($max, $item);
            $results[$keyword_id] = [
                'total' => $item['total'],
                'keyword_name' => $item['keyword_name'],
                'positive' => $item['positive'],
                'neutral' => $item['neutral'],
                'negative' => $item['negative'],
                'hightlightColor' => $index_max,
                'message_total' => $message_total,
            ];

        }

        return $results;
    }

    public function SentimentScore(Request $request, $only_data = false)
    {
        $data = ['senitment_score_data' => [], 'senitment_score_percentage' => []];

        $analysis_current = $this->factorySentimentScore($this->start_date, $this->end_date);
        $analysis_previous = $this->factorySentimentScore($this->start_date_previous, $this->end_date_previous);


        foreach ($analysis_current as $keyword_id => $item) {

            $positive = $item['positive'];
            $neutral = $item['neutral'];
            $negative = $item['negative'];
            $total = $item['total'];

            $p_positive = 0;
            $p_negative = 0;
            $p_neutral = 0;
            $total_positive_negative_neutral = 0;

            if (isset($analysis_previous[$keyword_id])) {
                $p_positive = $analysis_previous[$keyword_id]['positive'];
                $p_negative = $analysis_previous[$keyword_id]['negative'];
                $p_neutral = $analysis_previous[$keyword_id]['neutral'];
                $total_positive_negative_neutral = $p_positive + $p_negative + $p_neutral;
            }


            $check_sentiment_score = (((1 * $positive) + (-1 * $negative)) / ($positive + $negative + $neutral)) * 5;

            $sentimentScore = 0;
            $sentimentScore_previous = 0;
            $check_sentiment_score_previous = 0;


            if ($check_sentiment_score > 5) {
                $sentimentScore = 5;
            } else if ($check_sentiment_score < -5) {
                $sentimentScore = -5;
            } else {
                $sentimentScore = round($check_sentiment_score);
            }


            if ($sentimentScore == -0 ) {
                $sentimentScore = 0;
            }


            if ($total_positive_negative_neutral) {
                $check_sentiment_score_previous = (((1 * $p_positive) + (-1 * $p_negative / $total_positive_negative_neutral) * 5));

                if ($check_sentiment_score_previous > 5) {
                    $sentimentScore_previous = 5;
                } else if ($check_sentiment_score_previous < -5) {
                    $sentimentScore_previous = -5;
                } else {
                    $sentimentScore_previous = round($check_sentiment_score_previous);
                }

                if ($sentimentScore_previous == -0 ) {
                    $sentimentScore_previous = 0;
                }
            }

            $data['senitment_score_data'][$keyword_id] = [
                'keyword_name' => $item['keyword_name'],
                "sentimentScore" => $sentimentScore,
                "previous_period" => $sentimentScore_previous,
                "positive" => $positive,
                "neutral" => $neutral,
                "negative" => $negative,
                "p_positive" => $p_positive,
                "p_neutral" => $p_neutral,
                "p_negative" => $p_negative,
                "hightlightColor" => $item['hightlightColor']
            ];

            $data['senitment_score_percentage'][$keyword_id] = [
                'keyword_id' => $keyword_id,
                'keyword_name' => $item['keyword_name'],
                "positive" => self::point_two_digits(($positive / $total) * 100),
                "neutral" => self::point_two_digits(($neutral / $total) * 100),
                "negative" => self::point_two_digits(($negative / $total) * 100),

            ];
        }




        if ($data['senitment_score_data']) {
            $data['senitment_score_data'] = array_values($data['senitment_score_data']);
        }
        if ($data['senitment_score_percentage']) {
            $data['senitment_score_percentage'] = array_values($data['senitment_score_percentage']);
        }


//
        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    private function factorySentimentComparison($start_date, $end_date)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }


        $items = $raw->get();
        $analysis = [];

        foreach ($items as $item) {
            if (isset($analysis[$item->keyword_id])) {
                $analysis[$item->keyword_id]['total'] += 1;

                if ($item->classification_name === 'Positive') {
                    $analysis[$item->keyword_id]['positive'] += 1;
                }

                if ($item->classification_name === 'Negative') {
                    $analysis[$item->keyword_id]['negative'] += 1;
                }

                if ($item->classification_name === 'Neutral') {
                    $analysis[$item->keyword_id]['neutral'] += 1;
                }
            } else {
                $analysis[$item->keyword_id] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => $item->keyword_name,
                    "total" => 1,
                    "positive" => 0,
                    "negative" => 0,
                    "neutral" => 0,
                ];

                if ($item->classification_name === 'Positive') {
                    $analysis[$item->keyword_id]['positive'] += 1;
                }

                if ($item->classification_name === 'Negative') {
                    $analysis[$item->keyword_id]['negative'] += 1;
                }

                if ($item->classification_name === 'Neutral') {
                    $analysis[$item->keyword_id]['neutral'] += 1;
                }
            }
        }

        return $analysis;
    }

    public function SentimentComparison(Request $request, $only_data = false)
    {

        $analysis_current = $this->factorySentimentComparison($this->start_date, $this->end_date);
        $analysis_previous = $this->factorySentimentComparison($this->start_date_previous, $this->end_date_previous);

        $data = [];

        foreach ($analysis_current as $key => $item) {

            $analysis_previous_key = 0;
            $analysis_previous_positive = 0;
            $analysis_previous_neutral = 0;
            $analysis_previous_negative = 0;

            if (isset($analysis_previous[$key])) {
                $analysis_previous_key = $analysis_previous[$key]['total'];
                $analysis_previous_positive = $analysis_previous[$key]['positive'];
                $analysis_previous_neutral = $analysis_previous[$key]['neutral'];
                $analysis_previous_negative = $analysis_previous[$key]['negative'];
            }

            $data[$key] = [
                "keyword_id" => $item['keyword_id'],
                "keyword_name" => $item['keyword_name'],
                "total" => $item['total'],
                "comparison" => [
                    "value" => $item['total'] - $analysis_previous_key,
                    "percentage" => $analysis_previous_key ? self::point_two_digits((($item['total'] - $analysis_previous_key) / $analysis_previous_key) * 100) : 0,
                    "type" => $item['total'] - $analysis_previous_key > 0 ? "plus" : "minus"
                ],
                "positive" => [
                    "value" => $item['positive'] - $analysis_previous_positive,
                    "percentage" => $analysis_previous_positive ? self::point_two_digits((($item['positive'] - $analysis_previous_positive) / $analysis_previous_positive) * 100) : 0,
                    "type" => $item['positive'] - $analysis_previous_positive > 0 ? "plus" : "minus"
                ],
                "neutral" => [
                    "value" => $item['neutral'] - $analysis_previous_negative,
                    "percentage" => $analysis_previous_negative ? self::point_two_digits((($item['neutral'] - $analysis_previous_negative) / $analysis_previous_negative) * 100) : 0,
                    "type" => $item['neutral'] - $analysis_previous_negative > 0 ? "plus" : "minus"
                ],

                "negative" => [
                    "value" => $item['neutral'] - $analysis_previous_neutral,
                    "percentage" => $analysis_previous_neutral ? self::point_two_digits((($item['neutral'] - $analysis_previous_neutral) / $analysis_previous_neutral) * 100) : 0,
                    "type" => $item['neutral'] - $analysis_previous_neutral > 0 ? "plus" : "minus"
                ],

            ];
        }

        if ($data) {
            $data = array_values($data);
        }

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SummaryBy(Request $request)
    {
        return parent::handleRespond([
            "SummaryScoreAccount" => $this->SummaryScoreAccount($request, true),
            "SummaryScoreChannel" => $this->SummaryScoreChannel($request, true),
            "SummaryKeyword" => $this->SummaryKeyword($request, true),
        ]);
    }

    public function SummaryScoreAccount(Request $request, $only_data = false)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('message_type', 'Post')
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        $items = $raw->get();
        $analysis = [];
        $message_total = 0;
        $max = ['value' => 0, 'hightlightColor' => ''];
        foreach ($items as $item) {
            $message_total += 1;
            if (isset($analysis[$item->author])) {
                $analysis[$item->author]['total'] += 1;
                if ($item->classification_id === 1) {
                    $analysis[$item->author]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->author]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->author]['neutral'] += 1;
                }
            } else {
                $analysis[$item->author] = [
                    'total' => 1,
                    'author' => $item->author,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
                if ($item->classification_id === 1) {
                    $analysis[$item->author]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->author]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->author]['neutral'] += 1;
                }
            }

        }
        $data = [];
        foreach ($analysis as $item) {
            $data[] = [
                "infulencer" => $item['author'],
                "total" => $item['total'],
                "positive" => $item['positive'],
                "neutral" => $item['neutral'],
                "negative" => $item['negative'],
                "sentiment_score" => round((((1 * $item['positive']) + (-1 * $item['negative'])) / ($item['positive'] + $item['negative'] + $item['neutral'])) * 5),

            ];

        }

//        $data[] = [
//            "infulencer" => "User 1",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function SummaryScoreChannel(Request $request, $only_data = false)
    {
        $sources = Sources::all();


        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('message_type', 'Post')
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);


            $sources = Sources::whereIn('name', $this->organization_group->platform)->get();
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        $items = $raw->get();
        $message_total = 0;
        $analysis = [];
        foreach ($items as $item) {
            $message_total += 1;
            if (isset($analysis[$item->source_id])) {
                $analysis[$item->source_id]['total'] += 1;
                if ($item->classification_id === 1) {
                    $analysis[$item->source_id]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->source_id]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->source_id]['neutral'] += 1;
                }
            } else {
                $analysis[$item->source_id] = [
                    'total' => 1,
                    'source_id' => $item->source_id,
                    'source_name' => $item->source_name,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
                if ($item->classification_id === 1) {
                    $analysis[$item->source_id]['positive'] += 1;
                } else if ($item->classification_id === 2) {
                    $analysis[$item->source_id]['negative'] += 1;
                } else if ($item->classification_id === 3) {
                    $analysis[$item->source_id]['neutral'] += 1;
                }
            }

        }

        $data = [];

        foreach ($sources as $source) {
            if (isset($analysis[$source->id])) {
                $data[] = [
                    "channel" => $source->name,
                    "total" => $analysis[$source->id]['total'],
                    "positive" => $analysis[$source->id]['positive'],
                    "neutral" => $analysis[$source->id]['neutral'],
                    "negative" => $analysis[$source->id]['negative'],
                    "sentiment_score" => round((((1 * $analysis[$source->id]['positive']) + (-1 * $analysis[$source->id]['negative'])) / ($analysis[$source->id]['positive'] + $analysis[$source->id]['negative'] + $analysis[$source->id]['neutral'])) * 5),
                ];
            } else {
                $data[] = [
                    "channel" => $source->name,
                    "total" => 0,
                    "positive" => 0,
                    "neutral" => 0,
                    "negative" => 0,
                    "sentiment_score" => 0,
                ];
            }
        }


//        $data[] = [
//            "channel" => "Facebook",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];

        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    public function SummaryKeyword(Request $request, $only_data = false)
    {

        $analysis_current = $this->factorySentimentScore($this->start_date, $this->end_date);

        $data = [];

        foreach ($analysis_current as $keyword_id => $item) {

            $positive = $item['positive'];
            $neutral = $item['neutral'];
            $negative = $item['negative'];
            $total = $item['total'];


            $data[$keyword_id] = [
                'keyword_id' => $keyword_id,
                'keyword_name' => $item['keyword_name'],
                'total_messages' => $total,
                'percentage' => $total / $item['message_total'] * 100,
                "positive" => self::point_two_digits(($positive / $total) * 100),
                "neutral" => self::point_two_digits(($neutral / $total) * 100),
                "negative" => self::point_two_digits(($negative / $total) * 100),

            ];
        }

        if ($data) {
            $data = array_values($data);
        }

//        dd($analysis_current);

//        $data[] = [
//            "keyword" => "Keyword 1",
//            "total_messages" => 212,
//            "percentage" => 61,
//            "positive" => 30,
//            "neutral" => 45,
//            "negative" => 5,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 2",
//            "total_messages" => 75,
//            "percentage" => 22,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];


        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }


    private function overPeriodComparison($current, $previous)
    {

        if ($current - $previous === 0 || $previous === 0) {
            return 0;
        }

        return (float)self::point_two_digits((($current - $previous) / $previous) * 100);
    }

    private function custom_number_format($n, $precision = 3)
    {
        if ($n < 1000000) {
            // Anything less than a million
            $n_format = number_format($n);
        } else if ($n < 1000000000) {
            // Anything less than a billion
            $n_format = number_format($n / 1000000, $precision) . 'M';
        } else {
            // At least a billion
            $n_format = number_format($n / 1000000000, $precision) . 'B';
        }

        return $n_format;
    }
}
