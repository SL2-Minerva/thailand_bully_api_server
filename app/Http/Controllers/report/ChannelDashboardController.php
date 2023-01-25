<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use Illuminate\Support\Carbon;
use App\Models\PercentageOfMessages;
use App\Models\DailyMessage;
use App\Models\Message;
use App\Models\MessageResult;
use App\Models\Sources;
use App\Models\MessageResultGroup;
use App\Models\MessageResultSemetic;
use Illuminate\Support\Facades\DB;

class ChannelDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;
    private $source_id;
    private $keyword_id;

    public function __construct(Request $request)
    {
        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source_id;
        
        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',' , $fillter_keywords);
        }

    }

    public function PercentageOfChannel(Request $request)
    {

        $data = null;
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($start_date, $end_date)
    {
        $data = null;
        $percentage_of_channal = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('source_id');

        if ($this->keyword_id) {
            $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
        }

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id = $channal->source_id;
            $data[$source_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id]['campaign_name'] = $channal->campaign_name;
            // $data[$source_id]['organization_id'] = $channal->organization_id;
            // $data[$source_id]['organizations_name'] = $channal->organizations_name;
            $data[$source_id]['source_id'] = $channal->source_id;
            $data[$source_id]['source_name'] = $channal->source_name;

            $channal_message = $this->channelTable($start_date, $end_date, $source_id);
            $channal_message_total = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->whereIn('classification_type_id', [1])
                ->get()
                ->count();


            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $this->point_two_digits(($channal_message / $channal_message_total) * 100),
            ];

            $data[$source_id]['value'][] = $nestData;
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    public function DailyChannel(Request $request)
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $items = $raw->get();
        $data = null;

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->source_id])) {
                
                if (isset($data[$item->source_id]['value'][$date_format])) {
                    $data[$item->source_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->source_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }
               
            } else {
                $data[$item->source_id] = [
                    "source_id" =>  $item->source_id,
                    "source_name" => $item->source_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];
                $data[$item->source_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'date_m' => $item->date_m,
                    'total_at_date' => 1
                ];
            }
        }

     
        foreach($data as $key => $item) {
            if ($item) {
               $data[$key]['value'] = array_values($item['value']);     
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function ChannelByDay(Request $request)
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
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;

        foreach ($raw->get() as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($data['value'][$item->source_id])) {
                $data['value'][$item->source_id]['data'][$index_label] += 1;
            } else {

                $data['value'][$item->source_id] = [
                    'id' => $item->source_id,
                    'name' => $item->source_name,
                    'keyword_name' => $item->source_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function ChannelByTime(Request $request)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;
           
        foreach ($raw->get() as $item) {


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

            if (isset($data['value'][$item->source_id])) {
                $data['value'][$item->source_id]['data'][$index_label] += 1;


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

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function ChannelByDevice(Request $request)
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;

        foreach ($raw->get() as $item) {
            $index_label = 0;

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp') {
                $index_label = 2;
            }

            if (isset($data['value'][$item->source_id])) {
                $data['value'][$item->source_id]['data'][$index_label] += 1;
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

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);

    }

    public function ChannelByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $raw_child = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('reference_message_id', '!=', null)
            ->whereIn('classification_type_id', [1]);


        $raw_root = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->where('reference_message_id','')
            ->orWhere('reference_message_id',null)
            ->whereIn('classification_type_id', [1]);

        $soures = $this->listSource();


        for ($i = 0; $i < count($soures['labels']); $i++) {
            $data['value'][$soures['labels'][$i]] = [
                "id" => $i,
                "keyword_name" => $soures['labels'][$i],
                'data' => [0, 0]
            ];

        }

//        if ($this->keyword_id) {
//            $raw_child->where('keyword_id', $this->keyword_id);
//            $raw_root->where('keyword_id', $this->keyword_id);
//        }

        $items_root = $raw_root->get();
        $items_child = $raw_child->get();

        foreach ($items_root as $key => $item) {
            $source_id = $item->source_id;
            $data['value'][$item->source_name]['data'][0] += 1;
        }


        foreach ($items_child as $key => $item) {
            $source_id = $item->source_id;
            $data['value'][$item->source_name]['data'][1] += 1;
        }

        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function ChannelBySentiment(Request $request)
    {

        $sentiment = Classification::where('classification_type_id', 1)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;
    
        foreach ($raw->get() as $item) {
        
            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

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


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);

    }

    public function ChannelBullyLevel(Request $request)
    {

        $sentiment = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;
    
        foreach ($raw->get() as $item) {
        
            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

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
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function ChannelBullyType(Request $request)
    {
        $sentiment = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [2]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        $data['value'] = null;
    
        foreach ($raw->get() as $item) {
        
            $index_label = 0;
            $index_label = array_search($item->classification_name, $data['labels']);

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
                    'data' => [0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->source_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function bully_type_name($class_id)
    {
        $classfication = Classification::where('id', $class_id)->first();
        return $classfication->name;
    }

    private function total_message_by_source_id($table, $start_date, $end_date, $source_id)
    {
        if ($source_id === "all") {
            $channal_message_current = DB::table($table)
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->whereIn('classification_type_id', [1]);
                

            if ($this->keyword_id) {
                $channal_message_current->whereIn('keyword_id', $this->keyword_id);
            }
                
                return $channal_message_current->get()->count();
            }
            
        $channal_message_current = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1])
            ->where('source_id', $source_id);

            if ($this->keyword_id) {
                $channal_message_current->whereIn('keyword_id', $this->keyword_id);
            }

        return $channal_message_current->get()->count();
    }

    public function PeriodOverPeriod(Request $request)
    {
        $source = Sources::where('status', 1)->get();
        foreach ($source as $item) {

            $channal_message_current = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, $item->id);
            $channal_message_previous = $this->total_message_by_source_id('message_result_full_data', $this->start_date_previous, $this->end_date_previous, $item->id);

            $comparison = $channal_message_current - $channal_message_previous;
            $percentage = ($channal_message_current - $channal_message_previous) / ($channal_message_previous === 0 ? 1 : $channal_message_previous) * 100;

            $data[$item->name] = [
                "comparison_value" => $this->point_two_digits($comparison),
                "percentage" => $this->point_two_digits($percentage),
                "type" => ($comparison >= 0 ? "plus" : "minus"),
            ];
        }

        return parent::handleRespond($data);
    }

    public function EngagementRate(Request $request)
    {
        $data = null;

        $data = $this->totalFromEngagementRate("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

        return parent::handleRespond($data);
    }

    public function EngagementRatePrevious(Request $request)
    {
        $data = null;

        $data = $this->totalFromEngagementRate("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

        return parent::handleRespond($data);
    }

    public function SentimentScore(Request $request)
    {
        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

        return parent::handleRespond($data);
    }

    public function SentimentScorePrevious(Request $request)
    {

        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

        return parent::handleRespond($data);
    }

    public function ChannelBySentiment2(Request $request)
    {
        $data = null;
        $source = Sources::where('status', 1)->get();
        $channal_message_all = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, "all");

        $data["all"] = [
            "keyword_name" => "All",
            "total_value" => $channal_message_all,
        ];
        foreach ($source as $item) {

            $channal_message = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, $item->id);
            $data[$item->name] = [
                "keyword_name" => $item->name,
                "total_value" => $channal_message,
            ];
        }

        if (!$data) {
            return parent::handleNotFound($data);
        }

        return parent::handleRespond(array_values($data));
    }

    public function SentimentLevel(Request $request)
    {
        $data = null;
        $percentage_of_channal = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_type_id', [1])
            ->groupBy('source_id');

        if ($this->keyword_id) {
            $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
        }
            
        foreach ($percentage_of_channal->get() as $channal) {
            $source_id = $channal->source_id;
            $data[$source_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id]['campaign_name'] = $channal->campaign_name;
            $data[$source_id]['source_id'] = $channal->source_id;
            $data[$source_id]['source_name'] = $this->source_name($channal->source_id);
            $data[$source_id]['negative'] = 0;
            $data[$source_id]['neutral'] = 0;
            $data[$source_id]['positive'] = 0;
            $data[$source_id]['total'] = 0;

            $sum = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [1])
                ->where('source_id', $channal->source_id)->get();
            foreach ($sum as $item) {
                if (isset($item->classification_name) && $item->classification_name === "Negative") {
                    $data[$source_id]['negative'] = $data[$source_id]['negative'] + 1;
                    $data[$source_id]['total'] = $data[$source_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Positive") {
                    $data[$source_id]['positive'] = $data[$source_id]['positive'] + 1;
                    $data[$source_id]['total'] = $data[$source_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Neutral") {
                    $data[$source_id]['neutral'] = $data[$source_id]['neutral'] + 1;
                    $data[$source_id]['total'] = $data[$source_id]['total'] + 1;
                }

            }

            $data[$source_id]['negative'] = $this->point_two_digits(($data[$source_id]['negative'] / $data[$source_id]['total']) * 100);
            $data[$source_id]['positive'] = $this->point_two_digits(($data[$source_id]['positive'] / $data[$source_id]['total']) * 100);
            $data[$source_id]['neutral'] = $this->point_two_digits(($data[$source_id]['neutral'] / $data[$source_id]['total']) * 100);
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    private function channelTable($start_date, $end_date, $source_id)
    {
        return DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('source_id', $source_id)
            ->whereIn('classification_type_id', [1])
            ->get()
            ->count();
    }

    private function totalFromEngagementRate($table, $start_date, $end_date, $keyword_name, $value_name)
    {
        $engagement_previous = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $engagement_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $source = Sources::where('status', 1)->get();
        foreach ($source as $source) {
            $data['labels'][] = $this->source_name($source->id);
        }

        foreach ($engagement_previous->get() as $item) {
            $source_name = $this->source_name($item->source_id);
            $index_label = array_search($source_name, $data['labels']);

            if (isset($data['value'][$value_name])) {
                $data['value'][$value_name]['data'][$index_label] += $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions;
            } else {
                $data['value'][$value_name] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $keyword_name,
                    'data' => [0, 0, 0, 0, 0, 0]
                ];
            }
        }

        return $data;
    }

    private function totalFromMessageResultSemetic($table, $start_date, $end_date, $keyword_name, $value_name)
    {
        $engagement_previous = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $engagement_previous->whereIn('keyword_id', $this->keyword_id);
        }

        $source = Sources::where('status', 1)->get();
        foreach ($source as $source) {
            $data['labels'][] = $this->source_name($source->id);
        }

        foreach ($engagement_previous->get() as $item) {
            $source_name = $this->source_name($item->source_id);
            $index_label = array_search($source_name, $data['labels']);

            if (isset($data['value'][$value_name])) {
                $data['value'][$value_name]['data'][$index_label] += 1;
            } else {
                $data['value'][$value_name] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $keyword_name,
                    'data' => [0, 0, 0, 0, 0, 0]
                ];
            }
        }

        return $data;
    }

    private function source_name($source_id)
    {
        $source = Sources::where('id', $source_id)->first();
        return $source->name;
    }

    // private function listDataByType($type, $table, $campaign_id, $start_date, $end_date, $keyword_id = null, $source_id = null, $column = null, $condition = null)
    // {
    //     $data['labels'] = [
    //         "Mon",
    //         "Tue",
    //         "Wed",
    //         "Thu",
    //         "Fri",
    //         "Sat",
    //         "Sun"
    //     ];

    //     $items = DB::table($table)
    //         ->where('campaign_id', $campaign_id)
    //         ->whereBetween('date_m', [$start_date, $end_date]);

    //     if (isset($condition['group_by'])) {

    //         foreach ($condition['group_by'] as $groupBy) {
    //             $items->groupBy($groupBy);
    //         }
    //     }


    //     if ($keyword_id) {
    //         $items->where('keyword_id', $keyword_id);
    //     }

    //     $data['value'] = null;

    //     if ($type === 'time') {
    //         $data['labels'] = [
    //             "Before 6 AM",
    //             "6 AM-12 PM",
    //             "12 PM-6 PM",
    //             "After 6 PM"
    //         ];
    //     }

    //     if ($type === 'channel_by_time' || $type === 'bully_level_by_time') {
    //         $data['labels'] = [
    //             "Before 6 AM",
    //             "6 AM-12 PM",
    //             "12 PM-6 PM",
    //             "After 6 PM"
    //         ];
    //     }

    //     if ($type === 'device') {
    //         $data['labels'] = [
    //             "Android",
    //             "Iphone",
    //             "Web App",
    //         ];
    //     }

    //     if ($type === 'channel_by_device' || $type === 'bully_level_by_device') {
    //         $data['labels'] = [
    //             "Android",
    //             "Iphone",
    //             "Web App",
    //         ];
    //     }

    //     if ($type === 'channel' || $type === 'bully_level_by_channel') {

    //         $sources = Sources::all();
    //         $data['labels'] = [];

    //         foreach ($sources as $source) {
    //             $data['labels'][] = $source->name;
    //         }

    //     }


    //     if ($type === 'sentiment' || $type === 'bully_level_by_sentiment') {
    //         $sentiment = Classification::where('classification_type_id', 1)->get();
    //         $data['labels'] = [];

    //         foreach ($sentiment as $item) {
    //             $data['labels'][] = $item->name;
    //         }
    //     }

    //     $debug = [];
    //     foreach ($items->get() as $item) {
    //         if ($type === 'dayname' || $type === 'dayname_engagement' || $type === 'channel_by_day' || $type === 'bully_level_by_day') {
    //             $day_name = Carbon::parse($item->date_m)->format('D');


    //             $index_label = array_search($day_name, $data['labels']);


    //             if ($type === 'dayname') {

    //                 if (isset($data['value'][$item->keyword_id])) {
    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //                 } else {
    //                     $data['value'][$item->keyword_id] = [
    //                         'id' => $item->keyword_id,
    //                         'keyword_name' => $item->keyword_name,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                         'data' => [0, 0, 0, 0, 0, 0, 0]
    //                     ];
    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;

    //                 }


    //             }

    //             if ($type === 'channel_by_day') {
    //                 if (isset($data['value'][$item->source_id])) {
    //                     $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
    //                 } else {

    //                     $data['value'][$item->source_id] = [
    //                         'id' => $item->source_id,
    //                         'name' => $item->source_name,
    //                         'keyword_name' => $item->source_name,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                         'data' => [0, 0, 0, 0, 0, 0, 0]
    //                     ];

    //                     $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
    //                 }
    //             }

    //             if ($type === 'bully_level_by_day') {
    //                 if ($item->classification_type_id === $column) {

    //                     if (isset($data['value'][$item->classification_id])) {
    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;


    //                     } else {
    //                         $data['value'][$item->classification_id] = [
    //                             'id' => $item->classification_id,
    //                             'keyword_name' => $item->classification_name,
    //                             'data' => [0, 0, 0, 0, 0, 0, 0]
    //                         ];

    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                     }
    //                 }
    //             }


    //             if ($type === 'dayname_engagement') {

    //                 if (isset($data['value'][0])) {
    //                     $data['value'][0]['data'][$index_label] += $item->number_of_shares;
    //                     $data['value'][1]['data'][$index_label] += $item->number_of_comments;
    //                     $data['value'][2]['data'][$index_label] += $item->number_of_reactions;

    //                 } else {
    //                     $data['value'][0] = [
    //                         'id' => 1,
    //                         'keyword_name' => 'Share',
    //                         'data' => [0, 0, 0, 0, 0, 0, 0]
    //                     ];

    //                     $data['value'][1] = [
    //                         'id' => 2,
    //                         'keyword_name' => 'Comment',
    //                         'data' => [0, 0, 0, 0, 0, 0, 0]
    //                     ];


    //                     $data['value'][2] = [
    //                         'id' => 3,
    //                         'keyword_name' => 'reactions',
    //                         'data' => [0, 0, 0, 0, 0, 0, 0]
    //                     ];

    //                     $data['value'][0]['data'][$index_label] += $item->number_of_shares;
    //                     $data['value'][1]['data'][$index_label] += $item->number_of_comments;
    //                     $data['value'][2]['data'][$index_label] += $item->number_of_reactions;
    //                 }
    //             }

    //         }


    //         if ($type === 'time' || $type === 'time_engagement' || $type === 'channel_by_time' || $type === 'bully_level_by_time') {

    //             $sixAM = Carbon::parse("06:00:00");
    //             $time = Carbon::parse($item->date_m)->format('H:i:s');
    //             $index_label = 3;

    //             if (Carbon::parse($time)->lt($sixAM)) {
    //                 $index_label = 0;
    //             }

    //             if (Carbon::parse($time)->between($sixAM, Carbon::parse("12:00:00"))) {
    //                 $index_label = 1;
    //             }

    //             if (Carbon::parse($time)->between(Carbon::parse("12:00:00"), Carbon::parse("18:00:00"))) {
    //                 $index_label = 2;
    //             }

    //             if (Carbon::parse($time)->gt(Carbon::parse("18:00:00"))) {
    //                 $index_label = 3;
    //             }

    //             if ($type === 'time' || $type === 'time_engagement') {
    //                 if (isset($data['value'][$item->keyword_id])) {
    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;


    //                 } else {
    //                     $data['value'][$item->keyword_id] = [
    //                         'id' => $item->keyword_id,
    //                         'keyword_name' => $item->keyword_name,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                         'data' => [0, 0, 0, 0]
    //                     ];

    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //                 }
    //             }

    //             if ($type === 'channel_by_time') {
    //                 if (isset($data['value'][$item->source_id])) {
    //                     $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;


    //                 } else {
    //                     $data['value'][$item->source_id] = [
    //                         'id' => $item->source_id,
    //                         'keyword_name' => $item->keyword_name,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                         'source_id' => $item->source_id,
    //                         'source_name' => $item->source_name,
    //                         'data' => [0, 0, 0, 0]
    //                     ];

    //                     $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
    //                 }
    //             }

    //             if ($type === 'bully_level_by_time') {
    //                 if ($item->classification_type_id === $column) {

    //                     if (isset($data['value'][$item->classification_id])) {
    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;


    //                     } else {
    //                         $data['value'][$item->classification_id] = [
    //                             'id' => $item->classification_id,
    //                             'keyword_name' => $item->classification_name,
    //                             'data' => [0, 0, 0, 0]
    //                         ];

    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                     }
    //                 }
    //             }

    //         }


    //         if ($type === 'device') {
    //             $index_label = 0;

    //             if ($item->device == 'iphone') {
    //                 $index_label = 1;
    //             }

    //             if ($item->device == 'webapp') {
    //                 $index_label = 2;
    //             }

    //             if (isset($data['value'][$item->keyword_id])) {
    //                 $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //             } else {
    //                 $data['value'][$item->keyword_id] = [
    //                     'id' => $item->keyword_id,
    //                     'keyword_name' => $item->keyword_name,
    //                     'campaign_id' => $item->campaign_id,
    //                     'campaign_name' => $item->campaign_name,
    //                     'data' => [0, 0, 0]
    //                 ];

    //                 $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //             }
    //         }

    //         if ($type === 'channel_by_device') {
    //             $index_label = 0;

    //             if ($item->device == 'iphone') {
    //                 $index_label = 1;
    //             }

    //             if ($item->device == 'webapp') {
    //                 $index_label = 2;
    //             }

    //             if (isset($data['value'][$item->source_id])) {
    //                 $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
    //             } else {
    //                 $data['value'][$item->source_id] = [
    //                     'id' => $item->keyword_id,
    //                     'keyword_name' => $item->keyword_name,
    //                     'campaign_id' => $item->campaign_id,
    //                     'campaign_name' => $item->campaign_name,
    //                     'source_id' => $item->source_id,
    //                     'source_name' => $item->source_name,
    //                     'data' => [0, 0, 0]
    //                 ];

    //                 $data['value'][$item->source_id]['data'][$index_label] += $item->total_at_date;
    //             }
    //         }

    //         if ($type === 'bully_level_by_device') {
    //             if ($item->classification_type_id === $column) {

    //                 $index_label = 0;

    //                 if ($item->device == 'iphone') {
    //                     $index_label = 1;
    //                 }

    //                 if ($item->device == 'webapp') {
    //                     $index_label = 2;
    //                 }

    //                 if (isset($data['value'][$item->classification_id])) {
    //                     $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                 } else {
    //                     $data['value'][$item->classification_id] = [
    //                         'id' => $item->classification_id,
    //                         'keyword_name' => $item->classification_name,
    //                         'data' => [0, 0, 0]
    //                     ];

    //                     $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                 }
    //             }

    //         }

    //         if ($type === 'channel' || $type === 'bully_level_by_channel') {
    //             $index_label = 0;
    //             $index_label = array_search($item->source_name, $data['labels']);

    //             if ($type === 'channel') {
    //                 if (isset($data['value'][$item->keyword_id])) {
    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //                 } else {
    //                     $data['value'][$item->keyword_id] = [
    //                         'id' => $item->keyword_id,
    //                         'name' => $item->keyword_name,
    //                         'keyword_name' => $item->keyword_name,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                     ];

    //                     for ($i = 0; $i <= count($data['labels']); $i++) {
    //                         $data['value'][$item->keyword_id]['data'][$i] = 0;
    //                     }

    //                     $data['value'][$item->keyword_id]['data'][$index_label] += 1;
    //                 }
    //             }

    //             if ($type === 'bully_level_by_channel') {
    //                 if ($item->classification_type_id === $column) {

    //                     if (isset($data['value'][$item->classification_id])) {
    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                     } else {
    //                         $data['value'][$item->classification_id] = [
    //                             'id' => $item->classification_id,
    //                             'keyword_name' => $item->classification_name,
    //                             'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
    //                         ];

    //                         $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
    //                     }
    //                 }
    //             }


    //         }

    //         if ($type === 'sentiment' || $type === 'bully_level_by_sentiment') {
    //             $index_label = 0;
    //             $index_label = array_search($item->classification_name, $data['labels']);


    //             if ($type === 'sentiment') {

    //                 if (isset($data['value'][$item->source_id])) {
    //                     $data['value'][$item->source_id]['data'][$index_label] += 1;
    //                 } else {
    //                     $data['value'][$item->source_id] = [
    //                         'id' => $item->source_id,
    //                         'name' => $item->keyword_name,
    //                         'keyword_name' => $item->keyword_name,
    //                         'source_name' => $this->source_name($item->source_id),
    //                         'classification_name' => $item->classification_name,
    //                         'classification_id' => $item->classification_id,
    //                         'source_id' => $item->source_id,
    //                         'campaign_id' => $item->campaign_id,
    //                         'campaign_name' => $item->campaign_name,
    //                         'data' => [0, 0, 0]
    //                     ];

    //                     $data['value'][$item->source_id]['data'][$index_label] += 1;
    //                 }
    //             }

    //             if ($type === 'bully_level_by_sentiment') {
    //                 if ($item->classification_type_id === $column) {

    //                     if (isset($data['value'][$item->classification_id])) {
    //                         // dd($data['value'][$item->classification_id]);
    //                         $data['value'][$item->classification_id]['data'][$index_label] += 1;
    //                     } else {
    //                         $data['value'][$item->classification_id] = [
    //                             'id' => $item->classification_id,
    //                             'keyword_name' => $item->classification_name,
    //                             'data' => [0, 0, 0]
    //                         ];

    //                         $data['value'][$item->classification_id]['data'][$index_label] += 1;
    //                     }
    //                 }

    //             }

    //         }


    //     }


    //     if (isset($data['value'])) {
    //         $data['value'] = array_values($data['value']);
    //     }

    //     return $data;

    // }

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
