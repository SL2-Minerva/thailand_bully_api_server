<?php

namespace App\Http\Controllers\report;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class EngagementDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;

    public function __construct(Request $request)
    {

        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);

//        parent::__construct($request);
    }

    public function EngagementTrans(Request $request)
    {
        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $period = $request->period;
        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);
        $data = [
            "engagement" => $this->engagement($request->campaign_id, $start_date, $end_date, $request->source),
            "prcentage_of_engagement_current" => $this->percentageOfEngagement($request->campaign_id, $start_date, $end_date, $request->keyword_id ?? null, $request->source ?? null),
            "prcentage_of_engagement_previous" => $this->percentageOfEngagement($request->campaign_id, $start_date_previous, $end_date_previous, $request->keyword_id ?? null, $request->source ?? null),
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByDay(Request $request)
    {
        $table =  'total_engagement_of_source_d_m_y_h_i_s';
//        $period = $request->period;
//
//        $start_date = $this->date_carbon($request->start_date) ?? null;
//        $end_date = $this->date_carbon($request->end_date) ?? null;
//
//        $this->campaign_id = $request->campaign_id;
        $data = parent::listDataByType('dayname', $table, $this->campaign_id, $this->start_date, $this->end_date );

        return parent::handleRespond($data);
    }

    public function EngagementByTime(Request $request)
    {

        $table =  'total_engagement_of_source_d_m_y_h_i_s';
        $data = parent::listDataByType('time', $table, $this->campaign_id, $this->start_date, $this->end_date );

        return parent::handleRespond($data);

    }

    public function EngagementByDevice(Request $request)
    {
        $table =  'total_engagement_of_source_d_m_y_h_i_s';
        $period = $request->period;

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $campaign_id = $request->campaign_id;

        $data = parent::listDataByType('device', $table, $campaign_id, $start_date, $end_date );

        return parent::handleRespond($data);
    }

    public function EngagementByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table = 'sna_root_node';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->keyword_id]['data'][0])) {
                $data['value'][$infulencer->keyword_id]['data'][0] += $infulencer->engagement;
            } else {
                $data['value'][$infulencer->keyword_id]['id'] = $infulencer->keyword_id;
                $data['value'][$infulencer->keyword_id]['keyword_name'] = $infulencer->keyword_name;
                $data['value'][$infulencer->keyword_id]['data'][0] = $infulencer->engagement ?? 0;

            }

        }

        $table = 'sna_child_node';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->keyword_id]['data'][1])) {
                $data['value'][$follower->keyword_id]['data'][1] += $follower->engagement ?? 0;
            } else {
                $data['value'][$follower->keyword_id]['id'] = $follower->keyword_id;
                $data['value'][$follower->keyword_id]['keyword_name'] = $follower->keyword_name;
                $data['value'][$follower->keyword_id]['data'][1] = $follower->engagement ?? 0;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }



        return parent::handleRespond($data);
    }

    public function EngagementChannel(Request $request)
    {

        $table =  'total_engagement_of_source_d_m_y_h_i_s';
        $period = $request->period;

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $campaign_id = $request->campaign_id;

        $data = parent::listDataByType('channel', $table, $campaign_id, $start_date, $end_date );

        return parent::handleRespond($data);
    }


    //todo maybe percentage is wrong
    public function EngagementType(Request $request)
    {

        $table = 'total_engagement_of_source_d_m_y_h_i_s';
        $source_id = $request->source;
        $data = null;

        $items = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }

        if ($source_id && $source_id !== 'all') {
            $items->where('source_id', $source_id);
        }



        $percentages_share_current = parent::findPercentage($items->get(), 'number_of_shares', $this->start_date, $this->end_date);
        $percentages_comment_current = parent::findPercentage($items->get(), 'number_of_comments', $this->start_date, $this->end_date);
        $percentages_reaction_current = parent::findPercentage($items->get(), 'number_of_reactions', $this->start_date, $this->end_date);


        $percentages_share_previous = parent::findPercentage($items->get(), 'number_of_shares', $this->start_date_previous, $this->end_date_previous);
        $percentages_comment_previous = parent::findPercentage($items->get(), 'number_of_comments', $this->start_date_previous, $this->end_date_previous);
        $percentages_reaction_previous = parent::findPercentage($items->get(), 'number_of_reactions', $this->start_date_previous, $this->end_date_previous);

        // find percentage of engagement

        foreach ($items->get() as $item) {

            $shared = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $item->date_m,
                "total_at_date" => $item->number_of_shares,
            ];

            $comment = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $item->date_m,
                "total_at_date" => $item->number_of_comments,
            ];

            $reactions = [
                "source_id" => $item->source_id,
                "source_name" => $item->source_name,
                "date_m" => $item->date_m,
                "total_at_date" => $item->number_of_reactions,
            ];


            if (isset($data['engagement'][1])) {


                $data['engagement'][1]['value'][] = $shared;
                $data['engagement'][2]['value'][] = $comment;
                $data['engagement'][3]['value'][] = $reactions;
            }

            else {
                $data['engagement'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    "campaign_id" =>  $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['engagement'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['engagement'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" =>  $this->campaign_id,
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
                    "campaign_id" =>  $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_current'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

//                // prcentage_of_engagement_previous
                $data['prcentage_of_engagement_previous'][1] = [
                    "id" => 1,
                    "name" => 'Share',
                    "campaign_id" =>  $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][2] = [
                    "id" => 2,
                    "name" => 'Comment',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];

                $data['prcentage_of_engagement_previous'][3] = [
                    "id" => 3,
                    "name" => 'Reactions',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "value" => []
                ];



                $data['prcentage_of_engagement_current'][1]['value'] = $percentages_share_current[$item->keyword_id]['value'];
                $data['prcentage_of_engagement_current'][2]['value'] = $percentages_reaction_current[$item->keyword_id]['value'];
                $data['prcentage_of_engagement_current'][3]['value'] = $percentages_comment_current[$item->keyword_id]['value'];

                $data['prcentage_of_engagement_previous'][1]['value'] = $percentages_share_previous[$item->keyword_id]['value'];
                $data['prcentage_of_engagement_previous'][2]['value'] = $percentages_reaction_previous[$item->keyword_id]['value'];
                $data['prcentage_of_engagement_previous'][3]['value'] = $percentages_comment_previous[$item->keyword_id]['value'];

            }

        }

        $data['engagement'] = isset($data['engagement']) ? array_values($data['engagement']) : null;
        $data['prcentage_of_engagement_previous'] = isset($data['prcentage_of_engagement_previous'] ) ? array_values($data['prcentage_of_engagement_previous']) : null;
        $data['prcentage_of_engagement_current'] = isset($data['prcentage_of_engagement_current']) ? array_values($data['prcentage_of_engagement_current']) : null;

        return parent::handleRespond($data);
    }

    public function EngagementByDayKey(Request $request)
    {

        $table =  'total_engagement_of_source_d_m_y_h_i_s';
        $period = $request->period;

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $campaign_id = $request->campaign_id;

        $data = parent::listDataByType('dayname_engagement', $table, $campaign_id, $start_date, $end_date );

        return parent::handleRespond($data);
    }

    public function EngagementByTimeKey(Request $request)
    {

        $table =  'total_engagement_of_source_d_m_y_h_i_s';

        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $items = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }


        $data['value'] = null;

        foreach ($items->get() as $item) {
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
                        "campaign_id" =>  $item->campaign_id,
                        "campaign_name" => $item->campaign_name,
                        'data' => [0, 0, 0, 0]
                    ];

                    $data['value'][2] = [
                        "id" => 2,
                        "name" => 'Comment',
                        "campaign_id" =>  $this->campaign_id,
                        "campaign_name" => $item->campaign_name,
                        'data' => [0, 0, 0, 0]
                    ];

                    $data['value'][3] = [
                        "id" => 3,
                        "keyword_name" => 'Reactions',
                        "campaign_id" =>  $this->campaign_id,
                        "campaign_name" => $item->campaign_name,
                        'data' => [0, 0, 0, 0]
                    ];

                    // engagement

//                    $data['value'][1]['data'][$index_label] += $item->number_of_comments;
//                    $data['value'][2]['data'][$index_label] += $item->number_of_shares;
//                    $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
            }
        }

        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }


        return parent::handleRespond($data);
    }

    public function EngagementByDeviceKey(Request $request)
    {

        $table =  'total_engagement_of_source_d_m_y_h_i_s';

        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $items = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }


        $data['value'] = null;

        foreach ($items->get() as $item) {
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
                    "campaign_id" =>  $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];

                $data['value'][2] = [
                    "id" => 2,
                    "keyword_name" => 'Comment',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];

                $data['value'][3] = [
                    "id" => 3,
                    "keyword_name" => 'Reactions',
                    "campaign_id" =>  $this->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    'data' => [0, 0, 0]
                ];

                // engagement

