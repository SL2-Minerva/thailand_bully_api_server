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
        $this->campaign_id = $request->campaign_id;
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

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
                19,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                65,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                67,
                89,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                23,
                56,
            ]
        ];

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

        $table = 'total_engagement_of_source';
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

        $data['engagement'] = array_values($data['engagement']);
        $data['prcentage_of_engagement_previous'] = array_values($data['prcentage_of_engagement_previous']);
        $data['prcentage_of_engagement_current'] = array_values($data['prcentage_of_engagement_current']);

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

        $data['value'] = array_values($data['value']);

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

        $data['value'] = array_values($data['value']);

        return parent::handleRespond($data);
    }

    public function EngagementByAccountKey(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Share",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Comment",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Reaction",
            "data" => [
                23,
                53,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementChannelKey(Request $request)
    {

        $data['labels'] = [
            "Facebook",
            "Twitter",
            "Instagram",
            "Youtube",
            "Pantip",
        ];

        $data['data'][] = [
            "id" => 1,
            "name" => "Share",
            "data" => [
                80, 50, 30, 40, 100
            ]
        ];

        $data['data'][] = [
            "id" => 2,
            "name" => "Comment",
            "data" => [
                20, 30, 40, 80, 20
            ]
        ];

        $data['data'][] = [
            "id" => 3,
            "name" => "Reaction",
            "data" => [
                44, 76, 78, 13, 43
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementComparison(Request $request)
    {
        $data['totalEngagement'] = [
            "totalValue" => "1.2M",
            "comparison" => "-1%",
            "type" => "minus",
        ];

        $data['share'] = [
            "totalValue" => "800k",
            "comparison" => "3%",
            "type" => "plus",
        ];

        $data['comment'] = [
            "totalValue" => "20k",
            "comparison" => "-3%",
            "type" => "minus",
        ];

        $data['reaction'] = [
            "totalValue" => "1.45M",
            "comparison" => "-1%",
            "type" => "minus",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementPeriodPlarform(Request $request)
    {
        $data['labels'] = [
            "Facebook",
            "Twitter",
            "Instagram",
            "Youtube",
            "Pantip",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Previous",
            "data" => [
                19, 38, 47, 16, 30
            ],
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Current",
            "data" => [
                15, 45, 65, 23, 53,
            ],
        ];

        $data['share'] = [
            "-30%", "-30%", "-30%", "-30%", "-30%",
        ];

        $data['comment'] = [
            "-23%", "-23%", "-23%", "-23%", "-23%",
        ];

        $data['reaction'] = [
            "-56%", "-56%", "-56%", "-56%", "-56%",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementPeriodSentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Previous",
            "data" => [
                47, 16, 30,
            ],
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Current",
            "data" => [
                12, 16, 78,
            ],
        ];

        $data['share'] = [
            "-30%", "-30%", "-30%", "-30%", "-30%",
        ];

        $data['comment'] = [
            "-23%", "-23%", "-23%", "-23%", "-23%",
        ];

        $data['reaction'] = [
            "-56%", "-56%", "-56%", "-56%", "-56%",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementTypeComparison(Request $request)
    {
        $data = [];

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

        $data = [
            [
                "keyword_id" => 1,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 1,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 1,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "reaction" => 30,
                "comment" => 60
            ],
            [
                "keyword_id" => 2,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 2,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 2,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 3,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 3,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 3,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 4,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 4,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 4,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 5,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 5,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 5,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ]
        ];

        return parent::handleRespond($data);
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
