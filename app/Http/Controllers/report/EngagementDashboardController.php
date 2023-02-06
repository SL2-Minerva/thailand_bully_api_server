<?php

namespace App\Http\Controllers\report;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\DailyMessage;
use App\Models\Sources;
use App\Models\Classification;

class EngagementDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;
    private $keyword_id;
    private $source_id;

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

    }

    public function EngagementTrans(Request $request)
    {

        $data = [
            "engagement" => $this->engagement($this->start_date, $this->end_date),
            "prcentage_of_engagement_current" => $this->percentageOfEngagement($this->start_date, $this->end_date),
            "prcentage_of_engagement_previous" => $this->percentageOfEngagement($this->start_date_previous, $this->end_date_previous)
        ];

        return parent::handleRespond($data);
    }


    public function EngagementBy(Request $request)
    {
        return parent::handleRespond([
            "EngagementByDay" => $this->EngagementByDay($request, true),
            "EngagementByTime" => $this->EngagementByTime($request, true),
            "EngagementByDevice" => $this->EngagementByDevice($request, true),
            "EngagementByAccount" => $this->EngagementByAccount($request, true),
            "EngagementChannel" => $this->EngagementChannel($request, true),
            "keywordByEngagementType" => $this->keywordByEngagementType($request, true),
        ]);
    }

    public function EngagementByDay(Request $request, $only_data = false)
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

        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw_current->get();

        foreach ($items as $item) {
            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;

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

    public function EngagementByTime(Request $request, $only_data = false)
    {

        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];


        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw_current->get();

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

            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;

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

    public function EngagementByDevice(Request $request, $only_data = false)
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw_current->get();

        foreach ($items as $item) {
            $index_label = 0;

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp') {
                $index_label = 2;
            }

            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;

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

    public function EngagementByAccount(Request $request, $only_data = false)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->start_date])
            ->where('reference_message_id', '')
            ->orWhere('reference_message_id', null)
            ->whereIn('classification_type_id', [1]);

        $raw_child = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->where('reference_message_id', '!=', null)
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->whereIn('source_id', $this->source_id);
            $raw_child->whereIn('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_child->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $items_child = $raw_child->get();
        $message_total = 0;
        $analysis = [];

        foreach ($items as $item) {
            if (isset($analysis[$item->message_id])) {
                $analysis[$item->message_id]['follows'] += 1;
            } else {
                $analysis[$item->message_id]['follows'] = 0;
                $analysis[$item->message_id]['Infulencer'] = 0;
                $analysis[$item->message_id]['keyword_id'] = $item->keyword_id;
                $analysis[$item->message_id]['keyword_name'] = $item->keyword_name;
                $analysis[$item->message_id]['campaign_id'] = $item->campaign_id;
                $analysis[$item->message_id]['campaign_name'] = $item->campaign_name;
            }


            $message_total += 1;
        }

        // todo check
        foreach ($items_child as $child) {
            if (isset($analysis[$child->reference_message_id])) {
                $analysis[$child->reference_message_id]['follows'] += 1;
            }
        }

        foreach ($analysis as $item) {
            foreach ($item as $key => $value) {

                if ($key !== 'follows') {
                    $index_label = array_search($key, $data['labels']);

                    if ($index_label !== -1) {
                        if (isset($data['value'][$item['keyword_id']])) {


                            if ($key === 'Infulencer' || $key === 'Follower') {
                                $data['value'][$item['keyword_id']]['data'][$index_label] += 1;
                            }

//                            $data[$item['keyword_id']]['data'][$index_label] += $value;
                        } else {
                            $data['value'][$item['keyword_id']] = [
                                'id' => $item['keyword_id'],
                                'keyword_name' => $item['keyword_name'],
                                'campaign_id' => $item['campaign_id'],
                                'campaign_name' => $item['campaign_name'],
                                'data' => [0, 0]
                            ];
                            $data['value'][$item['keyword_id']]['data'][$index_label] = 1;
                        }

                    }
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

    public function EngagementChannel(Request $request, $only_data = false)
    {
        $data = parent::listSource();
        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw_current->get();

        foreach ($items as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'name' => $item->keyword_name,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][$i] = 0;
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
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

    public function keywordByEngagementType(Request $request, $only_data = false)
    {

        $data['labels'] = ["Share of Voice", "Comments", "Reaction"];

        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw_current->get();

        foreach ($items as $item) {
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][0] += $item->number_of_shares;
                $data['value'][$item->keyword_id]['data'][1] += $item->number_of_comments;
                $data['value'][$item->keyword_id]['data'][2] += $item->number_of_reactions;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'name' => $item->keyword_name,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][$i] = 0;
                }

                $data['value'][$item->keyword_id]['data'][0] += $item->number_of_shares;
                $data['value'][$item->keyword_id]['data'][1] += $item->number_of_comments;
                $data['value'][$item->keyword_id]['data'][2] += $item->number_of_reactions;
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


    public function EngagementTypeBy(Request $request)
    {
        return parent::handleRespond([
            "EngagementType" => $this->EngagementType($request, true),
            "EngagementByDayKey" => $this->EngagementByDayKey($request, true),
            "EngagementByTimeKey" => $this->EngagementByTimeKey($request, true),
            "EngagementByDeviceKey" => $this->EngagementByDeviceKey($request, true),
            "EngagementByAccountKey" => $this->EngagementByAccountKey($request, true),
            "EngagementChannelKey" => $this->EngagementChannelKey($request, true),
        ]);
    }


    //todo maybe percentage is wrong
    public function EngagementType(Request $request, $only_data = false)
    {

        $table = 'message_result_full_data';

        $data = null;

        $raw = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        $raw_previous = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
            $raw_previous->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $items_previous = $raw_previous->get();


        // find percentage of engagement

        $total_engaement = 0;
        $total_engaement_previous = 0;
        $percentages_share_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_comment_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_reactions_current = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y'),
        ];

        $percentages_share_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        $percentages_comment_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        $percentages_reactions_previous = [
            'total' => 0,
            'date' => Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y'),
        ];

        foreach ($items_previous as $items_previou) {
            $total_engaement_previous += $items_previou->number_of_shares + $items_previou->number_of_comments + $items_previou->number_of_reactions;
            $percentages_share_previous['total'] += $items_previou->number_of_shares;
            $percentages_comment_previous['total'] += $items_previou->number_of_comments;
            $percentages_reactions_previous['total'] += $items_previou->number_of_reactions;
        }

        foreach ($items as $item) {
            $total_engaement += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
            $percentages_share_current['total'] += $item->number_of_shares;
            $percentages_comment_current['total'] += $item->number_of_comments;
            $percentages_reactions_current['total'] += $item->number_of_reactions;
        }


        foreach ($items as $item) {

            $date_m = \Illuminate\Support\Carbon::parse($item->date_m)->format('Y-m-d');

            $shared = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_shares,
            ];

            $comment = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_comments,
            ];

            $reactions = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $date_m,
                "total_at_date" => $item->number_of_reactions,
            ];


            if (isset($data['engagement'][1])) {

                $data['engagement'][1]['value'][] = $shared;
                $data['engagement'][2]['value'][] = $comment;
                $data['engagement'][3]['value'][] = $reactions;
            } else {
                $data['engagement'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['engagement'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['engagement'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                // engagement

                $data['engagement'][1]['value'][] = $shared;
                $data['engagement'][2]['value'][] = $comment;
                $data['engagement'][3]['value'][] = $reactions;

                // prcentage_of_engagement_current

                $data['prcentage_of_engagement_current'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

//                // prcentage_of_engagement_previous
                $data['prcentage_of_engagement_previous'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

            }

        }


        $data['prcentage_of_engagement_current'][1]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_share_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
        ];

        $data['prcentage_of_engagement_current'][2]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_comment_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
        ];
        $data['prcentage_of_engagement_current'][3]['value'] = [
            "percentage" => $total_engaement ? self::point_two_digits(($percentages_reactions_current['total'] / $total_engaement) * 100) : 0,
            "date" => $percentages_share_current['date'],
        ];


        $data['prcentage_of_engagement_previous'][1]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_share_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
        ];

        $data['prcentage_of_engagement_previous'][2]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_comment_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
        ];
        $data['prcentage_of_engagement_previous'][3]['value'] = [
            "percentage" => $total_engaement_previous ? self::point_two_digits(($percentages_reactions_previous['total'] / $total_engaement_previous) * 100) : 0,
            "date" => $percentages_share_previous['date'],
        ];


        $data['engagement'] = isset($data['engagement']) ? array_values($data['engagement']) : null;
        $data['prcentage_of_engagement_previous'] = isset($data['prcentage_of_engagement_previous']) ? array_values($data['prcentage_of_engagement_previous']) : null;
        $data['prcentage_of_engagement_current'] = isset($data['prcentage_of_engagement_current']) ? array_values($data['prcentage_of_engagement_current']) : null;
//

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementByDayKey(Request $request, $only_data = false)
    {

        $table = 'message_result_full_data';

        $data = null;
        $raw = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);

        }

        $items = $raw->get();
        $total_engaement = 0;

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        foreach ($items as $item) {
            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($data['value'][0])) {

                $data['value'][0]['data'][$index_label] += $item->number_of_shares;
                $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                $data['value'][2]['data'][$index_label] += $item->number_of_reactions;

            } else {
                $data['value'][0] = [
                    'id' => 1,
                    'keyword_name' => 'Share',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][1] = [
                    'id' => 2,
                    'keyword_name' => 'Comment',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];


                $data['value'][2] = [
                    'id' => 3,
                    'keyword_name' => 'reactions',
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][0]['data'][$index_label] += $item->number_of_shares;
                $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                $data['value'][2]['data'][$index_label] += $item->number_of_reactions;
            }
        }