//                    $data['value'][1]['data'][$index_label] += $item->number_of_comments;
//                    $data['value'][2]['data'][$index_label] += $item->number_of_shares;
//                    $data['value'][3]['data'][$index_label] += $item->number_of_reactions;
            }
        }

        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }


        return parent::handleRespond($data);
    }

    public function EngagementByAccountKey(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table_root = 'sna_root_node';
        $table_child = 'sna_child_node';

        $infulencer_root = DB::table($table_root)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][1]['data'][0])) {
                $data['value'][1]['data'][0] += $infulencer->number_of_shares;
                $data['value'][2]['data'][0] += $infulencer->number_of_comments;
                $data['value'][3]['data'][0] += $infulencer->number_of_reactions;
            } else {
                $data['value'][1] = [
                    'id' => 1,
                    "keyword_name" => "Share",
                    "data"  => [$infulencer->number_of_shares, 0]
                ];

                $data['value'][2] = [
                    'id' => 2,
                    "keyword_name" => "Comment",
                    "data"  => [$infulencer->number_of_comments, 0]
                ];

                $data['value'][3] = [
                    'id' => 3,
                    "keyword_name" => "Reaction",
                    "data"  => [$infulencer->number_of_reactions, 0]
                ];

            }

        }

        $follower_raw = DB::table($table_child)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);


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

        return parent::handleRespond($data);
    }

    public function EngagementChannelKey(Request $request)
    {

        $data = parent::listSource();

        $table = 'total_engagement_of_source';

        $raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

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

                for ($i = 0; $i <= count($data['labels']); $i++) {

                    $data['value'][1]['data'][$i] = 0;
                    $data['value'][2]['data'][$i] = 0;
                    $data['value'][3]['data'][$i] = 0;
                }

                $data['value'][1]['data'][$index_label] = $item->number_of_shares;
                $data['value'][2]['data'][$index_label] = $item->number_of_comments;
                $data['value'][3]['data'][$index_label] = $item->number_of_reactions;
            }
        }


