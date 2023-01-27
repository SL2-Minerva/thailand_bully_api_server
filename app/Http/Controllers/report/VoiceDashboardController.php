<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use Illuminate\Support\Carbon;
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

        if (!$request->campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source_id;
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
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
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
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

    public function DailyMessage(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data = [];

        $items = $raw->get();

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;
            $date_m = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$keyword_id])) {
                if (isset($data[$keyword_id][$date_m])) {
                    $data[$keyword_id]['value'][$date_m]['total_at_date'] += 1;
                } else {
                    $data[$keyword_id]['value'][$date_m] = [
                        'date' => $date_m,
                        'total_at_date' => 1
                    ];
                }
            } else {

                $nestData = [
                    'source_id' => $item->source_id,
                    'source_name' => $item->source_name,
                    'date_m' => $date_m,
                    'total_at_date' => 1
                ];
                $data[$keyword_id] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => $item->keyword_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "source_id" => $item->source_id,
                    "source_name" => $item->source_name,
                ];

                $data[$keyword_id]['value'][$date_m] = $nestData;
            }

        }

        foreach ($data as $k => $value) {
            if ($value['value']) {
                $data[$k]['value'] = array_values($value['value']);
            }
        }

        if ($data) {
            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    /**
     * prepare function for group
     * @param Request $request
     * @return void
     */
    public function messageBy(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data = [];

        $items = $raw->get();

        $data['messageByDay'] = $this->messageByDay($items);
        $data['messageByTime'] = $this->messageByTime($items);
        $data['messageByDevice'] = $this->messageByDevice($items);
        $data['messageByAccount'] = $this->messageByAccount($items);
        $data['messageByChannel'] = $this->MessageByChannel($items);
        $data['messageBySentiment'] = $this->messageBySentiment($items);
        $data['messageByLevel'] = $this->messageByLevel();
        $data['messageByType'] = $this->messageByType();

        return parent::handleRespond($data);

    }

    private function messageByDay($items)
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

        $data['value'] = null;

        if ($items) {
            foreach ($items as $item) {
                $day_name = Carbon::parse($item->date_m)->format('D');
                $index_label = array_search($day_name, $data['labels']);

                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        "id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "campaign_id" => $item->campaign_id,
                        "campaign_name" => $item->campaign_name,
                        'data' => [0, 0, 0, 0, 0, 0, 0]
                    ];

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }
        }


        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function MessageByDayold(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        $data = $this->messageByDay($items);

        return parent::handleRespond($data);
    }

    private function messageByTime($items)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'] = null;

        if ($items) {
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
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function MessageByTimeold(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data = [];

        $items = $raw->get();

//        $table =  'daily_message_device_d_m_y_h_i_s';
//        $data = $this->listDataByType('time', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );
//
        return parent::handleRespond($this->messageByTime($items));
    }

    private function messageByDevice($items)
    {
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        if ($items) {
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
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }


            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }


    public function MessageByDeviceold(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        return parent::handleRespond($this->messageByDevice($items));


//        $table =  'daily_message_device';
//        $data = $this->listDataByType('device', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, null, null );
//
//        return parent::handleRespond($data);
    }


    private function messageByAccount($items)
    {
        $data['labels'] = [
            "Post Owner",
            "Follower",
        ];

        if ($items) {

            foreach ($items as $item) {

                if (isset($data['value'][$item->keyword_id])) {
                    if (!$item->reference_message_id && ($item->message_type === 'Post')) {
                        $data['value'][$item->keyword_id]['data'][0] += 1;
                    } else {
                        $data['value'][$item->keyword_id]['data'][1] += 1;
                    }

                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                        'data' => [0, 0]
                    ];

                    if (!$item->reference_message_id && ($item->message_type === 'Post')) {
                        $data['value'][$item->keyword_id]['data'][0] += 1;
                    } else {
                        $data['value'][$item->keyword_id]['data'][1] += 1;
                    }
                }
            }
        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    public function MessageByAccountold(Request $request)
    {
        $data['labels'] = [
            "Post Owner",
            "Follower",
        ];

//        $table = 'sna_root_node';
//
//        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
//
//        $infulencers = $infulencer_root->get();
//
//
//        foreach ($infulencers as $infulencer) {
//
//            if (isset($data['value'][$infulencer->keyword_id]['data'][0])) {
//                $data['value'][$infulencer->keyword_id]['data'][0] += 1;
//            } else {
//                $data['value'][$infulencer->keyword_id]['id'] = $infulencer->keyword_id;
//                $data['value'][$infulencer->keyword_id]['keyword_name'] = $infulencer->keyword_name;
//                $data['value'][$infulencer->keyword_id]['data'][0] = 0;
//
//            }
//
//        }
//
//        $table = 'sna_child_node';
//        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
//
//
//        $followers = $follower_raw->get();
//
//
//        foreach ($followers as $follower) {
//
//            if (isset($data['value'][$follower->keyword_id]['data'][1])) {
//                $data['value'][$follower->keyword_id]['data'][1] += 1;
//            } else {
//                $data['value'][$follower->keyword_id]['id'] = $follower->keyword_id;
//                $data['value'][$follower->keyword_id]['keyword_name'] = $follower->keyword_name;
//                $data['value'][$follower->keyword_id]['data'][1] = 0;
//            }
//
//        }
//
//        if (isset($data['value'])) {
//            $data['value'] = array_values($data['value']);
//        }


        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        return parent::handleRespond($this->messageByAccount($items));
    }


    private function messageByChannel($items)
    {

        $data = parent::listSource();

        if ($items) {
            foreach ($items as $item) {
                $index_label = array_search($item->source_name, $data['labels']);
                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'name' => $item->keyword_name,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name
                    ];

                    for ($i = 0; $i <= count($data['labels']); $i++) {
                        $data['value'][$item->keyword_id]['data'][] = 0;
                    }

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function MessageByChannelold(Request $request)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        return parent::handleRespond($this->messageByChannel($items));
    }

    private function messageBySentiment($items)
    {

        $labels = [
            "Positive",
            "Neutral",
            "Negative"
        ];

        $data['labels'] = $labels;
        if ($items) {
            foreach ($items as $item) {

                $index_label = array_search($item->classification_name, $labels);

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
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function MessageBySentimentold(Request $request)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        return parent::handleRespond($this->messageBySentiment($items));

    }

    private function messageByLevel($raw = null)
    {
        $data = [];

        if (!$raw) {
            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [3]);

        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }


        $items = $raw->get();


        $classifications = Classification::where('classification_type_id', 3)->get();

        foreach ($classifications as $classification) {
            $data['labels'][] = $classification->name;
        }

        foreach ($items as $item) {
            $index_label = array_search($item->classification_name, $data['labels']);
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    public function MessageByLevelold(Request $request)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);


//        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date])
//            ->where('classification_type_id', 3);
//
//        $level = Classification::where('classification_type_id', 3)->get();
//        $data['labels'] = [];
//
//        foreach ($level as $item) {
//            $data['labels'][] = $item->name;
//        }
//
//        foreach ($items->get() as $item) {
//
//            $index_label = 0;
//            $index_label = array_search($item->classification_name, $data['labels']);
//
//
//            if (isset($data['value'][$item->keyword_id])) {
//                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
//            } else {
//                $data['value'][$item->keyword_id] = [
//                    'id' => $item->keyword_id,
//                    'keyword_id' => $item->keyword_id,
//                    'keyword_name' => $item->keyword_name,
//                    'data' => [0, 0, 0, 0]
//                ];
//
//                $data['value'][$item->keyword_id]['data'][$index_label] += $item->total_at_date;
//            }
//
//        }
//
//
//        if (isset($data['value'])) {
//            $data['value'] = array_values($data['value']);
//        }

        return parent::handleRespond($this->messageByLevel($raw));
    }


    private function messageByType($raw = null)
    {
        $data = [];

        if (!$raw) {
            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [2]);

        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }


        $items = $raw->get();


        $classifications = Classification::where('classification_type_id', 2)->get();
        foreach ($classifications as $classification) {
            $data['labels'][] = $classification->name;
        }

        foreach ($items as $item) {

            $index_label = array_search($item->classification_name, $data['labels']);
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function MessageByTypeold(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);

        return parent::handleRespond($this->messageByType($raw));
    }


    public function ChannelDevice(Request $request)
    {
        $data = null;
        $labels = parent::listSource();
        $devices = ['Android', 'Iphone', 'Web app'];

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


        foreach ($labels['labels'] as $label) {
            foreach ($devices as $device) {
                $data['labels'][$label.'-'.$device] = [$label, $device];
                $data['data'][$label.'-'.$device] = 0;
            }
        }


        foreach ($items as $item) {
            $device = 'empty';

            if ($item->device === 'android') {
                $device = 'Android';
            } else if ($item->device === 'iphone') {
                $device = 'Iphone';
            } else if ($item->device === 'webapp') {
                $device = 'Web app';
            }

            if ($device !== 'empty') {
                if (isset($data['data'][$item->source_name.'-'.$device])) {
                    $data['data'][$item->source_name.'-'.$device] += 1;
                } else {
                    $data['data'][$item->source_name.'-'.$device] = 1;
                }
            }
        }

        if (isset($data['data'])) {
            $data['data'] = array_values($data['data']);
        }

        if (isset($data['labels'])) {
            $data['labels'] = array_values($data['labels']);
        }

        return parent::handleRespond($data);
    }

    public function KeywordSentiment(Request $request)
    {
        $data = null;
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
//
        $items = $raw->get();
//
//        foreach ($items as $item) {
//            if ($data[''])
//        }
//

//        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date])
//            ->where('classification_type_id', 1);
//
//        $level = Classification::where('classification_type_id', 1)->get();
//        $data['labels'] = [];
//
//        foreach ($level as $item) {
//            $data['labels'][] = $item->name;
//        }
//
//        foreach ($items->get() as $item) {
//
//            $index_label = 0;
//            $index_label = array_search($item->classification_name, $data['labels']);
//
//
//            if (isset($data['value'][$item->keyword_id])) {
//                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
//            } else {
//                $data['value'][$item->keyword_id] = [
//                    'id' => $item->keyword_id,
//                    'keyword_id' => $item->keyword_id,
//                    'keyword_name' => $item->keyword_name,
//                    'data' => [0, 0, 0]
//                ];
//
//                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
//            }
//
//        }
//
//
//        if (isset($data['value'])) {
//            $data['value'] = array_values($data['value']);
//        }

        return parent::handleRespond($this->getKeywordSentiment(['items' => $items, 'raw' => $raw]));
    }

    public function KeywordBullyLevel(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_wherein = $raw->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        return parent::handleRespond($this->getKeywordBullyLevel(['items' => $items, 'raw' => $raw]));
    }

    public function KeywordBullyType(Request $request)
    {
        return parent::handleRespond($this->getKeywordBullyType());
    }

    public function KeywordChannel(Request $request)
    {

//        $items = MessageResultBully::where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
//
//        $level = Sources::where('status', 1)->get();
//        $data['labels'] = [];
//
//        foreach ($level as $item) {
//            $data['labels'][] = $item->name;
//        }
//
//        foreach ($items->get() as $item) {
//            $index_label = 0;
//            $index_label = array_search($item->source_name, $data['labels']);
//
//
//            if (isset($data['value'][$item->keyword_id])) {
//                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
//            } else {
//                $data['value'][$item->keyword_id] = [
//                    'id' => $item->keyword_id,
//                    'keyword_id' => $item->keyword_id,
//                    'keyword_name' => $item->keyword_name,
//                    'data' => [0, 0, 0, 0, 0, 0]
//                ];
//
//                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
//            }
//
//        }
//
//
//        if (isset($data['value'])) {
//            $data['value'] = array_values($data['value']);
//        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_wherein = $raw->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();


        return parent::handleRespond($this->getKeywordChannel(['raw' => $raw_wherein, 'items' => $items]));
    }


    public function NumberOfAccountPeriodOverPeriod(Request $request)
    {
        $data['numberOfAccount'] = $this->factoryNumberOfAccountPeriodOverPeriod('numberOfAccount');
        $data['PeriodOverPeriod'] = $this->factoryNumberOfAccountPeriodOverPeriod('PeriodOverPeriod');

        return parent::handleRespond($data);
    }

    private function factoryNumberOfAccountPeriodOverPeriod($type = null)
    {
        if ($type === 'numberOfAccount') {
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


            if ($data) {

                foreach ($data as $keyword_id => $item) {

                    $data[$keyword_id]['date'] = array_values($item['date']);
                    $data[$keyword_id]['data'] = array_values($item['data']);
                }

                $data = array_values($data);
            }


            return $data;
        }

        if ($type === 'PeriodOverPeriod') {
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
                "type" => $total_message_current - $total_message_previous > 0 ? "plus" : "minus",
            ];
//
            $data['total_account'] = [
                "total_message" => $total_account_current,
                "percentage" => parent::point_two_digits($total_account_previous !== 0 ? ($total_account_current - $total_account_previous) / $total_account_previous * 100 : 0),
                "type" => $total_account_current - $total_account_previous > 0 ? "plus" : "minus",
            ];

            return $data;
        }
    }

    public function NumberOfAccount(Request $request)
    {
        return parent::handleRespond($this->factoryNumberOfAccountPeriodOverPeriod('numberOfAccount'));
    }


    public function PeriodOverPeriod(Request $request)
    {
        return parent::handleRespond($this->factoryNumberOfAccountPeriodOverPeriod('PeriodOverPeriod'));
    }

    public function DayTimeComparison(Request $request)
    {
        $data = null;
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

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


        if ($data) {

            foreach ($data as $key => $value) {
                $data[$key]["data"] = array_values($value["data"]);
            }

            $data = array_values($data);
        }

        return parent::handleRespond($data);
    }

    public function DayTimeSentiment(Request $request)
    {
        $data = null;
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        $items = $raw_current->get();

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

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


        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }

            foreach ($data['day_value'] as $key => $value) {
                $data['day_value'][$key]["data"] = array_values($value["data"]);
            }

            foreach ($data['time_value'] as $key => $value) {
                $data['time_value'][$key]["data"] = array_values($value["data"]);
            }
        }


        return parent::handleRespond($data);
    }

    public function DayTimeLevel(Request $request)
    {
        $data = null;
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

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


        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }

            foreach ($data['day_value'] as $key => $value) {
                $data['day_value'][$key]["data"] = array_values($value["data"]);
            }

            foreach ($data['time_value'] as $key => $value) {
                $data['time_value'][$key]["data"] = array_values($value["data"]);
            }
        }


        return parent::handleRespond($data);
    }

    public function DayTimeType(Request $request)
    {

        $data = null;
        $raw_current = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);

        if ($this->keyword_id) {
            $raw_current->whereIn('keyword_id', $this->keyword_id);
        }

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


        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key] = array_values($value);
            }

            foreach ($data['day_value'] as $key => $value) {
                $data['day_value'][$key]["data"] = array_values($value["data"]);
            }

            foreach ($data['time_value'] as $key => $value) {
                $data['time_value'][$key]["data"] = array_values($value["data"]);
            }
        }


        return parent::handleRespond($data);
    }


    public function channelPlatformChannelDevice(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }


        $data['channelPlatform'] = $this->getChannelPlatform(['raw' => $raw]);
        $data['device'] = $this->getDevice(['raw' => $raw]);
        $data['channelDevice'] = $this->getChannelDevice(['raw' => $raw]);
        return parent::handleRespond($data);
    }

    public function ChannelPlatform(Request $request)
    {

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        return parent::handleRespond($this->getChannelPlatform(['raw' => $raw]));
    }

    private function getChannelPlatform($condition = null)
    {
        $data = null;
        $labels = parent::listSource();
        $raw = $condition['raw'] ?? null;
        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $items_previous = $raw_previous->get();

        $data['current_period']['label'] = $labels['labels'];
        $data['previous_period']['label'] = $labels['labels'];
        $data['current_period']['total'] = 0;
        $data['previous_period']['total'] = 0;

        for ($i = 0; $i < count($labels['labels']); $i++) {
            $data['current_period']['data'][] = 0;
            $data['previous_period']['data'][] = 0;
        }

        if ($items) {
            foreach ($items as $item) {
                $index_label = array_search($item->source_name, $data['current_period']['label']);
                $data['current_period']['data'][$index_label] += 1;
                $data['current_period']['total'] += 1;
            }
        }

        if ($items_previous) {
            foreach ($items as $item) {
                $index_label = array_search($item->source_name, $data['previous_period']['label']);
                $data['previous_period']['data'][$index_label] += 1;
                $data['previous_period']['total'] += 1;
            }
        }

        return $data;
    }

    private function getDevice($condition = null)
    {
        $data = null;
        $labels = ["labels" => ['Andriod', 'Iphone', 'Web App']];
        $raw = $condition['raw'] ?? null;
        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $items_previous = $raw_previous->get();

        $data['current_period']['label'] = $labels['labels'];
        $data['previous_period']['label'] = $labels['labels'];
        $data['current_period']['total'] = 0;
        $data['previous_period']['total'] = 0;

        for ($i = 0; $i < count($labels['labels']); $i++) {
            $data['current_period']['data'][] = 0;
            $data['previous_period']['data'][] = 0;
        }

        if ($items) {
            foreach ($items as $item) {


                if ($item->device === "android") {
                    $data['current_period']['data'][0] += 1;
                }
                if ($item->device === "iphone") {
                    $data['current_period']['data'][1] += 1;
                }
                if ($item->device === "webapp") {
                    $data['current_period']['data'][2] += 1;
                }

                $data['current_period']['total'] += 1;
            }
        }

        if ($items_previous) {
            foreach ($items as $item) {
                if ($item->device === "android") {

                    $data['previous_period']['data'][0] += 1;
                }
                if ($item->device === "iphone") {
                    $data['previous_period']['data'][1] += 1;
                }
                if ($item->device === "webapp") {
                    $data['previous_period']['data'][2] += 1;
                }

                $data['previous_period']['total'] += 1;
            }
        }

        return $data;
    }


    public function Device(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


//        $data['previous_period']['label'] = [
//            "Andriod",
//            "Iphone",
//            "Web App"
//        ];
//
//        $data['current_period']['label'] = [
//            "Andriod",
//            "Iphone",
//            "Web App"
//        ];
//
//        $data['previous_period']['data'] = [0, 0, 0];
//        $data['current_period']['data'] = [0, 0, 0];
//
//        $items_current = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date, $this->end_date]);
//        $items_previous = DB::table('daily_message_device')->where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);
//
//        foreach ($items_current->get() as $item) {
//            if ($item->device === "android") {
//                $data['current_period']['data'][0] += $item->total_at_date;
//            }
//            if ($item->device === "iphone") {
//                $data['current_period']['data'][1] += $item->total_at_date;
//            }
//            if ($item->device === "webapp") {
//                $data['current_period']['data'][2] += $item->total_at_date;
//            }
//        }
//        $data['current_period']['total'] = array_sum($data['current_period']['data']);
//
//
//        foreach ($items_previous->get() as $item) {
//            if ($item->device === "android") {
//                $data['previous_period']['data'][0] += $item->total_at_date;
//            }
//            if ($item->device === "iphone") {
//                $data['previous_period']['data'][1] += $item->total_at_date;
//            }
//            if ($item->device === "webapp") {
//                $data['previous_period']['data'][2] += $item->total_at_date;
//            }
//        }
//        $data['previous_period']['total'] = array_sum($data['previous_period']['data']);

        return parent::handleRespond($this->getDevice(['raw' => $raw]));
    }


    private function getChannelDevice($conditions = null)
    {

        $data = null;
        $labels = parent::listSource();
        $devices = ['Android', 'Iphone', 'Web app'];

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


        foreach ($labels['labels'] as $label) {
            foreach ($devices as $device) {
                $data['labels'][$label.'-'.$device] = [$label, $device];
                $data['data'][$label.'-'.$device] = 0;
            }
        }


        foreach ($items as $item) {
            $device = 'empty';

            if ($item->device === 'android') {
                $device = 'Android';
            } else if ($item->device === 'iphone') {
                $device = 'Iphone';
            } else if ($item->device === 'webapp') {
                $device = 'Web app';
            }

            if ($device !== 'empty') {
                if (isset($data['data'][$item->source_name.'-'.$device])) {
                    $data['data'][$item->source_name.'-'.$device] += 1;
                } else {
                    $data['data'][$item->source_name.'-'.$device] = 1;
                }
            }
        }

        if (isset($data['data'])) {
            $data['data'] = array_values($data['data']);
        }

        if (isset($data['labels'])) {
            $data['labels'] = array_values($data['labels']);
        }

        return $data;
    }

    public function keywordBy(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);


        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();

        $data['keyword-channel'] = $this->getKeywordChannel(['raw' => $raw, 'items' => $items]);
        $data['keyword-sentiment'] = $this->getKeywordSentiment(['raw' => $raw, 'items' => $items]);
        $data['keyword-bully-level'] = $this->getKeywordBullyLevel(['raw' => $raw, 'items' => $items]);
        $data['keyword-bully-type'] = $this->getKeywordBullyType(['raw' => $raw, 'items' => $items]);

        return parent::handleRespond($data);

    }

    private function getKeywordChannel($conditions = null)
    {

        $data = parent::listSource();
        $raw = $conditions['raw'] ?? null;
        $meesage_total = 0;
//        $raw_previous = DB::table('message_result_full_data')
//            ->where('campaign_id', $this->campaign_id)
//            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
//            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
//            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        if ($items) {
            foreach ($items as $item) {
                $meesage_total += 1;
                $index_label = array_search($item->source_name, $data['labels']);
                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_id' => $item->keyword_name,
                        'keyword_name' => $item->keyword_name
                    ];

                    for ($i = 0; $i < count($data['labels']); $i++) {
                        $data['value'][$item->keyword_id]['data'][] = 0;
                    }

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function getKeywordSentiment($condition = null)
    {
        $data = null;
        $raw = $condition['raw'] ?? null;

        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [1]);

        if (isset($condition['items']) && $condition['items']) {
            $items = $condition['items'];
        } else {
            if ($this->keyword_id) {
                $raw->whereIn('keyword_id', $this->keyword_id);
                $raw_previous->whereIn('keyword_id', $this->keyword_id);
            }
            $items = $raw->get();
        }

        $sentiments = Classification::where('classification_type_id', 1)->get();
        $message_total = 0;

        foreach ($sentiments as $item) {
            $data['labels'][] = $item->name;
        }

        if ($items) {
            foreach ($items as $item) {
                $index_label = array_search($item->classification_name, $data['labels']);
                $message_total += 1;
                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_id' => $item->keyword_id,
                        'keyword_name' => $item->keyword_name,
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function getKeywordBullyLevel($condition = null)
    {
        $data = null;
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);
        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $levels = Classification::where('classification_type_id', 3)->get();
        $message_total = 0;

        foreach ($levels as $item) {
            $data['labels'][] = $item->name;
        }

        $items = $raw->get();
        $items_previous = $raw_previous->get();

        foreach ($items as $item) {
            $index_label = array_search($item->classification_name, $data['labels']);
            $message_total += 1;
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function getKeywordBullyType($condition = null)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);
        $raw_previous = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_type_id', [2]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $raw_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $levels = Classification::where('classification_type_id', 2)->get();
        $message_total = 0;

        foreach ($levels as $item) {
            $data['labels'][] = $item->name;
        }

        $items = $raw->get();
        $items_previous = $raw_previous->get();

        foreach ($items as $item) {
            $index_label = array_search($item->classification_name, $data['labels']);
            $message_total += 1;
            if (isset($data['value'][$item->keyword_id])) {
                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->keyword_id] = [
                    'id' => $item->keyword_id,
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                ];

                for ($i = 0; $i < count($data['labels']); $i++) {
                    $data['value'][$item->keyword_id]['data'][] = 0;
                }

                $data['value'][$item->keyword_id]['data'][$index_label] += 1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }


}
