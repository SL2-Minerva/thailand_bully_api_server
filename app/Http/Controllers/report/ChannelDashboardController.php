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
use App\Models\Keyword;

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

        if ($request->period === 'customrange') {
            $this->start_date_previous =  $this->date_carbon($request->start_date_period);
            $this->end_date_previous =  $this->date_carbon($request->end_date_period);
        }

    }

    public function dailyBy(Request $request)
    {
        $data = null;

        $keyword = Keyword::where('campaign_id', $this->campaign_id);
        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $raw = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->where('source_id', $source_ids);
        }

        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous);
        $data['daily_message'] = $this->DailyChannelGroup($raw);

        return parent::handleRespond($data);

    }

    private function PercentageToCal($start_date, $end_date)
    {
        $data = null;
        $percentage_of_channal = $this->raw_message($this->campaign_id, $start_date, $end_date)->groupBy('source_id');
        $channal_message_total = $this->raw_message($this->campaign_id, $start_date, $end_date);

        $channal_message_total = $channal_message_total->get()->count();
        

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id_id = $channal->source_id;
            $data[$source_id_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id_id]['campaign_name'] = $channal->campaign_name;
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

    public function DailyChannelGroup($raw)
    {
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
        $raw = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date);

        $data['channel_by_day'] = $this->ChannelByDayGroup($raw);
        $data['channel_by_time'] = $this->ChannelByTimeGroup($raw);
        $data['channel_by_device'] = $this->ChannelByDeviceGroup($raw);
        $data['channel_by_account'] = $this->ChannelByAccountGroup();
        $data['channel_by_sentiment'] = $this->ChannelBySentimentGroup($raw);
        $data['channel_by_level'] = $this->ChannelBullyLevelGroup($raw);
        $data['channel_by_bully_type'] = $this->ChannelBullyTypeGroup($raw);

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

    private function ChannelByDayGroup($raw)
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

    public function ChannelByTimeGroup($raw)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

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

    public function ChannelByDeviceGroup($raw)
    {
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($raw->get() as $item) {
            $index_label = null;

            if ($item->device == 'android') {
                $index_label = 0;
            }

            if ($item->device == 'iphone') {
                $index_label = 1;
            }

            if ($item->device == 'webapp' || $item->device == 'website') {
                $index_label = 2;
            }

            if ($index_label != null || $index_label != '') {

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

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $raw_child = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '!=', '');

        $raw_root = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->where('reference_message_id', '');

        if ($this->source_id) {
            $raw_child->where('source_id', $this->source_id);
            $raw_root->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw_child->whereIn('source_id', $source_ids);
            $raw_root->whereIn('source_id', $source_ids);
        }

        $soures = parent::listSource();

        if ($this->keyword_id) {
            $raw_child->whereIn('keyword_id', $this->keyword_id);
            $raw_root->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $raw_child->where('source_id', $this->source_id);
            $raw_root->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw_child->whereIn('source_id', $source_ids);
            $raw_root->whereIn('source_id', $source_ids);
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

    public function ChannelBySentimentGroup($raw)
    {

        $sentiment = Classification::where('classification_type_id', 1)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $raw = $raw->select([
            'messages.keyword_id as keyword_id',
            'keywords.name as keyword_name',
            'keywords.campaign_id AS campaign_id',
            'campaigns.name AS campaign_name',
            'messages.source_id as source_id',
            'sources.name as source_name',
            'messages.message_datetime as date_m',
            'messages.device as device',
            'classifications.name as classification_name',
            'message_results.classification_id as classification_id',
        ])->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
        ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id');

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

    public function ChannelBullyLevelGroup($raw)
    {

        $sentiment = Classification::where('classification_type_id', 3)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $raw = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'classifications.name as classification_name',
                'message_results.classification_id as classification_id',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
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

    public function ChannelBullyTypeGroup($raw)
    {
        $sentiment = Classification::where('classification_type_id', 2)->get();
        $data['labels'] = [];

        foreach ($sentiment as $item) {
            $data['labels'][] = $item->name;
        }

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $raw = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'classifications.name as classification_name',
                'message_results.classification_id as classification_id',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $raw->whereIn('source_id', $source_ids);
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
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }
        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        if ($source_id_id === "all") {
            return $data->get()->count();
        }

        $data = $data->where('source_id', $source_id_id);

        return $data->get()->count();
    }

    public function engagementBy()
    {


        $source_group = $this->organization_group->platform;

        if ($this->user_login->is_admin) {
            $sources = Sources::where('status', 1)->get();
        } else {
            $sources = Sources::where('status', 1)
                ->whereIn('name', $source_group)
                ->get();
        }

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }
        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();
        $campaignId = $this->campaign_id;
        $data = null;
        $data['period_over_period'] = $this->PeriodOverPeriodGroup($campaignId,$keywordIds, $sources);
        $data['engagement_rate'] = $this->totalFromEngagementRate($campaignId,$keywordIds, $sources, $this->start_date, $this->end_date, 'current_period');
        $data['engagement_rate_previous'] = $this->totalFromEngagementRate($campaignId,$keywordIds, $sources, $this->start_date_previous, $this->end_date_previous, 'previous_period');

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriodGroup($campaignId,$keywordIds, $sources)
    {

        $sourceIds = $sources->pluck('id')->all();

        $channel_message_current = $this->total_message_by_source($campaignId, $keywordIds, $sourceIds, $this->start_date, $this->end_date);
        $channel_message_previous = $this->total_message_by_source($campaignId, $keywordIds, $sourceIds, $this->start_date_previous, $this->end_date_previous);
        $data = array();
        foreach ($sources as $item) {
            $message_current = 1;
            $message_previous = 1;
            foreach ($channel_message_current as $current) {
                if ($current->source_name == $item->name) {
                    $message_current = $current->total_messages;
                    break;
                }
            }
            foreach ($channel_message_previous as $current) {
                if ($current->source_name == $item->name) {
                    $message_previous = $current->total_messages;
                    break;
                }
            }

            $comparison = $message_current - $message_previous;
            $percentage = ($message_current - $message_previous) / ($message_previous === 0 ? 1 : $message_previous) * 100;

            $data[$item->name] = [
                "comparison_value" => $this->point_two_digits($comparison, 0),
                "percentage" => $this->point_two_digits($percentage, 0),
                "type" => ($comparison >= 0 ? "plus" : "minus"),
            ];
        }
        return $data;
    }

    private function total_message_by_source($campaignId, $keywordIds, $sourceIds, $start_date, $end_date)
    {

        $result = DB::table('messages')
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->where('keywords.campaign_id', $campaignId);

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $result = $result->whereIn('messages.source_id', $source_ids);
        } else {
            $result->whereIn('sources.id', $sourceIds);
        }
        $result->whereIn('keywords.id', $keywordIds)
            ->whereBetween('messages.message_datetime', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->groupBy('sources.id', 'sources.name')
            ->select('sources.id as source_id', 'sources.name as source_name', DB::raw('COUNT(*) as total_messages'));

        return $result->get();
    }

    private function totalFromEngagementRate($campaignId,$keywordIds, $sources, $start_date, $end_date, $value_name)
    {
        $labels = parent::listSource();
        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        $engagement = DB::table('messages')
            ->select([
                'messages.source_id as source_id',
                'sources.name as source_name',
                DB::raw('SUM(tbl_messages.number_of_comments + tbl_messages.number_of_shares + tbl_messages.number_of_reactions) as engagement_count')
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->where('campaigns.id', $campaignId)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($this->source_id) {
            $engagement->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $engagement->whereIn('source_id', $source_ids);
        }

        $engagementResults = $engagement->groupBy('messages.source_id', 'sources.name')->get();

        foreach ($engagementResults as $item) {
            $source_name = $item->source_name;
            $index_label = array_search($source_name, $labels['labels']);

            $data['value'][$value_name]['data'][$index_label] = $item->engagement_count;
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

    // public function EngagementRatePreviousGroup()
    // {
    //     $data = null;

    //     $data = $this->totalFromEngagementRate($this->start_date_previous, $this->end_date_previous, 'previous_period');

    //     return $data;
    // }

    public function sentimentBy()
    {
        $source_group = $this->organization_group->platform;
        if ($this->user_login->is_admin) {
            $sources = Sources::where('status', 1)->get();
        } else {
            $sources = Sources::where('status', 1)
                ->whereIn('name', $source_group)
                ->get();
        }

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }
        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();
        $campaignId = $this->campaign_id;
        $data = null;

        $data['sentiment_score'] = $this->totalFromMessageResultSemetic($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date, "current period", "current_period");
        $data['sentiment_score_previous'] = $this->totalFromMessageResultSemetic($campaignId, $keywordIds, $sources, $this->start_date_previous, $this->end_date_previous, "previous period", "previous period");
        $data['channel_by_sentiment'] = $this->ChannelBySentiment2Group($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date);
        $data['sentiment_by_level'] = $this->SentimentLevelGroup($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date);

        return parent::handleRespond($data);
    }


    private function ChannelBySentiment2Group($campaignId, $keywordIds, $sources, $start_date, $end_date)
    {
        $source_id = Sources::where('status', 1)->get();

        $sourceIds = $sources->pluck('id')->all();

        if (!$this->user_login->is_admin) {
            $sourceIds = $source_id->whereIn('name', $this->organization_group->platform);
        }
        $channal_message_all = $this->total_message_by_source($campaignId, $keywordIds, $sourceIds, $start_date, $end_date);
        $data = [];
        $totalValue = 0;

        foreach ($channal_message_all as $item) {
            $totalValue += $item->total_messages;
            $data[] = [
                'keyword_name' => $item->source_name,
                'total_value' => $item->total_messages
            ];
        }
        $data = array_merge([['keyword_name' => 'all', 'total_value' => $totalValue]], $data);
        return $data;
    }

    public function SentimentLevelGroup($campaignId, $keywordIds, $sources, $start_date, $end_date)
    {
        $query = "SELECT
        s.id  as source_id,
        s.name as source_name,
        SUM(CASE WHEN c.name = 'Positive' THEN 1 ELSE 0 END) AS positive,
        SUM(CASE WHEN c.name = 'Negative' THEN 1 ELSE 0 END) AS negative,
        SUM(CASE WHEN c.name = 'Neutral' THEN 1 ELSE 0 END) AS neutral
    FROM
        tbl_messages m
        LEFT JOIN tbl_keywords k ON k.id = m.keyword_id
        LEFT JOIN tbl_message_results mr ON m.id = mr.message_id
        LEFT JOIN tbl_classifications c ON c.id = mr.classification_id
        LEFT JOIN tbl_sources s ON m.source_id = s.id
    WHERE
        k.campaign_id = 3
        AND s.id IN (1, 2, 3, 4, 5, 6)
        AND m.message_datetime BETWEEN '2023-05-21 00:00:00' AND '2023-05-27 23:59:59'
    GROUP BY s.id;";

        $data = DB::select($query);

        $items = array();
        $result = array();
        $totals = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total' => 0,
            'source_id' => 0,
            'source_name' => "All"
        ];

        foreach ($data as $item) {
            $positive = intval($item->positive);
            $negative = intval($item->negative);
            $neutral = intval($item->neutral);
            $total = $positive + $negative + $neutral;
            $i['source_id'] = $item->source_id;
            $i['source_name'] = $item->source_name;
            $i['total'] = $total;
            $i['positive'] = self::point_two_digits(($positive / $total) * 100);
            $i['negative'] = self::point_two_digits(($negative / $total) * 100);
            $i['neutral'] = self::point_two_digits(($neutral / $total) * 100);
            $totals['positive'] += intval($item->positive);
            $totals['negative'] += intval($item->negative);
            $totals['neutral'] += intval($item->neutral);
            $totals['total'] += $total;
            $items[] = $i;
        }
        $positive= self::point_two_digits((  $totals['positive'] /   $totals['total']) * 100);
        $negative= self::point_two_digits((  $totals['negative'] /   $totals['total']) * 100);
        $neutral= self::point_two_digits((  $totals['neutral'] /   $totals['total']) * 100);
        $totals['positive']=$positive;
        $totals['negative']=$negative;
        $totals['neutral']=$neutral;
        $result[] = $totals;
        foreach ($items as $item) {
            $result[] = $item;
        }
        return $result;
    }

    private function totalFromMessageResultSemetic($campaignId, $keywordIds, $sources, $start_date, $end_date, $keyword_name, $value_name)
    {
        $labels = parent::listSource();
        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        $engagement = DB::table('messages')
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            //->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->where('keywords.campaign_id', $campaignId)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('sources.id', 'sources.name')
            ->select([
                'sources.id as source_id',
                'sources.name as source_name',
                DB::raw('COUNT(*) as count')
            ])
            ->pluck('count', 'source_name');
        $data['value'][$value_name]['keyword_name'] = $keyword_name;
        foreach ($engagement as $source_name => $count) {
            $index_label = array_search($source_name, $data['labels']);
            $data['value'][$value_name]['data'][$index_label] = $count;
        }

        return $data;
    }


    // private function ChannelBySentiment2Group($campaignId, $keywordIds, $sources, $start_date, $end_date)
    // {
    //     $source_id = Sources::where('status', 1)->get();

    //     $sourceIds = $sources->pluck('id')->all();

    //     if (!$this->user_login->is_admin) {
    //         $sourceIds = $source_id->whereIn('name', $this->organization_group->platform);
    //     }
    //     $channal_message_all = $this->total_message_by_source($campaignId, $keywordIds, $sourceIds, $start_date, $end_date);
    //     $data = [];
    //     $totalValue = 0;

    //     foreach ($channal_message_all as $item) {
    //         $totalValue += $item->total_messages;
    //         $data[] = [
    //             'keyword_name' => $item->source_name,
    //             'total_value' => $item->total_messages
    //         ];
    //     }
    //     $data = array_merge([['keyword_name' => 'all', 'total_value' => $totalValue]], $data);
    //     return $data;
    // }

    // public function SentimentLevelGroup($campaignId, $keywordIds, $sources, $start_date, $end_date)
    // {
    //     $query = "SELECT
    //     s.id  as source_id,
    //     s.name as source_name,
    //     SUM(CASE WHEN c.name = 'Positive' THEN 1 ELSE 0 END) AS positive,
    //     SUM(CASE WHEN c.name = 'Negative' THEN 1 ELSE 0 END) AS negative,
    //     SUM(CASE WHEN c.name = 'Neutral' THEN 1 ELSE 0 END) AS neutral
    // FROM
    //     tbl_messages m
    //     LEFT JOIN tbl_keywords k ON k.id = m.keyword_id
    //     LEFT JOIN tbl_message_results mr ON m.id = mr.message_id
    //     LEFT JOIN tbl_classifications c ON c.id = mr.classification_id
    //     LEFT JOIN tbl_sources s ON m.source_id = s.id
    // WHERE
    //     k.campaign_id = 3
    //     AND s.id IN (1, 2, 3, 4, 5, 6)
    //     AND m.message_datetime BETWEEN '2023-05-21 00:00:00' AND '2023-05-27 23:59:59'
    // GROUP BY s.id;";

    //     $data = DB::select($query);

    //     $items = array();
    //     $result = array();
    //     $totals = [
    //         'positive' => 0,
    //         'negative' => 0,
    //         'neutral' => 0,
    //         'total' => 0,
    //         'source_id' => 0,
    //         'source_name' => "All"
    //     ];

    //     foreach ($data as $item) {
    //         $positive = intval($item->positive);
    //         $negative = intval($item->negative);
    //         $neutral = intval($item->neutral);
    //         $total = $positive + $negative + $neutral;
    //         $i['source_id'] = $item->source_id;
    //         $i['source_name'] = $item->source_name;
    //         $i['total'] = $total;
    //         $i['positive'] = self::point_two_digits(($positive / $total) * 100);
    //         $i['negative'] = self::point_two_digits(($negative / $total) * 100);
    //         $i['neutral'] = self::point_two_digits(($neutral / $total) * 100);
    //         $totals['positive'] += intval($item->positive);
    //         $totals['negative'] += intval($item->negative);
    //         $totals['neutral'] += intval($item->neutral);
    //         $totals['total'] += $total;
    //         $items[] = $i;
    //     }
    //     $positive= self::point_two_digits((  $totals['positive'] /   $totals['total']) * 100);
    //     $negative= self::point_two_digits((  $totals['negative'] /   $totals['total']) * 100);
    //     $neutral= self::point_two_digits((  $totals['neutral'] /   $totals['total']) * 100);
    //     $totals['positive']=$positive;
    //     $totals['negative']=$negative;
    //     $totals['neutral']=$neutral;
    //     $result[] = $totals;
    //     foreach ($items as $item) {
    //         $result[] = $item;
    //     }
    //     return $result;
    // }

    // private function totalFromMessageResultSemetic($campaignId, $keywordIds, $sources, $start_date, $end_date, $keyword_name, $value_name)
    // {
    //     $labels = parent::listSource();
    //     $data = [
    //         'labels' => $labels['labels'],
    //         'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
    //     ];

    //     $engagement = DB::table('messages')
    //         ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
    //         //->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
    //         ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
    //         ->whereIn('keyword_id', $keywordIds)
    //         ->where('keywords.campaign_id', $campaignId)
    //         ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
    //         ->groupBy('sources.id', 'sources.name')
    //         ->select([
    //             'sources.id as source_id',
    //             'sources.name as source_name',
    //             DB::raw('COUNT(*) as count')
    //         ])
    //         ->pluck('count', 'source_name');
    //     $data['value'][$value_name]['keyword_name'] = $keyword_name;
    //     foreach ($engagement as $source_name => $count) {
    //         $index_label = array_search($source_name, $data['labels']);
    //         $data['value'][$value_name]['data'][$index_label] = $count;
    //     }

    //     return $data;
    // }

    // public function SentimentScore(Request $request)
    // {
    //     $data = null;
    //     $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

    //     return parent::handleRespond($data);
    // }

    // private function SentimentScoreGroup()
    // {
    //     $data = null;
    //     $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date, $this->end_date, "current period", "current_period");

    //     return $data;
    // }

    // public function SentimentScorePrevious(Request $request)
    // {

    //     $data = null;
    //     $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

    //     return parent::handleRespond($data);
    // }

    // private function SentimentScorePreviousGroup()
    // {

    //     $data = null;
    //     $data = $this->totalFromMessageResultSemetic("message_result_full_data", $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");

    //     return $data;
    // }

    public function ChannelBySentiment2(Request $request)
    {
        $data = null;
        $source_id = Sources::where('status', 1)->get();
        if (!$this->user_login->is_admin) {
            $source_id = $source_id->whereIn('name', $this->organization_group->platform);
        }

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

    // private function ChannelBySentiment2Group()
    // {
    //     $data = null;
    //     $source_id = Sources::where('status', 1)->get();

    //     if (!$this->user_login->is_admin) {
    //         $source_id = $source_id->whereIn('name', $this->organization_group->platform);
    //     }

    //     $channal_message_all = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, "all");

    //     $data["all"] = [
    //         "keyword_name" => "All",
    //         "total_value" => $channal_message_all,
    //     ];
    //     foreach ($source_id as $item) {

    //         $channal_message = $this->total_message_by_source_id('message_result_full_data', $this->start_date, $this->end_date, $item->id);
    //         $data[$item->name] = [
    //             "keyword_name" => $item->name,
    //             "total_value" => $channal_message,
    //         ];
    //     }

    //     if (!$data) {
    //         return parent::handleNotFound($data);
    //     }

    //     return array_values($data);
    // }

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

    // public function SentimentLevelGroup()
    // {
    //     $data = null;

    //     $percentage_of_channal = $this->raw_message($this->campaign_id, $this->start_date, $this->end_date)
    //         ->groupBy('source_id');
    //     //     ->where('campaign_id', $this->campaign_id)
    //     //     ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
    //     //     ->whereIn('classification_type_id', [1])
    //     //     ->groupBy('source_id');

    //     // if ($this->keyword_id) {
    //     //     $percentage_of_channal->whereIn('keyword_id', $this->keyword_id);
    //     // }

    //     // if (!$this->user_login->is_admin) {
    //     //     $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
    //     //     $percentage_of_channal->whereIn('source_id', $source_ids);
    //     // }

    //     // if ($this->source_id) {
    //     //     $percentage_of_channal->where('source_id', $this->source_id);
    //     // }

    //     // All
    //     $data[-1] = [
    //         'keyword_id' => 0,
    //         'keyword_name' => 'All',
    //         'campaign_id' => '0',
    //         'campaign_name' => 'All',
    //         'source_id' => 0,
    //         'source_name' => 'All',
    //         'negative' => 0,
    //         'neutral' => 0,
    //         'positive' => 0,
    //         'total' => 0
    //     ];

    //     $sum = $this->raw_message_classification($this->campaign_id, $this->start_date, $this->end_date)->get();
    //     foreach ($sum as $item) {
    //         if (isset($item->classification_name) && $item->classification_name === "Negative") {
    //             $data[-1]['negative'] = $data[-1]['negative'] + 1;
    //             $data[-1]['total'] = $data[-1]['total'] + 1;
    //         } else if (isset($item->classification_name) && $item->classification_name === "Positive") {
    //             $data[-1]['positive'] = $data[-1]['positive'] + 1;
    //             $data[-1]['total'] = $data[-1]['total'] + 1;
    //         } else if (isset($item->classification_name) && $item->classification_name === "Neutral") {
    //             $data[-1]['neutral'] = $data[-1]['neutral'] + 1;
    //             $data[-1]['total'] = $data[-1]['total'] + 1;
    //         }

    //     }

    //     $data[-1]['negative'] = $this->point_two_digits($data[-1]['total'] ? ($data[-1]['negative'] / $data[-1]['total']) * 100 : 0);
    //     $data[-1]['positive'] = $this->point_two_digits($data[-1]['total'] ? ($data[-1]['positive'] / $data[-1]['total']) * 100 : 0);
    //     $data[-1]['neutral'] = $this->point_two_digits( $data[-1]['total'] ? ($data[-1]['neutral'] / $data[-1]['total']) * 100 : 0);

    //     // By source Id
    //     foreach ($percentage_of_channal->get() as $channal) {
    //         $source_id_id = $channal->source_id;
    //         $data[$source_id_id]['keyword_id'] = $channal->keyword_id;
    //         $data[$source_id_id]['keyword_name'] = $channal->keyword_name;
    //         $data[$source_id_id]['campaign_id'] = $channal->campaign_id;
    //         $data[$source_id_id]['campaign_name'] = $channal->campaign_name;
    //         $data[$source_id_id]['source_id'] = $channal->source_id;
    //         $data[$source_id_id]['source_name'] = $this->source_name($channal->source_id);
    //         $data[$source_id_id]['negative'] = 0;
    //         $data[$source_id_id]['neutral'] = 0;
    //         $data[$source_id_id]['positive'] = 0;
    //         $data[$source_id_id]['total'] = 0;

    //         $sum = $this->raw_message_classification($this->campaign_id, $this->start_date, $this->end_date)
    //             ->where('source_id', $channal->source_id)
    //             ->get();
    //         foreach ($sum as $item) {
    //             if (isset($item->classification_name) && $item->classification_name === "Negative") {
    //                 $data[$source_id_id]['negative'] = $data[$source_id_id]['negative'] + 1;
    //                 $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
    //             } else if (isset($item->classification_name) && $item->classification_name === "Positive") {
    //                 $data[$source_id_id]['positive'] = $data[$source_id_id]['positive'] + 1;
    //                 $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
    //             } else if (isset($item->classification_name) && $item->classification_name === "Neutral") {
    //                 $data[$source_id_id]['neutral'] = $data[$source_id_id]['neutral'] + 1;
    //                 $data[$source_id_id]['total'] = $data[$source_id_id]['total'] + 1;
    //             }

    //         }

    //         $data[$source_id_id]['negative'] = $this->point_two_digits(($data[$source_id_id]['negative'] / $data[$source_id_id]['total']) * 100);
    //         $data[$source_id_id]['positive'] = $this->point_two_digits(($data[$source_id_id]['positive'] / $data[$source_id_id]['total']) * 100);
    //         $data[$source_id_id]['neutral'] = $this->point_two_digits(($data[$source_id_id]['neutral'] / $data[$source_id_id]['total']) * 100);
    //     }

    //     if ($data) {
    //         return array_values($data);
    //     }

    //     return $data;
    // }

    private function channelTable($start_date, $end_date, $source_id_id)
    {
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_shares as number_of_shares',
                'classifications.name as classification_name',
                'message_results.classification_id as classification_id'
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->where('source_id', $source_id_id);

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data->count();
    }


    // private function totalFromMessageResultSemetic($table, $start_date, $end_date, $keyword_name, $value_name)
    // {
    //     $raw = $this->raw_message($this->campaign_id, $start_date, $end_date);

    //     $source_id = Sources::where('status', 1)->get();
    //     foreach ($source_id as $source_id) {
    //         $data['labels'][] = $source_id->name;
    //     }

    //     foreach ($raw->get() as $item) {
    //         $source_name = $item->source_name;
    //         $index_label = array_search($source_name, $data['labels']);

    //         if (isset($data['value'][$value_name])) {
    //             $data['value'][$value_name]['data'][$index_label] += 1;
    //         } else {
    //             $data['value'][$value_name] = [
    //                 'id' => $item->keyword_id,
    //                 'keyword_name' => $keyword_name,
    //                 'data' => [0, 0, 0, 0, 0, 0]
    //             ];

    //             $data['value'][$value_name]['data'][$index_label] += 1;
    //         }
    //     }

    //     return $data;
    // }

    private function source_name($source_id_id)
    {
        $source_id = Sources::where('id', $source_id_id)->first();
        return $source_id->name;
    }

    private function raw_message($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_shares as number_of_shares',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data;
    }

    private function raw_message_classification($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_shares as number_of_shares',
                'classifications.name as classification_name',
                'message_results.classification_id as classification_id'
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }


        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data;
    }

}