//        $table =  'total_engagement_of_source_d_m_y_h_i_s';
//        $period = $request->period;
//
//        $start_date = $this->date_carbon($request->start_date) ?? null;
//        $end_date = $this->date_carbon($request->end_date) ?? null;
//
//        $campaign_id = $request->campaign_id;
//


        if ($only_data) {
            return $data;
        }
        return parent::handleRespond($data);
    }

    public function EngagementByTimeKey(Request $request, $only_data = false)
    {


        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $table = 'message_result_full_data';

        $raw = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
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


            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                $data['value'][2]['data'][$index_label] += $item->number_of_shares;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;


            } else {

                $data['value'][1] = [
                    "id" => 1,
                    "keyword_name" => 'Share',
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][3] = [
                    "id" => 3,
                    "keyword_name" => 'Reactions',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];
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

    public function EngagementByDeviceKey(Request $request, $only_data = false)
    {


        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $table = 'message_result_full_data';

        $raw = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);

        }

        $items = $raw->get();


        foreach ($items as $item) {
            $index_label = 0;

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp') {
                $index_label = 2;
            }

            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                $data['value'][2]['data'][$index_label] += $item->number_of_shares;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;


            } else {

                $data['value'][1] = [
                    "id" => 1,
                    "keyword_name" => 'Share',
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];

                $data['value'][2] = [
                    "id" => 2,
                    "keyword_name" => 'Comment',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];

                $data['value'][3] = [
                    "id" => 3,
                    "keyword_name" => 'Reactions',
                    "campaign_id" => $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];
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

    public function EngagementByAccountKey(Request $request, $only_data = false)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table_root = 'sna_root_node';
        $table_child = 'sna_child_node';

        $infulencer_root = DB::table($table_root)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if ($this->source_id) {
            $infulencer_root->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $infulencer_root->whereIn('keyword_id', $this->keyword_id);
        }

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if ($infulencer) {

                if (isset($data['value'][1]['data'][0])) {
                    $data['value'][1]['data'][0] += $infulencer->number_of_shares;
                    $data['value'][2]['data'][0] += $infulencer->number_of_comments;
                    $data['value'][3]['data'][0] += $infulencer->number_of_reactions;
                } else {
                    $data['value'][1] = [
                        'id' => 1,
                        "keyword_name" => "Share",
                        "data" => [$infulencer->number_of_shares, 0]
                    ];

                    $data['value'][2] = [
                        'id' => 2,
                        "keyword_name" => "Comment",
                        "data" => [$infulencer->number_of_comments, 0]
                    ];

                    $data['value'][3] = [
                        'id' => 3,
                        "keyword_name" => "Reaction",
                        "data" => [$infulencer->number_of_reactions, 0]
                    ];

                }
            }

        }

        $follower_raw = DB::table($table_child)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if ($this->source_id) {
            $follower_raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $follower_raw->whereIn('keyword_id', $this->keyword_id);
        }


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][1]['data'][0])) {
                $data['value'][1]['data'][1] += $follower->number_of_shares;
                $data['value'][2]['data'][1] += $follower->number_of_comments;
                $data['value'][3]['data'][1] += $follower->number_of_reactions;
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

    public function EngagementChannelKey(Request $request, $only_data = false)
    {

        $data = $this->listSource();

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

        foreach ($items as $item) {
            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][1])) {
                $data['value'][1]['data'][$index_label] += $item->number_of_shares;
                $data['value'][2]['data'][$index_label] += $item->number_of_comments;
                $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
            } else {
                $data['value'][1] = [
                    'id' => 1,
                    'name' => "Share",
                ];

                $data['value'][2] = [
                    'id' => 2,
                    'name' => "Comment",
                ];

                $data['value'][3] = [
                    'id' => 3,
                    'name' => "Reaction",
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {

                    $data['value'][1]['data'][$i] = 0;
                    $data['value'][2]['data'][$i] = 0;
                    $data['value'][3]['data'][$i] = 0;
                }

                $data['value'][1]['data'][$index_label] = $item->number_of_shares;
                $data['value'][2]['data'][$index_label] = $item->number_of_comments;
                $data['value'][3]['data'][$index_label] = $item->number_of_reactions;
            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        if ($only_data) {
            return $data;
        }
    }


    public function EngagementComparisonBy(Request $request)
    {
        return parent::handleRespond([
            "EngagementComparison" => $this->EngagementComparison($request, true),
            "EngagementPeriodPlarform" => $this->EngagementPeriodPlarform($request, true),
            "EngagementPeriodSentiment" => $this->EngagementPeriodSentiment($request, true),
            "EngagementTypeComparison" => $this->EngagementTypeComparison($request, true),
            "EngagementActionComparison" => $this->EngagementActionComparison($request, true),
            "EngagementByInfulencer" => $this->EngagementByInfulencer($request, true),
        ]);
    }


    public function EngagementComparison(Request $request, $only_data = false)
    {

        $data = null;
        $table = 'message_result_full_data';
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])->whereIn('classification_type_id', [1]);
        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])->whereIn('classification_type_id', [1]);


        $totalEngagement_current = $raw_current->sum(DB::raw('number_of_shares + number_of_comments + number_of_reactions'));
        $totalEngagement_previous = $raw_previous->sum(DB::raw('number_of_shares + number_of_comments + number_of_reactions'));

        $total_share_current = $raw_current->sum('number_of_shares');
        $total_share_previous = $raw_previous->sum('number_of_shares');

        $total_comment_current = $raw_current->sum('number_of_comments');
        $total_comment_previous = $raw_previous->sum('number_of_comments');

        $total_reactions_current = $raw_current->sum('number_of_reactions');
        $total_reactions_previous = $raw_previous->sum('number_of_reactions');


        $data['totalEngagement'] = [
            "totalValue" => $this->custom_number_format((int)$totalEngagement_current),
            "comparison" => (float)parent::point_two_digits($totalEngagement_current - $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous) : 0),
            "type" => $totalEngagement_current - $totalEngagement_previous > 0 ? "plus" : "minus",
        ];

        $data['share'] = [
            "totalValue" => $this->custom_number_format((int)$total_share_current),
            "comparison" => (float)parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous * 100) : 0),
            "type" => $total_share_current - $total_share_previous > 0 ? "plus" : "minus",
        ];

        $data['comment'] = [
            "totalValue" => $this->custom_number_format((int)$total_comment_current),
            "comparison" => (float)parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0),
            "type" => $total_comment_current - $total_comment_previous > 0 ? "plus" : "minus",
        ];

        $data['reaction'] = [
            "totalValue" => $this->custom_number_format((int)$total_reactions_current),
            "comparison" => $total_reactions_previous ? (float)parent::point_two_digits(($total_reactions_current - $total_reactions_previous) / $total_reactions_previous) : 0,
            "type" => $total_reactions_current - $total_reactions_previous > 0 ? "plus" : "minus",
        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementPeriodPlarform(Request $request, $only_data = false)
    {

        $data = $this->listSource();
        $table = 'message_result_full_data';

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
            $raw_previous->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);

        }

        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();

        $current_share = [];
        $previous_share = [];

        $current_comment = [];
        $previous_comment = [];

        $current_reaction = [];
        $previous_reaction = [];

        $debug = null;

        foreach ($items_current as $item) {
            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][2])) {
                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions);;
                $current_share[$index_label] += $item->number_of_shares;
                $current_comment[$index_label] += $item->number_of_comments;
                $current_reaction[$index_label] += $item->number_of_reactions;

            } else {
                $data['value'][1] = [
                    'id' => 1,
                    'keyword_name' => "Previous",
                ];

                $data['value'][2] = [
                    'id' => 2,
                    'keyword_name' => "Current",
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][1]['data'][$i] = 0;
                    $data['value'][2]['data'][$i] = 0;

                    $data['share'][$i] = 0;
                    $data['comment'][$i] = 0;
                    $data['reaction'][$i] = 0;

                    $current_share[$i] = 0;
                    $current_share[$index_label] = $item->number_of_shares;
                    $current_comment[$i] = 0;
                    $current_comment[$index_label] = $item->number_of_comments;
                    $current_reaction[$i] = 0;
                    $current_reaction[$index_label] = $item->number_of_reactions;

                    $previous_share[$i] = 0;
                    $previous_comment[$i] = 0;
                    $previous_reaction[$i] = 0;
                }

                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions);
            }
        }


        foreach ($items_previous as $item) {

            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][1])) {
                $data['value'][1]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions);
            } else {

            }

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i <= count($data['labels']); $i++) {

            if (isset($data['share'][$i])) {
                $data['share'][$i] = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
                $data['comment'][$i] = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
                $data['reaction'][$i] = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
            } else {
//                $data['share'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
//
//                $data['comment'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
//
//                $data['reaction'][$i] = [
//                    "totalValue" => 0,
//                    "comparison" => 0,
//                    "type" => "minus",
//                ];
            }

        }

