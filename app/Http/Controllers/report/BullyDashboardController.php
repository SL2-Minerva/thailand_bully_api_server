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
    private $keyword_id;

    public function __construct(Request $request)
    {

        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);

        if ($request->fillter_keywords) {
            $this->keyword_id = $request->fillter_keywords;
            $this->keyword_id = explode(",", $this->keyword_id);
        }

    }

    public function PercentageBully(Request $request)
    {
        $data = null;
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($start_date, $end_date)
    {
        $data = [];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $message_total = 0;

        foreach ($items as $item) {
            $message_total += 1;
            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['value']['total'] += 1;
            } else {
                $data[$item->classification_id]['bully_level'] = $item->classification_name;
                $data[$item->classification_id]['campaign_id'] = $item->campaign_id;
                $data[$item->classification_id]['campaign_name'] = $item->campaign_name;
                $data[$item->classification_id]['value']['total'] = 1;
                $data[$item->classification_id]['value']['date'] = Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y');
            }
        }

        foreach ($data as $key => $value) {
            $data[$key]['value']['percentage'] = $this->point_two_digits(($data[$key]['value']['total'] / $message_total) * 100);
        }

        if ($data) {
            $data = array_values($data);
        }

        return $data;
    }

    public function DailyBully(Request $request)
    {
        $data = null;

        $daily_messages = MessageResultBully::where('campaign_id', $request->campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($daily_messages->get() as $daily_message) {

            if ($daily_message->classification_type_id === 3) {

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

        $table = 'message_result_bully';
        $data = $this->listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null);

        return parent::handleRespond($data);
    }

    public function BullyByTime(Request $request)
    {

        $table = 'message_result_bully_d_m_y_h_i_s';
        $data = $this->listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null);

        return parent::handleRespond($data);
    }

    public function BullyByDevice(Request $request)
    {

        $table = 'message_device_bully';
        $data = $this->listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null);

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
        $table = 'message_result_bully';
        $data = $this->listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 3, null);

        return parent::handleRespond($data);
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

            if ($daily_message->classification_type_id === 2) {

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
        $table = 'message_result_bully';
        $data = $this->listDataByType('bully_level_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null);

        return parent::handleRespond($data);
    }

    public function BullyTypeByTime(Request $request)
    {
        $table = 'message_result_bully_d_m_y_h_i_s';
        $data = $this->listDataByType('bully_level_by_time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null);

        return parent::handleRespond($data);
    }

    public function BullyTypeByDevice(Request $request)
    {
        $table = 'message_device_bully';
        $data = $this->listDataByType('bully_level_by_device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null);

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
        $table = 'message_result_bully';
        $data = $this->listDataByType('bully_level_by_channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 2, null);

        return parent::handleRespond($data);
    }


    public function BullyChartLevel(Request $request)
    {

        $bully = MessageResultBully::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 3)->get();

        $all = 0;
        $data = [];
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


    public function BullyChartType(Request $request)
    {
        $bully = MessageResultBully::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('classification_type_id', 2)->get();

        $all = 0;

        $data = [];
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

    public function BullyBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][10] = ["id" => 10, "keyword_name" => "Level 0", "data" => [0, 0, 0]];
        $data['value'][11] = ["id" => 11, "keyword_name" => "Level 1", "data" => [0, 0, 0]];
        $data['value'][12] = ["id" => 12, "keyword_name" => "Level 2", "data" => [0, 0, 0]];
        $data['value'][13] = ["id" => 13, "keyword_name" => "Level 3", "data" => [0, 0, 0]];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1, 3]);

        if ($this->keyword_id) {
            $raw->where('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $anylsys = [];
        foreach ($items as $item) {

            $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
        }

        foreach ($anylsys as $anylsy) {

            $index_data = 10;

            if ($anylsy['Bully Level'] === 'Level 1') {
                $index_data = 11;
            }

            if ($anylsy['Bully Level'] === 'Level 2') {
                $index_data = 12;
            }

            if ($anylsy['Bully Level'] === 'Level 3') {
                $index_data = 13;
            }


            $index_label = array_search($anylsy['Sentiment'], $data['labels']);
            $data['value'][$index_data]['data'][$index_label] += 1;

        }

        if ($data) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function BullyTypeBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $bully_types = DB::table('classifications')->where('classification_type_id', 2)->get();

        foreach ($bully_types as $bully_type) {
            $data['value'][$bully_type->id] = [
                'id' => $bully_type->id,
                'keyword_name' => $bully_type->name,
                'data' => [0, 0, 0]
            ];
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1, 2]);

        if ($this->keyword_id) {
            $raw->where('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $anylsys = [];

        foreach ($items as $item) {
            $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
        }

        foreach ($anylsys as $anylsy) {
            foreach ($bully_types as $bully_type) {

                if ($anylsy['Bully Type'] === $bully_type->name) {
                    $index_label = array_search($anylsy['Sentiment'], $data['labels']);
                    $data['value'][$bully_type->id]['data'][$index_label] += 1;
                }
            }
        }

        if ($data) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function BullyLevelLevel(Request $request)
    {


        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->where('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        $soures = $this->listSource();
        $anylsys = [];
        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
        }


        foreach ($items as $item) {

            if (isset($anylsys['all'])) {
                $anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$item->source_name]['total'] += 1;
            }

            if (isset($anylsys[$item->classification_name])) {
                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            } else {
                $anylsys[$item->classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $item->classification_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "total" => 1,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['id'] =  $i;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
            }
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                'campaign_id' => $item['campaign_id'],
                'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $value['total'] / $total * 100;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }

        return parent::handleRespond($data);
    }

    public function BullyTableType(Request $request)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);

        if ($this->keyword_id) {
            $raw->where('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $soures = $this->listSource();
        $anylsys = [];
        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
        }


        foreach ($items as $item) {

            if (isset($anylsys['all'])) {
                $anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$item->source_name]['total'] += 1;
            }

            if (isset($anylsys[$item->classification_name])) {
                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            } else {
                $anylsys[$item->classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $item->classification_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "total" => 1,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['id'] =  $i;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
            }
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                'campaign_id' => $item['campaign_id'],
                'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $value['total'] / $total * 100;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }

//        $data = [
//            [
//                "id" => 1,
//                "keyword_name" => "all",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ],
//            [
//                "id" => 1,
//                "keyword_name" => "No Bully",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ],
//            [
//                "id" => 1,
//                "keyword_name" => "Gossip",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ],
//            [
//                "id" => 1,
//                "keyword_name" => "Harassment",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ],
//            [
//                "id" => 1,
//                "keyword_name" => "Exclusion",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ],
//            [
//                "id" => 1,
//                "keyword_name" => "Hate Speech",
//                "campaign_id" => 2,
//                "campaign_name" => "ข่าวบันเทิง",
//                "organization_id" => 1,
//                "organizations_name" => "test",
//                "value" => [
//                    [
//                        "channel" => "facebook",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "twitter",
//                        "percentage" => "30"
//                    ],
//                    [
//                        "channel" => "youtube",
//                        "percentage" => "10"
//                    ],
//                    [
//                        "channel" => "instagram",
//                        "percentage" => "20"
//                    ],
//                    [
//                        "channel" => "pantip",
//                        "percentage" => "15"
//                    ]
//                ]
//            ]
//        ];

        return parent::handleRespond($data);
    }

    private function listSource()
    {
        $sources = Sources::all();
        $data['labels'] = [];

        foreach ($sources as $source) {
            $data['labels'][] = $source->name;
        }

        return $data;
    }
}
