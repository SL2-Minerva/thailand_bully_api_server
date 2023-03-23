<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Classification;
use Illuminate\Support\Carbon;
use App\Models\Sources;
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
        $this->source_id = $request->source === "all" ? "" : $request->source;

        $fillter_keywords = $request->fillter_keywords;

        if ($fillter_keywords && $fillter_keywords !== 'all') {
            $this->keyword_id = explode(',', $fillter_keywords);
        }

        $this->request = $request;

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();


            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

    }

    public function dailyBy(Request $request)
    {
        $data = null;

        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous);
        $data['daily_message'] = $this->DailyChannelGroup();

        return parent::handleRespond($data);

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
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1])
            ->groupBy('source_id');
        $channal_message_total = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);
            // ->get()
            // ->count();

        if ($this->keyword_id) {
            $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
            $channal_message_total->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $percentage_of_channal->where('source_id', $this->source_id);
            $channal_message_total->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();

            $percentage_of_channal->whereIn('source_id', $source_ids);
            $channal_message_total->whereIn('source_id', $source_ids);

        }

        $channal_message_total = $channal_message_total->get()->count();

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id_id = $channal->source_id;
            $data[$source_id_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id_id]['campaign_name'] = $channal->campaign_name;
            // $data[$source_id_id]['organization_id'] = $channal->organization_id;
            // $data[$source_id_id]['organizations_name'] = $channal->organizations_name;
            $data[$source_id_id]['source_id'] = $channal->source_id;
            $data[$source_id_id]['source_name'] = $channal->source_name;


            $channal_message = $this->channelTable($start_date, $end_date, $source_id_id);

            $data[$source_id_id]['total'] = self::point_two_digits($channal_message_total, 0);


            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $this->point_two_digits(($channal_message / $channal_message_total) * 100),
            ];

            $data[$source_id_id]['value'][] = $nestData;
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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
                    "source_id" => $item->source_id,
                    "source_name" => $item->source_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];
                $data[$item->source_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'date_m' => $date_format,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function DailyChannelGroup()
    {
        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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
                    "source_id" => $item->source_id,
                    "source_name" => $item->source_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];
                $data[$item->source_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'date_m' => $date_format,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    public function channelBy()
    {
        $data = null;

        $data['channel_by_day'] = $this->ChannelByDayGroup();
        $data['channel_by_time'] = $this->ChannelByTimeGroup();
        $data['channel_by_device'] = $this->ChannelByDeviceGroup();
        $data['channel_by_account'] = $this->ChannelByAccountGroup();
        $data['channel_by_sentiment'] = $this->ChannelBySentimentGroup();
        $data['channel_by_level'] = $this->ChannelBullyLevelGroup();
        $data['channel_by_bully_type'] = $this->ChannelBullyTypeGroup();

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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    private function ChannelByDayGroup()
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

        return $data;
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    public function ChannelByTimeGroup()
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

        return $data;
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    public function ChannelByDeviceGroup()
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        $data['value'] = null;

        foreach ($raw->get() as $item) {
            $index_label = null;

            if ($item->device == 'android') {
                $index_label = 0;
            }

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp') {
                $index_label = 2;
            }

            if ($index_label !== null) {

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

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;

    }

    public function ChannelByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $raw_child = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '!=', null)
            ->whereIn('classification_type_id', [1]);


        $raw_root = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '')
            ->orWhere('reference_message_id', null)
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw_child->whereIn('keyword_id', $this->keyword_id);
            $raw_root->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_child->where('source_id', $this->source_id);
            $raw_root->where('source_id', $this->source_id);
        }

        $soures = parent::listSource();


        for ($i = 0; $i < count($soures['labels']); $i++) {
            $data['value'][$soures['labels'][$i]] = [
                "id" => $i,
                "keyword_name" => $soures['labels'][$i],
                'data' => [0, 0]
            ];

        }


        $items_root = $raw_root->get();
        $items_child = $raw_child->get();

        foreach ($items_root as $key => $item) {
            $source_id_id = $item->source_id;
            $data['value'][$item->source_name]['data'][0] += 1;
        }


        foreach ($items_child as $key => $item) {
            $source_id_id = $item->source_id;
            $data['value'][$item->source_name]['data'][1] += 1;
        }

        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function ChannelByAccountGroup()
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $raw_child = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '!=', '')
            // ->orWhere('reference_message_id', null)
            ->whereIn('classification_type_id', [1]);


        $raw_root = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '')
            // ->orWhere('reference_message_id', null)
            ->whereIn('classification_type_id', [1]);

        $soures = parent::listSource();

        if ($this->keyword_id) {
            $raw_child->whereIn('keyword_id', $this->keyword_id);
            $raw_root->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_child->where('source_id', $this->source_id);
            $raw_root->where('source_id', $this->source_id);
        }


        for ($i = 0; $i < count($soures['labels']); $i++) {
            $data['value'][$soures['labels'][$i]] = [
                "id" => $i,
                "keyword_name" => $soures['labels'][$i],
                'data' => [0, 0]
            ];

        }

        $items_root = $raw_root->get();
        $items_child = $raw_child->get();

        foreach ($items_root as $key => $item) {
            $source_id_id = $item->source_id;
            $data['value'][$item->source_name]['data'][0] += 1;
        }


        foreach ($items_child as $key => $item) {
            $source_id_id = $item->source_id;
            $data['value'][$item->source_name]['data'][1] += 1;
        }

        if ($data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    public function ChannelBySentimentGroup()
    {

        $sentiment = Classification::where('classification_type_id', 1)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

        return $data;

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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    public function ChannelBullyLevelGroup()
    {

        $sentiment = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [3]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

        return $data;
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
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [2]);

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

    public function ChannelBullyTypeGroup()
    {
        $sentiment = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }


        $raw = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [2]);


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
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

        return $data;
    }

    public function bully_type_name($class_id)
    {
        $classfication = Classification::where('id', $class_id)->first();
        return $classfication->name;
    }

    private function total_message_by_source_id($table, $start_date, $end_date, $source_id_id)
    {
        if ($source_id_id === "all") {
            $channal_message_current = DB::table($table)
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                ->whereIn('classification_type_id', [1]);


            if ($this->keyword_id) {
                $channal_message_current->whereIn('keyword_id', $this->keyword_id);
            }

            if ($this->source_id) {
                $channal_message_current->where('source_id', $this->source_id);
            }

            return $channal_message_current->get()->count();
        }

        $channal_message_current = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1])
            ->where('source_id', $source_id_id);

        if ($this->keyword_id) {
            $channal_message_current->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $channal_message_current->where('source_id', $this->source_id);
        }

        return $channal_message_current->get()->count();
    }

    public function engagementBy()
    {
        $data = null;

        $data['period_over_period'] = $this->PeriodOverPeriodGroup();
        $data['engagement_rate'] = $this->EngagementRateGroup();
        $data['engagement_rate_previous'] = $this->EngagementRatePreviousGroup();

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $source_id = Sources::where('status', 1)->get();
        foreach ($source_id as $item) {

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

    public function PeriodOverPeriodGroup()
    {


        $soure_group = $this->organization_group->platform;
        $source_id = Sources::where('status', 1)
            ->whereIn('name', $soure_group)
            ->get();

        if ($this->user_login->is_admin) {
            $source_id = Sources::where('status', 1)->get();
        }


        foreach ($source_id as $item) {

            $channal_message_current = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, $item->id);
            $channal_message_previous = $this->total_message_by_source_id('message_result_full_data', $this->start_date_previous, $this->end_date_previous, $item->id);

            $comparison = $channal_message_current - $channal_message_previous;
            $percentage = ($channal_message_current - $channal_message_previous) / ($channal_message_previous === 0 ? 1 : $channal_message_previous) * 100;

            $data[$item->name] = [
                "comparison_value" => $this->point_two_digits($comparison, 0),
                "percentage" => $this->point_two_digits($percentage, 0),
                "type" => ($comparison >= 0 ? "plus" : "minus"),
            ];
        }

        return $data;
    }

    // public function EngagementRate(Request $request)
    // {
    //     $data = $this->totalFromEngagementRate($this->start_date, $this->end_date, 'current_period');

    //     return parent::handleRespond($data);
    // }

    public function EngagementRateGroup()
    {
        $data = $this->totalFromEngagementRate($this->start_date, $this->end_date, 'current_period');

        return $data;
    }

    // public function EngagementRatePrevious(Request $request)
    // {
    //     $data = null;

    //     $data = $this->totalFromEngagementRate($this->start_date_previous, $this->end_date_previous, 'previous_period');

    //     return parent::handleRespond($data);
    // }

    public function EngagementRatePreviousGroup()
    {
        $data = null;

        $data = $this->totalFromEngagementRate($this->start_date_previous, $this->end_date_previous, 'previous_period');

        return $data;
    }

    public function sentimentBy()
    {
        $data = null;
        $data['sentiment_score'] = $this->SentimentScoreGroup();
        $data['sentiment_score_previous'] = $this->SentimentScorePreviousGroup();
        $data['channel_by_sentiment'] = $this->ChannelBySentiment2Group();
        $data['sentiment_by_level'] = $this->SentimentLevelGroup();
        return parent::handleRespond($data);
    }

    public function SentimentScore(Request $request)
    {
        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

        return parent::handleRespond($data);
    }

    private function SentimentScoreGroup()
    {
        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

        return $data;
    }

    public function SentimentScorePrevious(Request $request)
    {

        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

        return parent::handleRespond($data);
    }

    private function SentimentScorePreviousGroup()
    {

        $data = null;
        $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

        return $data;
    }

    public function ChannelBySentiment2(Request $request)
    {
        $data = null;
        $source_id = Sources::where('status', 1)->get();
        $channal_message_all = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, "all");

        $data["all"] = [
            "keyword_name" => "All",
            "total_value" => $channal_message_all,
        ];
        foreach ($source_id as $item) {

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

    private function ChannelBySentiment2Group()
    {
        $data = null;
        $source_id = Sources::where('status', 1)->get();
        $channal_message_all = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, "all");

        $data["all"] = [
            "keyword_name" => "All",
            "total_value" => $channal_message_all,
        ];
        foreach ($source_id as $item) {

            $channal_message = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, $item->id);
            $data[$item->name] = [
                "keyword_name" => $item->name,
                "total_value" => $channal_message,
            ];
        }

        if (!$data) {
            return parent::handleNotFound($data);
        }

        return array_values($data);
    }

    public function SentimentLevel(Request $request)
    {
        $data = null;
        $percentage_of_channal = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1])
            ->groupBy('source_id');

        if ($this->keyword_id) {
            $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $percentage_of_channal->where('source_id', $this->source_id);
        }

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id_id = $channal->source_id;
            $data[$source_id_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id_id]['campaign_name'] = $channal->campaign_name;
            $data[$source_id_id]['source_id'] = $channal->source_id;
            $data[$source_id_id]['source_name'] = $this->source_name($channal->source_id);
            $data[$source_id_id]['negative'] = 0;
            $data[$source_id_id]['neutral'] = 0;
            $data[$source_id_id]['positive'] = 0;
            $data[$source_id_id]['total'] = 0;

            $sum = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                ->whereIn('classification_type_id', [1])
                ->where('source_id', $channal->source_id)->get();
            foreach ($sum as $item) {
                if (isset($item->classification_name) && $item->classification_name === "Negative") {
                    $data[$source_id_id]['negative'] = $data[$source_id_id]['negative'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Positive") {
                    $data[$source_id_id]['positive'] = $data[$source_id_id]['positive'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Neutral") {
                    $data[$source_id_id]['neutral'] = $data[$source_id_id]['neutral'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                }

            }

            $data[$source_id_id]['negative'] = $this->point_two_digits(($data[$source_id_id]['negative'] / $data[$source_id_id]['total']) * 100);
            $data[$source_id_id]['positive'] = $this->point_two_digits(($data[$source_id_id]['positive'] / $data[$source_id_id]['total']) * 100);
            $data[$source_id_id]['neutral'] = $this->point_two_digits(($data[$source_id_id]['neutral'] / $data[$source_id_id]['total']) * 100);
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function SentimentLevelGroup()
    {
        $data = null;
        $percentage_of_channal = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1])
            ->groupBy('source_id');

        if ($this->keyword_id) {
            $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $percentage_of_channal->where('source_id', $this->source_id);
        }

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id_id = $channal->source_id;
            $data[$source_id_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id_id]['campaign_name'] = $channal->campaign_name;
            $data[$source_id_id]['source_id'] = $channal->source_id;
            $data[$source_id_id]['source_name'] = $this->source_name($channal->source_id);
            $data[$source_id_id]['negative'] = 0;
            $data[$source_id_id]['neutral'] = 0;
            $data[$source_id_id]['positive'] = 0;
            $data[$source_id_id]['total'] = 0;

            $sum = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                ->whereIn('classification_type_id', [1])
                ->where('source_id', $channal->source_id)->get();
            foreach ($sum as $item) {
                if (isset($item->classification_name) && $item->classification_name === "Negative") {
                    $data[$source_id_id]['negative'] = $data[$source_id_id]['negative'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Positive") {
                    $data[$source_id_id]['positive'] = $data[$source_id_id]['positive'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                } else if (isset($item->classification_name) && $item->classification_name === "Neutral") {
                    $data[$source_id_id]['neutral'] = $data[$source_id_id]['neutral'] + 1;
                    $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
                }

            }

            $data[$source_id_id]['negative'] = $this->point_two_digits(($data[$source_id_id]['negative'] / $data[$source_id_id]['total']) * 100);
            $data[$source_id_id]['positive'] = $this->point_two_digits(($data[$source_id_id]['positive'] / $data[$source_id_id]['total']) * 100);
            $data[$source_id_id]['neutral'] = $this->point_two_digits(($data[$source_id_id]['neutral'] / $data[$source_id_id]['total']) * 100);
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    private function channelTable($start_date, $end_date, $source_id_id)
    {
        $count =  DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->where('source_id', $source_id_id)
            ->whereIn('classification_type_id', [1]);
            // ->get()
            // ->count();

        if ($this->keyword_id) {
            $count->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $count->where('source_id', $this->source_id);
        }

        $count = $count->get()->count();

        return $count;
    }

    private function totalFromEngagementRate($start_date, $end_date, $value_name)
    {

        $labels = parent::listSource();
        $engagement = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $engagement->where('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $engagement->where('source_id', $this->source_id);
        }

        $source_id = Sources::where('status', 1)->get();

        foreach ($source_id as $source_id) {
            $data['labels'][] = $source_id->name;
        }

        for ($i = 0; $i < count($labels['labels']); $i++) {
            $data['value'][$value_name]['data'][] = 0;
        }

        foreach ($engagement->get() as $item) {
            $source_name = $item->source_name;
            $index_label = array_search($source_name, $labels['labels']);

            if (isset($data['value'][$value_name])) {
                $data['value'][$value_name]['data'][$index_label] += $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions;
            } else {
                $data['value'][$value_name] = [
                    'id' => $item->keyword_id,
                    'keyword_name' => $item->source_name,
                    // 'data' => [0, 0, 0, 0, 0, 0]
                ];
            }
        }

        return $data;
    }

    private function totalFromMessageResultSemetic($table, $start_date, $end_date, $keyword_name, $value_name)
    {
        $engagement_previous = DB::table($table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [1]);

        if ($this->keyword_id) {
            $engagement_previous->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $engagement_previous->where('source_id', $this->source_id);
        }

        $source_id = Sources::where('status', 1)->get();
        foreach ($source_id as $source_id) {
            $data['labels'][] = $source_id->name;
        }

        foreach ($engagement_previous->get() as $item) {
            $source_name = $item->source_name;
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

    private function source_name($source_id_id)
    {
        $source_id = Sources::where('id', $source_id_id)->first();
        return $source_id->name;
    }

}