//        $data['labels'] = [
//            "Facebook",
//            "Twitter",
//            "Instagram",
//            "Youtube",
//            "Pantip",
//        ];

//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Previous",
//            "data" => [
//                19, 38, 47, 16, 30
//            ],
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Current",
//            "data" => [
//                15, 45, 65, 23, 53,
//            ],
//        ];

//        $data['share'] = [
//            "-30%", "-30%", "-30%", "-30%", "-30%",
//        ];
//
//        $data['comment'] = [
//            "-23%", "-23%", "-23%", "-23%", "-23%",
//        ];
//
//        $data['reaction'] = [
//            "-56%", "-56%", "-56%", "-56%", "-56%",
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementPeriodSentiment(Request $request, $only_data = false)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];


        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
            $raw_previous->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);

        }

        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();
        $current_share = [];
        $previous_share = [];
        $current_comment = [];
        $previous_comment = [];
        $current_reaction = [];
        $previous_reaction = [];

        $debug = null;
        $data['value'][1] = [
            'id' => 1,
            'keyword_name' => "Previous",
        ];

        $data['value'][2] = [
            'id' => 2,
            'keyword_name' => "Current",
        ];

        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['value'][1]['data'][$i] = 0;
            $data['value'][2]['data'][$i] = 0;

            $data['share'][$i] = 0;
            $data['comment'][$i] = 0;
            $data['reaction'][$i] = 0;

            $current_share[$i] = 0;
            $current_comment[$i] = 0;
            $current_reaction[$i] = 0;


            $previous_share[$i] = 0;
            $previous_comment[$i] = 0;
            $previous_reaction[$i] = 0;
        }

        foreach ($items_current as $item) {
            $index_label = array_search($item->classification_name, $data['labels']);

            if (isset($data['value'][2])) {
                $data['value'][2]['data'][$index_label] += 1;
                $current_share[$index_label] += $item->number_of_shares;
                $current_comment[$index_label] += $item->number_of_comments;
                $current_reaction[$index_label] += $item->number_of_reactions;

            } else {


//                for ($i = 0; $i < count($data['labels']); $i++) {
//                    $data['value'][1]['data'][$i] = 0;
//                    $data['value'][2]['data'][$i] = 0;
//
//                    $data['share'][$i] = 0;
//                    $data['comment'][$i] = 0;
//                    $data['reaction'][$i] = 0;
//
//                    $current_share[$i] = 0;
                $current_share[$index_label] = $item->number_of_shares;
//                    $current_comment[$i] = 0;
                $current_comment[$index_label] = $item->number_of_comments;
//                    $current_reaction[$i] = 0;
                $current_reaction[$index_label] = $item->number_of_reactions;
//
                $previous_share[$i] = 0;
                $previous_comment[$i] = 0;
                $previous_reaction[$i] = 0;
//                }

                $data['value'][2]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions);
            }
        }


        foreach ($items_previous as $item) {

            $index_label = array_search($item->classification_name, $data['labels']);

            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += ($item->number_of_shares + $item->number_of_comments + $item->number_of_reactions);
            }

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['share'][$i] = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
            $data['comment'][$i] = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
            $data['reaction'][$i] = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Previous",
