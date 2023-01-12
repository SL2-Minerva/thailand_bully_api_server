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
        $data = parent::listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByTime(Request $request)
    {

        $table =  'message_result_bully_d_m_y_h_i_s';
        $data = parent::listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByDevice(Request $request)
    {

        $table =  'message_device_bully';
        $data = parent::listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

        return parent::handleRespond($data);
    }

    public function BullyByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                15,
                35,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function BullyByChannel(Request $request)
    {
        $table =  'message_result_bully';
        $data = parent::listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null );

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
        $data = parent::listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByTime(Request $request)
    {
        $table =  'message_result_bully_d_m_y_h_i_s';
        $data = parent::listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByDevice(Request $request)
    {
        $table =  'message_device_bully';
        $data = parent::listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

        return parent::handleRespond($data);
    }

    public function BullyTypeByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [

            "id" => 3,
            "keyword_name" => "Harassement",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                23,
                53,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function BullyTypeByChannel(Request $request)
    {
        $table =  'message_result_bully';
        $data = parent::listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null );

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
}