//        $data['data'][] = [
//            "id" => 1,
//            "name" => "Share",
//            "data" => [
//                80, 50, 30, 40, 100
//            ]
//        ];
//
//        $data['data'][] = [
//            "id" => 2,
//            "name" => "Comment",
//            "data" => [
//                20, 30, 40, 80, 20
//            ]
//        ];
//
//        $data['data'][] = [
//            "id" => 3,
//            "name" => "Reaction",
//            "data" => [
//                44, 76, 78, 13, 43
//            ]
//        ];

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }
        return parent::handleRespond($data);
    }

    public function EngagementComparison(Request $request)
    {

        $data = null;
        $table = 'total_engagement_of_source';
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);


        $totalEngagement_current = $raw_current->sum('engagement');
        $totalEngagement_previous = $raw_previous->sum('engagement');


        $total_share_current = $raw_current->sum('number_of_shares');
        $total_share_previous = $raw_previous->sum('number_of_shares');

        $total_comment_current = $raw_current->sum('number_of_comments');
        $total_comment_previous = $raw_previous->sum('number_of_comments');

        $total_reactions_current = $raw_current->sum('number_of_reactions');
        $total_reactions_previous = $raw_previous->sum('number_of_reactions');


        $data['totalEngagement'] = [
            "totalValue" => parent::custom_number_format((int)$totalEngagement_current),
            "comparison" => (float)parent::point_two_digits($totalEngagement_current- $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous)  : 0),
            "type" => $totalEngagement_current- $totalEngagement_previous > 0 ? "plus" :"minus",
        ];

        $data['share'] = [
            "totalValue" => parent::custom_number_format((int)$total_share_current),
            "comparison" => (float)parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous  * 100) : 0),
            "type" => $total_share_current- $total_share_previous > 0 ? "plus" :"minus",
        ];

        $data['comment'] = [
            "totalValue" => parent::custom_number_format((int)$total_comment_current),
            "comparison" => (float)parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0),
            "type" => $total_comment_current- $total_comment_previous > 0 ? "plus" :"minus",
        ];

        $data['reaction'] = [
            "totalValue" => parent::custom_number_format((int)$total_reactions_current),
            "comparison" => (float)parent::point_two_digits(($total_reactions_current- $total_reactions_previous) / $total_reactions_previous),
            "type" => $total_reactions_current- $total_reactions_previous > 0 ? "plus" :"minus",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementPeriodPlarform(Request $request)
    {

        $data = parent::listSource();
        $table = 'total_engagement_of_source_d_m_y_h_i_s';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);

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
                $data['value'][2]['data'][$index_label] += $item->engagement;
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

                $data['value'][2]['data'][$index_label] += $item->engagement;
            }
        }


        foreach ($items_previous as $item) {

            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][1])) {
                $data['value'][1]['data'][$index_label] += $item->engagement;
            }

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i <= count($data['labels']); $i++) {
            $data['share'][$i]  = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
            $data['comment'][$i]  = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
            $data['reaction'][$i]  = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
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

        return parent::handleRespond($data);
    }

    public function EngagementPeriodSentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $table = 'message_result_semetic_engagement';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);

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
            $index_label = array_search($item->classification_name, $data['labels']);

            if (isset($data['value'][2])) {
                $data['value'][2]['data'][$index_label] += 1;
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

                for ($i = 0; $i < count($data['labels']); $i++) {
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

                $data['value'][2]['data'][$index_label] += $item->engagement;
            }
        }


        foreach ($items_previous as $item) {

            $index_label = array_search($item->classification_name, $data['labels']);

            if (isset($data['value'][1])) {

                $data['value'][1]['data'][$index_label] += $item->engagement;
            }

            $previous_share[$index_label] += $item->number_of_shares;
            $previous_comment[$index_label] += $item->number_of_comments;
            $previous_reaction[$index_label] += $item->number_of_reactions;
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        for ($i = 0; $i < count($data['labels']); $i++) {
            $data['share'][$i]  = $this->overPeriodComparison($current_share[$i], $previous_share[$i]);
            $data['comment'][$i]  = $this->overPeriodComparison($current_comment[$i], $previous_comment[$i]);
            $data['reaction'][$i]  = $this->overPeriodComparison($current_reaction[$i], $previous_reaction[$i]);
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

        return parent::handleRespond($data);
    }

    public function EngagementTypeComparison(Request $request)
    {
        $data = [];

        $table = 'total_engagement_of_source_d_m_y_h_i_s';

        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);

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

        foreach ($current as $key => $item) {

            $data[] = [
                'keyword_id' => $key,
                'keyword_name' => $item['keyword_name'],
                'total' => [
                    "value" => $item['total'] - $previous[$key]['total'],
                    "percentage" => $this->overPeriodComparison($item['total'], $previous[$key]['total']),
                    "type" => $item['total'] - $previous[$key]['total'] >0 ? "plus" : "minus",
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

        return parent::handleRespond($data);
    }

    public function EngagementActionComparison(Request $request)
    {
        $engagement_action_raw = DB::table('total_engagement_of_source_d_m_y_h_i_s')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $engagement_actions = $engagement_action_raw->get();


        $percentages = null;
        foreach ($engagement_actions as $engagement_action) {
            if (isset($percentages[$engagement_action->keyword_id]) ) {

                $percentages[$engagement_action->keyword_id]['total'] += $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share'] = (float)self::point_two_digits(($percentages[$engagement_action->keyword_id]['share_r'] / $percentages[$engagement_action->keyword_id]['total']) * 100);
                $percentages[$engagement_action->keyword_id]['comment'] = (float)self::point_two_digits(($percentages[$engagement_action->keyword_id]['comment_r'] / $percentages[$engagement_action->keyword_id]['total']) * 100); ;
                $percentages[$engagement_action->keyword_id]['reaction'] = (float)self::point_two_digits(($percentages[$engagement_action->keyword_id]['reaction_r'] / $percentages[$engagement_action->keyword_id]['total']) * 100);
            }
            else {
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


                $percentages[$engagement_action->keyword_id]['share_r'] += (float)$engagement_action->number_of_shares;
                $percentages[$engagement_action->keyword_id]['comment_r'] += (float)$engagement_action->number_of_comments;
                $percentages[$engagement_action->keyword_id]['reaction_r'] += (float)$engagement_action->number_of_reactions;
                $percentages[$engagement_action->keyword_id]['share'] = (float)self::point_two_digits(($engagement_action->number_of_shares / (float)$engagement_action->engagement) * 100);
                $percentages[$engagement_action->keyword_id]['comment'] = (float)self::point_two_digits(($engagement_action->number_of_comments / (float)$engagement_action->engagement) * 100);
                $percentages[$engagement_action->keyword_id]['reaction'] = (float)self::point_two_digits(($engagement_action->number_of_reactions / (float)$engagement_action->engagement) * 100);
                $percentages[$engagement_action->keyword_id]['total'] +=  $engagement_action->number_of_shares + $engagement_action->number_of_comments + $engagement_action->number_of_reactions;
            }
        }

        if ($percentages) {
            $percentages = array_values($percentages);

        }

        return parent::handleRespond($percentages);
    }

    public function EngagementByInfulencer(Request $request)
    {

        $data = [
            [
                "infulencer" => "User 1",
                "total" => 39,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 2",
                "total" => 25,
                "share" => 25,
                "comment" => 0,
                "reaction" => 0,
                "period_over_preiod" => "+10",
                "period_over_period_percentage" => "+2"
            ],
            [
                "infulencer" => "User 3",
                "total" => 29,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 4",
                "total" => 95,
                "share" => 90,
                "comment" => 0,
                "reaction" => 5,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 5",
                "total" => 29,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 6",
                "total" => 32,
                "share" => 32,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "+20",
                "period_over_period_percentage" => "+30"
            ]
        ];

        return parent::handleRespond($data);
    }

    private function percentageOfEngagement($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $table = 'total_engagement_of_source';
        $column = 'engagement';
        return parent::getDataByCondition($table,$campaign_id, $start_date, $end_date,  $keyword_id, $source_id, $column, 'percentage');
    }

    private function engagement($campaign_id, $start_date, $end_date, $source)
    {
        $table = 'total_engagement_of_source';
        $column = 'engagement';
        return parent::getDataByCondition($table, $campaign_id, $start_date, $end_date, null, $source, $column, 'engagement');

    }
}