//            "data" => [
//                47, 16, 30,
//            ],
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Current",
//            "data" => [
//                12, 16, 78,
//            ],
//        ];
//
//        $data['share'] = [
//            "-30%", "-30%", "-30%", "-30%", "-30%",
//        ];
//
//        $data['comment'] = [
//            "-23%", "-23%", "-23%", "-23%", "-23%",
//        ];
//
//        $data['reaction'] = [
//            "-56%", "-56%", "-56%", "-56%", "-56%",
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementTypeComparison(Request $request, $only_data = false)
    {
        $data = [];

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);
            $raw_previous->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);

        }

        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();

        $current = null;
        $previous = null;

        foreach ($items_current as $item) {
            if (isset($current[$item->keyword_id]) && $current[$item->keyword_id]) {
                $current[$item->keyword_id]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $current[$item->keyword_id]['share'] += $item->number_of_shares;
                $current[$item->keyword_id]['comment'] += $item->number_of_comments;
                $current[$item->keyword_id]['reaction'] += $item->number_of_reactions;
            } else {
                $current[$item->keyword_id]['keyword_name'] = $item->keyword_name;
                $current[$item->keyword_id]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $current[$item->keyword_id]['share'] = $item->number_of_shares;
                $current[$item->keyword_id]['comment'] = $item->number_of_comments;
                $current[$item->keyword_id]['reaction'] = $item->number_of_reactions;
            }
        }

        foreach ($items_previous as $item) {
            if (isset($previous[$item->keyword_id]) && $previous[$item->keyword_id]) {
                $previous[$item->keyword_id]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $previous[$item->keyword_id]['share'] += $item->number_of_shares;
                $previous[$item->keyword_id]['comment'] += $item->number_of_comments;
                $previous[$item->keyword_id]['reaction'] += $item->number_of_reactions;
            } else {
                $previous[$item->keyword_id]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $previous[$item->keyword_id]['share'] = $item->number_of_shares;
                $previous[$item->keyword_id]['comment'] = $item->number_of_comments;
                $previous[$item->keyword_id]['reaction'] = $item->number_of_reactions;
            }
        }

