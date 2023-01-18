<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use App\Models\Message;
use Illuminate\Support\Carbon;
use App\Models\DailyMessage;
use App\Models\MessageResultBully;
use App\Models\PercentageOfMessages;
use App\Models\Sources;
use Illuminate\Support\Facades\DB;

class VoiceDashboardController extends Controller
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
    public function PercentageOfMessage(Request $request)
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
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($campaign_id, $start_date, $end_date, $request->keyword_id ?? null, $request->source_id ?? null);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($campaign_id, $start_date_previous, $end_date_previous, $request->keyword_id ?? null, $request->source_id ?? null);

        return parent::handleRespond($data);
    }

    private function percentageOfMessages($campaign_id, $start_date, $end_date, $keyword_id, $source_id = null)
    {
        $table = 'percentage_of_messages';
        $column = 'total_at_keyword';

        return  parent::getDataByCondition($table, $campaign_id, $start_date, $end_date, $keyword_id, $source_id, $column, 'percentage', ['group_by' => ['keyword_name']]);
    }

    public function dailyMessage(Request $request)
    {
        $campaign_id = $request->campaign_id;
        $source = $request->source;

        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }
        $table = 'daily_message';
        $column = 'engagement';
        return parent::handleRespond(parent::getDataByCondition($table, $campaign_id, $request->start_date, $request->end_date, null, $source, $column, 'daily_message'));
    }

    public function MessageByDay(Request $request)
    {

        $table =  'daily_message';
        $data = parent::listDataByType('dayname', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageByTime(Request $request)
    {
        $table =  'daily_message_device_d_m_y_h_i_s';
        $data = parent::listDataByType('time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageByDevice(Request $request)
    {

        $table =  'daily_message_device';
        $data = parent::listDataByType('device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageByAccount(Request $request)
    {
        $data['labels'] = [
            "Post Owner",
            "Follower",
        ];

        $table = 'sna_root_node';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->keyword_id]['data'][0])) {
                $data['value'][$infulencer->keyword_id]['data'][0] += 1;
            } else {
                $data['value'][$infulencer->keyword_id]['id'] = $infulencer->keyword_id;
                $data['value'][$infulencer->keyword_id]['keyword_name'] = $infulencer->keyword_name;
                $data['value'][$infulencer->keyword_id]['data'][0] = 0;

            }

        }

        $table = 'sna_child_node';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->keyword_id]['data'][1])) {
                $data['value'][$follower->keyword_id]['data'][1] += 1;
            } else {
                $data['value'][$follower->keyword_id]['id'] = $follower->keyword_id;
                $data['value'][$follower->keyword_id]['keyword_name'] = $follower->keyword_name;
                $data['value'][$follower->keyword_id]['data'][1] = 0;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function MessageByChannel(Request $request)
    {

        $table =  'daily_message';
        $data = parent::listDataByType('channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageBySentiment(Request $request)
    {
        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 1);

        $level = Classification::where('classification_type_id', 1)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {

            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
        
    }

    public function MessageByLevel(Request $request)
    {

        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 3);

        $level = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {

            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
    }

    public function MessageByType(Request $request)
    {

        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 2);

        $level = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {

            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0,0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
    }

    public function NumberOfAccount(Request $request)
    {
        $data[] = [
            "name" =>  "Keyword 1",
            "data" => [
                44,
                55,
                41,
                67,
                22,
                43,
                21,
                49,
                29,
                36,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 2",
            "data" => [
                13,
                23,
                20,
                8,
                13,
                27,
                33,
                12,
                29,
                34,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 3",
            "data" => [
                11,
                17,
                15,
                15,
                21,
                14,
                15,
                13,
                65,
                29,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 4",
            "data" => [
                44,
                55,
                41,
                67,
                22,
                43,
                21,
                49,
                58,
                37,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 5",
            "data" => [
                30,
                23,
                20,
                8,
                13,
                27,
                33,
                12,
                62,
                43,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $data['total_messages'] = [
            "total_message" => 40000,
            "percentage" => "10",
            "type" => "minus",
        ];

        $data['total_account'] = [
            "total_message" => 200,
            "percentage" => "20",
            "type" => "plus",
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeComparison(Request $request)
    {
        $data[] = [
            "name" =>  "Mon.",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data[] = [
            "name" =>  "Tue.",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data[] = [
            "name" =>  "Wed.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        $data[] = [
            "name" =>  "Thu.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        $data[] = [
            "name" =>  "Fri.",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data[] = [
            "name" =>  "Sat.",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data[] = [
            "name" =>  "Sun.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeSentiment(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "Negative",
            "data" => [
                10, 20, 30, 40, 50, 60, 70,
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Neutral",
            "data" => [
                10, 20, 30, 20, 60, 100, 70,
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Positive",
            "data" => [
                10, 20, 20, 20, 60, 100, 20,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Negative",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Neutral",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Positive",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeLevel(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "level 3",
            "data" => [
                10, 20, 30, 40, 50, 60, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 2",
            "data" => [
                10, 20, 30, 20, 60, 100, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 1",
            "data" => [
                10, 20, 20, 20, 60, 100, 20
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 0",
            "data" => [
                10, 20, 30, 20, 60, 100, 74
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 3",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 2",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 1",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 0",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeType(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "Hate Speech",
            "data" => [
                10, 20, 30, 40, 50, 60, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Exclusion",
            "data" => [
                10, 20, 30, 20, 60, 100, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Harassment",
            "data" => [
                10, 20, 20, 20, 60, 100, 20
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Gossip",
            "data" => [
                10, 20, 30, 20, 60, 100, 74
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "No Bully",
            "data" => [
                10, 20, 30, 20, 60, 100, 70,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Hate Speech",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Exclusion",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Harassment",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Gossip",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "No Bully",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelPlatform(Request $request)
    {
        $label = Sources::where('status', 1)->get();

        $data['current_period']['label'] = [];
        $data['previous_period']['label'] = [];

        foreach ($label as $item) {
            $data['current_period']['label'][] = $item->name;
            $data['current_period']['data'][] = 0;

            $data['previous_period']['label'][] = $item->name;
            $data['previous_period']['data'][] = 0;
        }

        $items_current = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $items_previous = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);

        foreach ($items_current->get() as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['current_period']['label']);
            
            if (isset($data['current_period']['data'])) {
                $data['current_period']['data'][$index_label] += $item->total_at_date;
            } else {
                $data['current_period']= [
                    'data' => [0,0,0,0,0,0]
                ];

                $data['current_period']['data'][$index_label] += $item->total_at_date;
            }
        }
        
        $data['current_period']['total'] = array_sum($data['current_period']['data']);

        foreach ($items_previous->get() as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['previous_period']['label']);
            
            if (isset($data['previous_period']['data'])) {
                $data['previous_period']['data'][$index_label] += $item->total_at_date;
            } else {
                $data['previous_period']= [
                    'data' => [0,0,0,0,0,0]
                ];

                $data['previous_period']['data'][$index_label] += $item->total_at_date;
            }
        }

        $data['previous_period']['total'] = array_sum($data['previous_period']['data']);

        return parent::handleRespond($data);
    }

    public function Device(Request $request)
    {
        $data['previous_period']['label'] = [
            "Andriod",
            "Iphone",
            "Web App"
        ];

        $data['current_period']['label'] = [
            "Andriod",
            "Iphone",
            "Web App"
        ];

        $data['previous_period']['data'] = [0,0,0];
        $data['current_period']['data'] = [0,0,0];

        $items_current = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
        $items_previous = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);
        
        foreach ($items_current->get() as $item) {
            if($item->device === "android") {
                $data['current_period']['data'][0] += $item->total_at_date;
            }
            if($item->device === "iphone") {
                $data['current_period']['data'][1] += $item->total_at_date;
            }
            if($item->device === "webapp") {
                $data['current_period']['data'][2] += $item->total_at_date;
            }
        }
        $data['current_period']['total'] = array_sum($data['current_period']['data']);
        

        foreach ($items_previous->get() as $item) {
            if($item->device === "android") {
                $data['previous_period']['data'][0] += $item->total_at_date;
            }
            if($item->device === "iphone") {
                $data['previous_period']['data'][1] += $item->total_at_date;
            }
            if($item->device === "webapp") {
                $data['previous_period']['data'][2] += $item->total_at_date;
            }
        }
        $data['previous_period']['total'] = array_sum($data['previous_period']['data']);

        return parent::handleRespond($data);
    }

    public function ChannelDevice(Request $request)
    {
        $data['labels'][] = [
            "Andriod",
            "Facebook"
        ];

        $data['labels'][] = [
            "iPhone",
            "Twitter"
        ];

        $data['labels'][] = [
            "Web",
            "Youtube"
        ];

        $data['data'] = [
            44, 50, 6,
        ];

        return parent::handleRespond($data);
    }

    public function KeywordSentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Negative",
            "Neutral"
        ];

        $data['data'][] = [
            "name" => "All",
            "data" => [
                44, 50, 6,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 1",
            "data" => [
                80, 50, 100
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 2",
            "data" => [
                20, 40, 10
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 3",
            "data" => [
                44, 76, 45
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 4",
            "data" => [
                20, 40, 12
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 5",
            "data" => [
                45, 26, 30
            ]
        ];

        return parent::handleRespond($data);
    }

    public function KeywordBullyLevel(Request $request)
    {
        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 3);

        $level = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {

            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
    }

    public function KeywordBullyType(Request $request)
    {

        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 2);

        $level = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {
            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0,0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
    }

    public function KeywordChannel(Request $request)
    {

        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $level = Sources::where('status', 1)->get();
        $data['labels'] = [];

        foreach ($level as $item) {
            $data['labels'][] = $item->name;
        }
        
        foreach ($items->get() as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['labels']);

            
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0,0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }
        
        return parent::handleRespond($data);
    }
}
