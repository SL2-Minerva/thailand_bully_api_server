<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\DailyMessage;
use App\Models\MessageResult;
use App\Models\MessageResultBully;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sources;
use App\Models\Classification;

class BullyDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;

    public function __construct(Request $request)
    {

        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);

    }

    public function PercentageBully(Request $request)
    {
        $data = null;
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($request->campaign_id, $this->start_date, $this->end_date, 3);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($request->campaign_id, $this->start_date_previous, $this->end_date_previous, 3);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($campaign_id, $start_date, $end_date, $classification_type_id)
    {
        $data = null;
        $percentage_of_bully = MessageResultBully::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('classification_type_id', $classification_type_id)
            ->groupBy('classification_id');

        foreach ($percentage_of_bully->get() as $bully) {

            $classification_id = $bully->classification_id;
            $data[$classification_id]['bully_level'] = $bully->classification_name;
            $data[$classification_id]['campaign_id'] = $bully->campaign_id;
            $data[$classification_id]['campaign_name'] = $bully->campaign_name;

            $channal_message = $this->bullyTable($campaign_id, $start_date, $end_date, $classification_id, $bully->classification_type_id);
            $channal_message_total = MessageResultBully::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->where('classification_type_id', $bully->classification_type_id)
                ->sum('total_at_date');

            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $this->point_two_digits(($channal_message / $channal_message_total) * 100),
            ];

            $data[$classification_id]['value'] = $nestData;
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    public function DailyBully(Request $request)
    {
        $data = null;

        $daily_messages = MessageResultBully::where('campaign_id', $request->campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($daily_messages->get() as $daily_message) {
            
            if ($daily_message->classification_type_id === 3 ) {

                $classification_id = $daily_message->classification_id;
                $data[$classification_id]['source_id'] = $daily_message->classification_id;
                $data[$classification_id]['bully_level'] = $daily_message->classification_name;
                $data[$classification_id]['source_name'] = $daily_message->source_name;
                $data[$classification_id]['campaign_id'] = $daily_message->campaign_id;
                $data[$classification_id]['campaign_name'] = $daily_message->campaign_name;
    
                $nestData = [
                    'keyword_id' => $daily_message->keyword_id,
                    'keyword_name' => $daily_message->keyword_name,
                    'date_m' => $daily_message->date_m,
                    'total_at_date' => $daily_message->total_at_date
                ];
    
                $data[$classification_id]['value'][] = $nestData;
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);

    }

    public function BullyByDay(Request $request)
    {

        $table =  'message_result_bully';
        $data = $this->listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByTime(Request $request)
    {

        $table =  'message_result_bully_d_m_y_h_i_s';
        $data = $this->listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByDevice(Request $request)
    {

        $table =  'message_device_bully';
        $data = $this->listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table = 'sna_root_node';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 3)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                $data['value'][$infulencer->classification_id]['data'][0] += 1;
            } else {
                $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->classification_name;
                $data['value'][$infulencer->classification_id]['data'][0] = 0;

            }

        }

        $table = 'sna_child_node';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 3)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->classification_id]['data'][1])) {
                $data['value'][$follower->classification_id]['data'][1] += 1;
            } else {
                $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                $data['value'][$follower->classification_id]['data'][1] = 0;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }



        return parent::handleRespond($data);
    }

    public function BullyByChannel(Request $request)
    {
        $table =  'message_result_bully';
        $data = $this->listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                19,
                38,
                47,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                12,
                16,
                32,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                15,
                45,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                23,
                17,
                34,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function BullyTypePercentageDaily(Request $request)
    {
        $data = null;
        
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($request->campaign_id, $this->start_date, $this->end_date, 2);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($request->campaign_id, $this->start_date_previous, $this->end_date_previous, 2);

        return parent::handleRespond($data);
    }

    public function BullyTypeDaily(Request $request)
    {
        $data = null;

        $daily_messages = MessageResultBully::where('campaign_id', $request->campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($daily_messages->get() as $daily_message) {
            
            if ($daily_message->classification_type_id === 2 ) {

                $classification_id = $daily_message->classification_id;
                $data[$classification_id]['source_id'] = $daily_message->classification_id;
                $data[$classification_id]['bully_level'] = $daily_message->classification_name;
                $data[$classification_id]['source_name'] = $daily_message->source_name;
                $data[$classification_id]['campaign_id'] = $daily_message->campaign_id;
                $data[$classification_id]['campaign_name'] = $daily_message->campaign_name;
    
                $nestData = [
                    'keyword_id' => $daily_message->keyword_id,
                    'keyword_name' => $daily_message->keyword_name,
                    'date_m' => $daily_message->date_m,
                    'total_at_date' => $daily_message->total_at_date
                ];
    
                $data[$classification_id]['value'][] = $nestData;
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function BullyTypeByDay(Request $request)
    {
        $table =  'message_result_bully';
        $data = $this->listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByTime(Request $request)
    {
        $table =  'message_result_bully_d_m_y_h_i_s';
        $data = $this->listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByDevice(Request $request)
    {
        $table =  'message_device_bully';
        $data = $this->listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table = 'sna_root_node';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 2)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                $data['value'][$infulencer->classification_id]['data'][0] += 1;
            } else {
                $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->classification_name;
                $data['value'][$infulencer->classification_id]['data'][0] = 0;

            }

        }

        $table = 'sna_child_node';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', 2)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->classification_id]['data'][1])) {
                $data['value'][$follower->classification_id]['data'][1] += 1;
            } else {
                $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                $data['value'][$follower->classification_id]['data'][1] = 0;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }



        return parent::handleRespond($data);
    }

    public function BullyTypeByChannel(Request $request)
    {
        $table =  'message_result_bully';
        $data = $this->listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                19,
                38,
                47,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                12,
                16,
                32,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Harassment",
            "data" => [
                15,
                45,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                23,
                17,
                34,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function BullyChartLevel(Request $request)
    {

        $bully = MessageResultBully::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 3)->get();

        $all = 0;
        foreach ($bully as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += $item->total_at_date
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += $item->total_at_date;
                $data["all"]['data'] += $item->total_at_date;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $item->classification_name,
                    'data' => 0
                ];
            }
        }
        
        if (!$data) {
            return parent::handleRespond($data);
        }
        
        return parent::handleRespond(array_values($data));
    }

    public function BullyLevelLevel(Request $request)
    {
        $data = [
            [
                "id" => 1,
                "keyword_name" => "all",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 0",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 1",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 2",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 3",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ]
        ];

        return parent::handleRespond([]);
    }

    public function BullyChartType(Request $request)
    {
        $bully = MessageResultBully::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 2)->get();
            
        $all = 0;
        foreach ($bully as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += $item->total_at_date
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += $item->total_at_date;
                $data["all"]['data'] += $item->total_at_date;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $item->classification_name,
                    'data' => 0
                ];
            }
        }
        
        if (!$data) {
            return parent::handleRespond($data);
        }
        
        return parent::handleRespond(array_values($data));
    }

    public function BullyTableType(Request $request)
    {
        $data = [
            [
                "id" => 1,
                "keyword_name" => "all",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "No Bully",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Gossip",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Harassment",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Exclusion",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Hate Speech",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ]
        ];

        return parent::handleRespond([]);
    }

    private function bullyTable($campaign_id, $start_date, $end_date, $classification_id, $classification_type_id)
    {
        return MessageResultBully::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('classification_id', $classification_id)
            ->where('classification_type_id', $classification_type_id)
            ->sum('total_at_date');
    }

    private function listDataByType($type, $table, $campaign_id, $start_date, $end_date, $keyword_id = null, $source_id = null, $column = null, $condition = null)
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

        $data['value'] = null;

        if ($type === 'time') {
            $data['labels'] = [
                "Before 6 AM",
                "6 AM-12 PM",
                "12 PM-6 PM",
                "After 6 PM"
            ];
        }

        if ($type === 'channel_by_time' || $type === 'bully_level_by_time') {
            $data['labels'] = [
                "Before 6 AM",
                "6 AM-12 PM",
                "12 PM-6 PM",
                "After 6 PM"
            ];
        }

        if ($type === 'device') {
            $data['labels'] = [
                "Android",
                "Iphone",
                "Web App",
            ];
        }

        if ($type === 'channel_by_device' || $type === 'bully_level_by_device') {
            $data['labels'] = [
                "Android",
                "Iphone",
                "Web App",
            ];
        }

        if ($type === 'channel' || $type === 'bully_level_by_channel') {

            $sources = Sources::all();
            $data['labels'] = [];

            foreach ($sources as $source) {
                $data['labels'][] = $source->name;
            }

        }


        if ($type === 'sentiment' || $type === 'bully_level_by_sentiment') {
            $sentiment = Classification::where('classification_type_id', 1)->get();
            $data['labels'] = [];

            foreach ($sentiment as $item) {
                $data['labels'][] = $item->name;
            }
        }

        $debug = [];
        foreach ($items->get() as $item) {
            if ($type === 'dayname' || $type === 'dayname_engagement' || $type === 'channel_by_day' || $type === 'bully_level_by_day') {
                $day_name = Carbon::parse($item->date_m)->format('D');


                $index_label = array_search($day_name, $data['labels']);


                if ($type === 'dayname') {

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

                if ($type === 'channel_by_day') {
                    if (isset($data['value'][$item->source_id])) {
                        $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
                    } else {

                        $data['value'][$item->source_id] = [
                            'id' => $item->source_id,
                            'name' => $item->source_name,
                            'keyword_name' => $item->source_name,
                            'campaign_id' => $item->campaign_id,
                            'campaign_name' => $item->campaign_name,
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ];

                        $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
                    }
                }

                if ($type === 'bully_level_by_day') {
                    if ($item->classification_type_id === $column) {

                        if (isset($data['value'][$item->classification_id])) {
                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;


                        } else {
                            $data['value'][$item->classification_id] = [
                                'id' => $item->classification_id,
                                'keyword_name' => $item->classification_name,
                                'data' => [0, 0, 0, 0, 0, 0, 0]
                            ];

                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                        }
                    }
                }


                if ($type === 'dayname_engagement') {

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

            }


            if ($type === 'time' || $type === 'time_engagement' || $type === 'channel_by_time' || $type === 'bully_level_by_time') {

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

                if ($type === 'time' || $type === 'time_engagement') {
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

                if ($type === 'channel_by_time') {
                    if (isset($data['value'][$item->source_id])) {
                        $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;


                    } else {
                        $data['value'][$item->source_id] = [
                            'id' => $item->source_id,
                            'keyword_name' => $item->keyword_name,
                            'campaign_id' => $item->campaign_id,
                            'campaign_name' => $item->campaign_name,
                            'source_id' => $item->source_id,
                            'source_name' => $item->source_name,
                            'data' => [0, 0, 0, 0]
                        ];

                        $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
                    }
                }

                if ($type === 'bully_level_by_time') {
                    if ($item->classification_type_id === $column) {

                        if (isset($data['value'][$item->classification_id])) {
                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;


                        } else {
                            $data['value'][$item->classification_id] = [
                                'id' => $item->classification_id,
                                'keyword_name' => $item->classification_name,
                                'data' => [0, 0, 0, 0]
                            ];

                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                        }
                    }
                }

            }



            if ($type === 'device') {
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

            if ($type === 'channel_by_device') {
                $index_label = 0;

                if ($item->device == 'iphone') {
                    $index_label = 1;
                }

                if ($item->device == 'webapp') {
                    $index_label = 2;
                }

                if (isset($data['value'][$item->source_id])) {
                    $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
                } else {
                    $data['value'][$item->source_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
                }
            }

            if ($type === 'bully_level_by_device') {
                if ($item->classification_type_id === $column) {

                    $index_label = 0;

                    if ($item->device == 'iphone') {
                        $index_label = 1;
                    }

                    if ($item->device == 'webapp') {
                        $index_label = 2;
                    }

                    if (isset($data['value'][$item->classification_id])) {
                        $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                    } else {
                        $data['value'][$item->classification_id] = [
                            'id' => $item->classification_id,
                            'keyword_name' => $item->classification_name,
                            'data' => [0, 0, 0]
                        ];

                        $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                    }
                }

            }

            if ($type === 'channel' || $type === 'bully_level_by_channel') {
                $index_label = 0;
                $index_label = array_search($item->source_name, $data['labels']);

                if ($type === 'channel') {
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

                if ($type === 'bully_level_by_channel') {
                    if ($item->classification_type_id === $column) {

                        if (isset($data['value'][$item->classification_id])) {
                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                        } else {
                            $data['value'][$item->classification_id] = [
                                'id' => $item->classification_id,
                                'keyword_name' => $item->classification_name,
                                'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                            ];

                            $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
                        }
                    }
                }


            }

            if ($type === 'sentiment' || $type === 'bully_level_by_sentiment') {
                $index_label = 0;
                $index_label = array_search($item->classification_name, $data['labels']);


                if ($type === 'sentiment') {

                    if (isset($data['value'][$item->source_id])) {
                        $data['value'][$item->source_id]['data'][$index_label] += 1;
                    } else {
                        $data['value'][$item->source_id] = [
                            'id' => $item->source_id,
                            'name' => $item->keyword_name,
                            'keyword_name' => $item->keyword_name,
                            'source_name' => $this->source_name($item->source_id),
                            'classification_name' => $item->classification_name,
                            'classification_id' => $item->classification_id,
                            'source_id' => $item->source_id,
                            'campaign_id' => $item->campaign_id,
                            'campaign_name' => $item->campaign_name,
                            'data' => [0, 0, 0]
                        ];

                        $data['value'][$item->source_id]['data'][$index_label] += 1;
                    }
                }

                if ($type === 'bully_level_by_sentiment') {
                    if ($item->classification_type_id === $column) {
                        
                        if (isset($data['value'][$item->classification_id])) {
                            // dd($data['value'][$item->classification_id]);
                            $data['value'][$item->classification_id]['data'][$index_label] += 1;
                        } else {
                            $data['value'][$item->classification_id] = [
                                'id' => $item->classification_id,
                                'keyword_name' => $item->classification_name,
                                'data' => [0, 0, 0]
                            ];

                            $data['value'][$item->classification_id]['data'][$index_label] += 1;
                        }
                    }

                }

            }



        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    private function source_name($source_id)
    {
        $source = Sources::where('id', $source_id)->first();
        return $source->name;
    }
}