//        dd($current, $previous);

        if ($current) {

            foreach ($current as $key => $item) {

                $data[] = [
                    'keyword_id' => $key,
                    'keyword_name' => $item['keyword_name'],
                    'total' => [
                        "value" => $item['total'] - $previous[$key]['total'],
                        "percentage" => $this->overPeriodComparison($item['total'], $previous[$key]['total']),
                        "type" => $item['total'] - $previous[$key]['total'] > 0 ? "plus" : "minus",
                    ],
                    'share' => [
                        "value" => $item['share'] - $previous[$key]['share'],
                        "percentage" => $this->overPeriodComparison($item['share'], $previous[$key]['share']),
                        "type" => $item['share'] - $previous[$key]['share'] > 0 ? "plus" : "minus",
                    ],
                    'comment' => [
                        "value" => $item['comment'] - $previous[$key]['comment'],
                        "percentage" => $this->overPeriodComparison($item['comment'], $previous[$key]['comment']),
                        "type" => $item['comment'] - $previous[$key]['comment'] > 0 ? "plus" : "minus",
                    ],
                    'reaction' => [
                        "value" => $item['reaction'] - $previous[$key]['reaction'],
                        "percentage" => $this->overPeriodComparison($item['reaction'], $previous[$key]['reaction']),
                        "type" => $item['reaction'] - $previous[$key]['reaction'] > 0 ? "plus" : "minus",
                    ],
                ];
            }
        }


