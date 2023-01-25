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
    private $keyword_id;

    public function __construct(Request $request)
    {
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source_id;
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',' , $fillter_keywords);
        }
    }
    public function PercentageOfMessage(Request $request)
    {
        $data = null;
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    private function percentageOfMessages($start_date, $end_date)
    {


        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $message_total = 0;
        $message_keywords = [];
        $data = [];
        foreach ($items as $item) {
            $message_total += 1;

            if (isset($message_keywords[$item->keyword_id])) {
                $message_keywords[$item->keyword_id]['total'] += 1;
            } else {
                $message_keywords[$item->keyword_id] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' =>  $item->campaign_id,
                    'campaign_name' =>  $item->campaign_name,
                ];
                $message_keywords[$item->keyword_id]['total'] = 1;
            }
        }

        foreach ($message_keywords as $keyword_id => $message_keyword) {

            $data[$keyword_id]['keyword_id'] = $message_keyword['keyword_id'];
            $data[$keyword_id]['keyword_name'] = $message_keyword['keyword_name'];
            $data[$keyword_id]['campaign_id'] = $message_keyword['campaign_id'];
            $data[$keyword_id]['campaign_name'] = $message_keyword['campaign_name'];
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => self::point_two_digits(($message_keyword['total'] / $message_total ?? 1) * 100),
                'total' => $message_keyword['total']

            ];
        }

        if ($data) {
            $data = array_values($data);
        }

        return $data;
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
        return parent::handleRespond($this->getDataByCondition($table, $campaign_id, $request->start_date, $request->end_date, null, $source, $column, 'daily_message'));
    }

    public function MessageByDay(Request $request)
    {

        $table =  'daily_message';
        $data = $this->listDataByType('dayname', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageByTime(Request $request)
    {
        $table =  'daily_message_device_d_m_y_h_i_s';
        $data = $this->listDataByType('time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

        return parent::handleRespond($data);
    }

    public function MessageByDevice(Request $request)
    {

        $table =  'daily_message_device';
        $data = $this->listDataByType('device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

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
        $data = $this->listDataByType('channel', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );

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

        $data = [];

        $items = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('device', 'source_name');

        foreach ($items->get() as $item) {
            $data['labels'][] = [
                $item->device !== "" ? $item->device : "unknow",
                $item->source_name,
            ];

            $data['data'][] =  DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->where('device', $item->device)
                ->where('source_name', $item->source_name)
                ->sum('total_at_date');
        }

        return parent::handleRespond($data);
    }

    public function KeywordSentiment(Request $request)
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
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0,0,0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

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

//    private function getDataByCondition(
//        $table,
//        $campaign_id,
//        $start_date,
//        $end_date,
//        $keyword_id = null,
//        $source_id = null,
//        $column = null,
//        $type = null, $condition = null)
//    {
//
//
//        $items = DB::table($table)
//            ->where('campaign_id', $campaign_id)
//            ->whereBetween('date_m', [$start_date, $end_date]);
//
//        if (isset($condition['group_by'])) {
//
//            foreach ($condition['group_by'] as $groupBy) {
//                $items->groupBy($groupBy);
//            }
//        }
//
//        if ($keyword_id) {
//            $items->where('keyword_id', $keyword_id);
//        }
//
//
//        if ($source_id && $source_id !== 'all') {
//            $items->where('source_id', $source_id);
//        }
//
//
//        return $this->factorListData($items->get(), $type, $campaign_id, $start_date, $end_date, $keyword_id, $table, $column, $condition);
//    }
//
//    private function factorListData($items, $type, $campaign_id = null, $start_date = null, $end_date = null, $keyword_id = null, $table = null, $column = null, $condition = null)
//    {
//
//        $data = null;
//
//        if ($type === 'percentage') {
//            $data = $this->findPercentage($items, $column, $start_date, $end_date);
//        }
//
//        foreach ($items as $item) {
//            $keyword_id = $item->keyword_id;
//
//            if ($type !== 'engagement') {
//                $data[$keyword_id]['keyword_id'] = $item->keyword_id;
//                $data[$keyword_id]['keyword_name'] = $item->keyword_name;
//                $data[$keyword_id]['campaign_id'] = $item->campaign_id;
//                $data[$keyword_id]['campaign_name'] = $item->campaign_name;
//            }
//
//            if ($type !== 'engagement' && isset($item->source_id) && $item->source_id) {
//                $data[$keyword_id]['source_id'] = $item->source_id;
//                $data[$keyword_id]['source_name'] = $item->source_name;
//            }
//
//            if ($type === 'source' || $type === 'daily_message') {
//                $nestData = [
//                    'source_id' => $item->source_id,
//                    'source_name' => $item->source_name,
//                    'date_m' => $item->date_m,
//                    'total_at_date' => $item->total_at_date
//                ];
//
//                $data[$keyword_id]['value'][] = $nestData;
//            }
//
//            if ($type === 'engagement') {
//
//                $nestData['keyword_id'] = $item->keyword_id;
//                $nestData['keyword_name'] = $item->keyword_name;
//                $nestData['campaign_id'] = $item->campaign_id;
//                $nestData['campaign_name'] = $item->campaign_name;
//
//
//                if (isset($nestData["value"][$item->source_id])) {
//                    $nestData["value"][$item->source_id][] = [
//                        'source_id' => $item->source_id,
//                        'source_name' => $item->source_name,
//                        'date_m' => $item->date_m,
//                        'total_at_date' => (int)$item->engagement
//                    ];
//                } else {
//                    $nestData["value"][$item->source_id] = [
//                        'source_id' => $item->source_id,
//                        'source_name' => $item->source_name,
//                        'date_m' => $item->date_m,
//                        'total_at_date' => (int)$item->engagement
//                    ];
//                }
//
//
//                if (isset($data[$item->keyword_id])) {
//                    $data[$item->keyword_id]['value'][] = [
//                        'source_id' => $item->source_id,
//                        'source_name' => $item->source_name,
//                        'date_m' => $item->date_m,
//                        'total_at_date' => (int)$item->engagement
//                    ];
//                } else {
//                    $data[$item->keyword_id] = $nestData;
//                }
//
//                if (isset($data[$item->keyword_id]['value'])) {
//                    $data[$item->keyword_id]['value'] = array_values($data[$item->keyword_id]['value']);
//                }
//
//            }
//
//            if ($type === 'shareofvoice') {
//                $message = $this->shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
//                $total_message = DB::table($table)->where('campaign_id', $campaign_id)
//                    ->where('keyword_id', $item->keyword_id)
//                    ->where('organization_id', $item->organization_id)
//                    ->where('campaign_name', $item->campaign_name)
//                    ->whereBetween('date_m', [$start_date, $end_date])
//                    ->sum($column);
//
//                $percentage = ($message / $total_message) * 100;
//
//                $push_data = [
//                    'channel' => $item->source_name,
//                    'percentage' => self::point_two_digits($percentage),
//                    'number_of_message' => $message,
//                    // 'highlight' =>
//                ];
//
//                $data[$keyword_id]['value'][] = $push_data;
//            }
//
//            if ($type === '') {
//
//            }
//
//        }
//
//
//        if ($data) {
//            return array_values($data);
//        }
//
//        return $data;
//
//    }

    private function shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $total_message = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->where('source_id', $source_id)
            ->sum('total_at_date');

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


    public function NumberOfAccount(Request $request)
    {
        $data = null;

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('message_type', 'Post')
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        $items = $raw->get();
        $date = [];

        foreach ($items as $item) {

            $date_format = Carbon::parse($item->date_m)->format('m/d/Y');
            // date
            if (!isset($data[$date_format])) {
                $date[$date_format] = 1;
            }

            if (isset($data[$item->keyword_id])) {

                if (isset($data[$item->keyword_id]['data'][$date_format])) {
                    $data[$item->keyword_id]['data'][$date_format] += 1;
                } else {
                    $data[$item->keyword_id]['data'][$date_format] = 1;
                }

                $data[$item->keyword_id]['date'][$date_format] = $date_format;
            } else {
                $data[$item->keyword_id] = [
                    "name" => $item->keyword_name,
                ];

                if (isset($data[$item->keyword_id]['data'][$date_format])) {
                    $data[$item->keyword_id]['data'][$date_format] += 1;
                } else {
                    $data[$item->keyword_id]['data'][$date_format] = 1;
                }


                if (!isset($data[$item->keyword_id]['date'][$date_format])) {
                    $data[$item->keyword_id]['date'][$date_format] = $date_format;
                }
            }
        }



        foreach ($data as $keyword_id => $item) {

            $data[$keyword_id]['date'] = array_values($item['date']);
            $data[$keyword_id]['data'] = array_values($item['data']);
        }

        if ($data) {
            $data = array_values($data);
        }

//        $data[] = [
//            "name" =>  "Keyword 1",
//            "data" => [
//                44,
//                55,
//                41,
//                67,
//                22,
//                43,
//                21,
//                49,
//                29,
//                36,
//            ],
//            "date" => [
//                "01/10",
//                "02/10",
//                "03/10",
//                "04/10",
//                "05/10",
//                "06/10",
//                "07/10",
//                "08/10",
//                "09/10",
//                "10/10",
//            ]
//        ];
//
//        $data[] = [
//            "name" =>  "Keyword 2",
//            "data" => [
//                13,
//                23,
//                20,
//                8,
//                13,
//                27,
//                33,
//                12,
//                29,
//                34,
//            ],
//            "date" => [
//                "01/10",
//                "02/10",
//                "03/10",
//                "04/10",
//                "05/10",
//                "06/10",
//                "07/10",
//                "08/10",
//                "09/10",
//                "10/10",
//            ]
//        ];
//
//        $data[] = [
//            "name" =>  "Keyword 3",
//            "data" => [
//                11,
//                17,
//                15,
//                15,
//                21,
//                14,
//                15,
//                13,
//                65,
//                29,
//            ],
//            "date" => [
//                "01/10",
//                "02/10",
//                "03/10",
//                "04/10",
//                "05/10",
//                "06/10",
//                "07/10",
//                "08/10",
//                "09/10",
//                "10/10",
//            ]
//        ];
//
//        $data[] = [
//            "name" =>  "Keyword 4",
//            "data" => [
//                44,
//                55,
//                41,
//                67,
//                22,
//                43,
//                21,
//                49,
//                58,
//                37,
//            ],
//            "date" => [
//                "01/10",
//                "02/10",
//                "03/10",
//                "04/10",
//                "05/10",
//                "06/10",
//                "07/10",
//                "08/10",
//                "09/10",
//                "10/10",
//            ]
//        ];
//
//        $data[] = [
//            "name" =>  "Keyword 5",
//            "data" => [
//                30,
//                23,
//                20,
//                8,
//                13,
//                27,
//                33,
//                12,
//                62,
//                43,
//            ],
//            "date" => [
//                "01/10",
//                "02/10",
//                "03/10",
//                "04/10",
//                "05/10",
//                "06/10",
//                "07/10",
//                "08/10",
//                "09/10",
//                "10/10",
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);


        $items_current = $raw_current->get();
        $items_previous = $raw_previous->get();

        $total_message_current = 0;
        $total_message_previous = 0;

        $total_account_current = [];
        $total_account_previous = [];


        foreach ($items_current as $current) {
            $total_message_current += 1;
            if (isset($total_account_current[$current->author])) {
                $total_account_current[$current->author] += 1;
            } else {
                $total_account_current[$current->author] = 1;
            }

        }


        foreach ($items_previous as $previous) {
            $total_message_previous += 1;
            if (isset($total_account_previous[$previous->author])) {
                $total_account_previous[$previous->author] = $previous->author;
            } else {
                $total_account_previous[$previous->author] = $previous->author;
            }

        }



        $date = [];

        $total_account_current = count($total_account_current);
        $total_account_previous = count($total_account_previous);

        $data['total_messages'] = [
            "total_message" => $total_message_current,
            "percentage" => parent::point_two_digits($total_message_previous !== 0 ? ($total_message_current - $total_message_previous) / $total_message_previous * 100 : 0),
            "type" => $total_message_current - $total_message_previous > 0 ? "plus" :"minus",
        ];
//
        $data['total_account'] = [
            "total_message" => $total_account_current,
            "percentage" => parent::point_two_digits($total_account_previous !== 0 ? ($total_account_current - $total_account_previous) / $total_account_previous * 100 : 0),
            "type" => $total_account_current - $total_account_previous > 0 ? "plus" :"minus",
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeComparison(Request $request)
    {

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        $items = $raw_current->get();

        foreach ($items as $item) {
            $date_h = Carbon::parse($item->date_m)->format('H');
            $date_d = Carbon::parse($item->date_m)->format('D');
//            dd($item->date_m, $test_h, $test_d);

            if (isset($data[$date_d])) {
                if (isset($data[$date_d]["data"][$date_h])) {
                    $data[$date_d]["data"][$date_h] += 1;
                } else {
                    $data[$date_d]["data"][$date_h] = 1;
                }
            } else {
                $data[$date_d] = [
                    "name" => $date_d,
                ];

                for ($i = 0; $i < 24; $i++) {
                    $data[$date_d]["data"][$i] = 0;
                }

                if (isset($data[$date_d]["data"][$date_h])) {
                    $data[$date_d]["data"][$date_h] += 1;
                } else {
                    $data[$date_d]["data"][$date_h] = 1;
                }


            }
        }


        foreach ($data as $key => $value) {
            $data[$key]["data"] = array_values($value["data"]);
        }

        if ($data) {
            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    public function DayTimeSentiment(Request $request)
    {

        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        $items = $raw_current->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = Carbon::parse($item->date_m)->format('H');

            if (isset($data['day_value'][$item->classification_name])) {
                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }

                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }

            } else {
                $data['day_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                $data['time_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                for ($i = 0; $i < 7; $i++) {
                    $data['day_value'][$item->classification_name]["data"][$i] = 0;
                }

                for ($i = 0; $i < 25; $i++) {
                    $data['time_value'][$item->classification_name]["data"][$i] = 0;
                }

                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }


                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }


            }
        }

        foreach ($data as $key => $value) {
            $data[$key] = array_values($value);
        }

        foreach ($data['day_value'] as $key => $value) {
            $data['day_value'][$key]["data"] = array_values($value["data"]);
        }

        foreach ($data['time_value'] as $key => $value) {
            $data['time_value'][$key]["data"] = array_values($value["data"]);
        }
//        $data['day_value'][] = [
//            "name" =>  "Negative",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70,
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "Neutral",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70,
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "Positive",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20,
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Negative",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Neutral",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Positive",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function DayTimeLevel(Request $request)
    {
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        $items = $raw_current->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = Carbon::parse($item->date_m)->format('H');

            if (isset($data['day_value'][$item->classification_name])) {
                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }

                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }

            } else {
                $data['day_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                $data['time_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                for ($i = 0; $i < 7; $i++) {
                    $data['day_value'][$item->classification_name]["data"][$i] = 0;
                }

                for ($i = 0; $i < 25; $i++) {
                    $data['time_value'][$item->classification_name]["data"][$i] = 0;
                }

                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }


                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }


            }
        }

        foreach ($data as $key => $value) {
            $data[$key] = array_values($value);
        }

        foreach ($data['day_value'] as $key => $value) {
            $data['day_value'][$key]["data"] = array_values($value["data"]);
        }

        foreach ($data['time_value'] as $key => $value) {
            $data['time_value'][$key]["data"] = array_values($value["data"]);
        }

//        $data['day_value'][] = [
//            "name" =>  "level 3",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "level 2",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "level 1",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "level 0",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 74
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "level 3",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "level 2",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "level 1",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "level 0",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function DayTimeType(Request $request)
    {
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);

        $items = $raw_current->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $date_h = Carbon::parse($item->date_m)->format('H');

            if (isset($data['day_value'][$item->classification_name])) {
                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }

                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }

            } else {
                $data['day_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                $data['time_value'][$item->classification_name] = [
                    "name" => $item->classification_name,
                ];

                for ($i = 0; $i < 7; $i++) {
                    $data['day_value'][$item->classification_name]["data"][$i] = 0;
                }

                for ($i = 0; $i < 25; $i++) {
                    $data['time_value'][$item->classification_name]["data"][$i] = 0;
                }

                if ($date_d == "Mon") {
                    $data['day_value'][$item->classification_name]["data"][0] += 1;
                } else if ($date_d == "Tue") {
                    $data['day_value'][$item->classification_name]["data"][1] += 1;
                } else if ($date_d == "Wed") {
                    $data['day_value'][$item->classification_name]["data"][2] += 1;
                } else if ($date_d == "Thu") {
                    $data['day_value'][$item->classification_name]["data"][3] += 1;
                } else if ($date_d == "Fri") {
                    $data['day_value'][$item->classification_name]["data"][4] += 1;
                } else if ($date_d == "Sat") {
                    $data['day_value'][$item->classification_name]["data"][5] += 1;
                } else if ($date_d == "Sun") {
                    $data['day_value'][$item->classification_name]["data"][6] += 1;
                }


                if (isset($data['time_value'][$item->classification_name]["data"][$date_h])) {
                    $data['time_value'][$item->classification_name]["data"][$date_h] += 1;
                } else {
                    $data['time_value'][$item->classification_name]["data"][$date_h] = 1;
                }


            }
        }

        foreach ($data as $key => $value) {
            $data[$key] = array_values($value);
        }

        foreach ($data['day_value'] as $key => $value) {
            $data['day_value'][$key]["data"] = array_values($value["data"]);
        }

        foreach ($data['time_value'] as $key => $value) {
            $data['time_value'][$key]["data"] = array_values($value["data"]);
        }

//        $data['day_value'][] = [
//            "name" =>  "Hate Speech",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "Exclusion",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "Harassment",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "Gossip",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 74
//            ]
//        ];
//
//        $data['day_value'][] = [
//            "name" =>  "No Bully",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70,
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Hate Speech",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Exclusion",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Harassment",
//            "data" => [
//                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "Gossip",
//            "data" => [
//                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
//            ]
//        ];
//
//        $data['time_value'][] = [
//            "name" =>  "No Bully",
//            "data" => [
//                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
//            ]
//        ];

        return parent::handleRespond($data);
    }
}