//        $data = [
//            [
//                "keyword_name" => "keyword 1",
//                "total" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 2",
//                "total" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 3",
//                "total" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 4",
//                "total" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 5",
//                "total" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ]
//        ];

        if ($only_data) {
            return $data;
        }

        return parent::handleRespond($data);
    }

    public function EngagementActionComparison(Request $request, $only_data = false)
    {
        $engagement_action_raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->source_id) {
            $engagement_action_raw->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $engagement_action_raw->whereIn('keyword_id', $this->keyword_id);
        }

        $engagement_actions = $engagement_action_raw->get();


        $percentages = null;
        foreach ($engagement_actions as $engagement_action) {
            if (isset($percentages[$engagement_action->keyword_id])) {
                $engagements = ($engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions);
                $percentages[$engagement_action->keyword_id]['total'] += $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_shares / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['comment'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_comments / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['reaction'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_reactions / (float)$engagements) * 100) : 0;
            } else {
                $percentages[$engagement_action->keyword_id] = [
                    'share' => 0,
                    'share_r' => 0,
                    'comment' => 0,
                    'comment_r' => 0,
                    'reaction' => 0,
                    'reaction_r' => 0,
                    'total' => 0,
                    "keyword_id" => $engagement_action->keyword_id,
                    "keyword_name" => $engagement_action->keyword_name,
                    "campaign_id" => $engagement_action->campaign_id,
                    "campaign_name" => $engagement_action->campaign_name,
                ];

                $engagements = ($engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions);
                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_shares / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['comment'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_comments / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['reaction'] = $engagements !== 0 ? (float)self::point_two_digits(($engagement_action->number_of_reactions / (float)$engagements) * 100) : 0;
                $percentages[$engagement_action->keyword_id]['total'] += $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions;
            }
        }

        if ($percentages) {
            $percentages = array_values($percentages);

        }


        if ($only_data) {
            return $percentages;
        }
        return parent::handleRespond($percentages);
    }

    //todo mamybe is wrong
    public function EngagementByInfulencer(Request $request, $only_data = false)
    {
        $data = null;
        $page = $request->page ?? null;
        $limit = $request->limit ?? 5;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start - 1;

        $raw_current = DB::table('sna_root_node')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])->groupBy('author');

        $raw_previous = DB::table('sna_root_node')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])->groupBy('author');

        if ($request->enable_page) {
            $raw_current->offset($start)->limit($limit)->orderBy('created', 'desc');
            $raw_previous->offset($start)->limit($limit)->orderBy('created', 'desc');
        }
//


        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();


        $current = null;
        $previous = null;

        foreach ($items_current as $item) {

            if ($item) {
                if (isset($current[$item->author]) && $current[$item->author]) {
                    $current[$item->author]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                    $current[$item->author]['share'] += $item->number_of_shares;
                    $current[$item->author]['comment'] += $item->number_of_comments;
                    $current[$item->author]['reaction'] += $item->number_of_reactions;
                } else {
                    $current[$item->author]['message_id'] = $item->message_id;
                    $current[$item->author]['infulencer'] = $item->author;
                    $current[$item->author]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                    $current[$item->author]['share'] = $item->number_of_shares;
                    $current[$item->author]['comment'] = $item->number_of_comments;
                    $current[$item->author]['reaction'] = $item->number_of_reactions;
                }
            }

        }

        foreach ($items_previous as $item) {
            if (isset($previous[$item->author]) && $previous[$item->author]) {
                $previous[$item->author]['total'] += $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $previous[$item->author]['share'] += $item->number_of_shares;
                $previous[$item->author]['comment'] += $item->number_of_comments;
                $previous[$item->author]['reaction'] += $item->number_of_reactions;
            } else {
                $previous[$item->author]['total'] = $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions;
                $previous[$item->author]['share'] = $item->number_of_shares;
                $previous[$item->author]['comment'] = $item->number_of_comments;
                $previous[$item->author]['reaction'] = $item->number_of_reactions;
            }
        }

//        dd($current, $previous);

        if ($current) {

            foreach ($current as $key => $item) {
                $previous_total = isset($previous[$key]['total']) ? $previous[$key]['total'] : 0;
                $data[] = [
                    'message_id' => $item['message_id'],
                    'infulencer' => $item['infulencer'],
                    "total" => $item['total'],
                    "share" => $item['share'],
                    "comment" => $item['comment'],
                    "reaction" => $item['reaction'],
                    "period_over_preiod" => $item['total'] - $previous_total,
                    "period_over_period_percentage" => $this->overPeriodComparison($item['total'], $previous_total),

                ];
            }
        }


        switch ($request->select) {
            case "top10":
                $data = array_slice($data, 0, 10);
                break;
            case "top20":
                $data = array_slice($data, 0, 20);
                break;
            case "top50":
                $data = array_slice($data, 0, 50);
                break;
            case "top100":
                $data = array_slice($data, 0, 100);
                break;
            default:
                $data = $data ? $data : null;
        }

        if ($only_data) {
            return $data;
        }


        return parent::handleRespond($data);
    }

    private function percentageOfEngagement($start_date, $end_date)
    {

        $message_keyword = [];
        $message_total = 0;
        $data = null;
        $table = 'message_result_full_data';

        $raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        foreach ($items as $object) {
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']]['total'] += 1;
            } else {
                $message_keyword[$item['keyword_id']]['total'] = 1;
                $message_keyword[$item['keyword_id']]['keyword_name'] = $item['keyword_name'];
            }

            $message_total += 1;
        }

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if (isset($value['total']) && $value['total'] && $message_total) {
                $percentage = self::point_two_digits(($value['total'] / $message_total) * 100);
            }
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
                'total' => $message_total,
                "keyword_name" => $value['keyword_name'],
                "name" => $value['keyword_name'],
            ];
        }

        if ($data) {
            $data = array_values($data);

        }

        return $data;

    }

    private function engagement($start_date, $end_date)
    {
        $data = null;
        $table = 'message_result_full_data';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->source_id) {
            $raw_current->where('source_id', $this->source_id);

        }

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

        $items_current = $raw_current->get();

        foreach ($items_current as $item) {
            $keyword_id = $item->keyword_id;
            $date_m = \Illuminate\Support\Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$keyword_id])) {
                if (isset($data[$keyword_id]['value'][$date_m])) {
                    $data[$keyword_id]['value'][$date_m]['total_at_date'] += 1;

                } else {
                    $data[$keyword_id]['value'][$date_m] = [
                        'date' => $date_m,
                        'total_at_date' => 1
                    ];
                }
            } else {
                $nestData = [
                    'date_m' => $date_m,
                    'total_at_date' => 1
                ];
                $data[$keyword_id] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => $item->keyword_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];

                $data[$keyword_id]['value'][$date_m] = $nestData;
            }
        }

        if ($data) {

            foreach ($data as $k => $value) {
                if ($value['value']) {
                    $data[$k]['value'] = array_values($value['value']);
                }
            }
        }

        if ($data) {
            $data = array_values($data);
        }
        return $data;
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
