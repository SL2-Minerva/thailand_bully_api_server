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
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

    }

    public function dailyBy(Request $request)
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        // if (!$this->user_login->is_admin) {
        //     $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
        //     $raw->where('source_id', $source_ids);
        // }


        //$sources = self::getAllSource();
        $messages = DB::table('messages')->select('keyword_id', DB::raw("DATE(message_datetime) AS date_m"), DB::raw('COUNT(*) as total_at_date'))
            ->whereIn('keyword_id', $keywords->pluck('id')->all())
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->groupBy('keyword_id', 'date_m')
            ->orderBy('date_m')
            ->orderBy('keyword_id')
            ->get();

        $dataM = [];
        foreach ($messages as $message) {
            $keywordId = $message->keyword_id;
            $dateM = $message->date_m;

            // Check if keyword_id exists
            if (!isset($dataM[$keywordId])) {
                $dataM[$keywordId] = [
                    'keyword_id' => $keywordId,
                    'keyword_name' => self::matchKeywordName($keywords, $keywordId), // Fetch keyword name from somewhere
                    /*'campaign_id' => $this->getCampaignId($keywordId), // Fetch campaign id from somewhere
                    'campaign_name' => $this->getCampaignName($keywordId), // Fetch campaign name from somewhere*/
                    'value' => [[
                        'keyword_id' => $keywordId,
                        'date_m' => $dateM,
                        'total_at_date' => $message->total_at_date,
                    ]]
                ];
            } else {
                $dataM[$keywordId]['value'][] = [
                    'keyword_id' => $keywordId,
                    'date_m' => $dateM,
                    'keyword_name' => self::matchKeywordName($keywords, $keywordId),
                    'total_at_date' => $message->total_at_date,
                ];
            }
        }

        $data['daily_message'] = array_values($dataM);
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($keywords, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($keywords, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);

    }

    private function PercentageToCal($keyword, $start_date, $end_date)
    {
        $keywordIds = $keyword->pluck('id')->all();
        $sources = self::getAllSource();
        //$campaign = self::getCampaign($this->campaign_id);
        $data = null;
        $percentage_of_channel = $this->raw_message($keywordIds, $start_date, $end_date)->groupBy('source_id');
        $channel_message_total = $this->countChannelTable($keywordIds, $start_date, $end_date);

        foreach ($percentage_of_channel->get() as $channel) {
            $source_id = $channel->source_id;
            $data[$source_id]['keyword_id'] = $channel->keyword_id;
            $data[$source_id]['keyword_name'] = self::matchKeywordName($keyword, $channel->keyword_id);
            /*$data[$source_id]['campaign_id'] = $campaign->id;
            $data[$source_id]['campaign_name'] = $campaign->name;*/
            $data[$source_id]['source_id'] = $channel->source_id;
            $data[$source_id]['source_name'] = self::matchSourceName($sources, $channel->source_id);

            $data[$source_id]['total'] = self::point_two_digits($channel_message_total, 0);
            $data[$source_id]['count'] = $channel->total_messages;

            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $channel_message_total ? $this->point_two_digits(($channel->total_messages / $channel_message_total) * 100) : 0,
            ];

            $data[$source_id]['value'][] = $nestData;
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
            $value = array_values($data['value']);
            $data['value'] = $this->filteredData($value);
        }

        return $data;
    }

    public function filteredData($data)
    {
        $filteredData = array_filter($data, function ($item) {
            return $item["data"] !== [0, 0];
        });

        $filteredData = array_values($filteredData);
        return $filteredData;
    }


    public function ChannelBySentimentGroup($raw)
    {

        // $sentiment = Classification::where('classification_type_id', 1)->get();
        // $data['labels'] = [];

        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

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
                if ($item->classification_name === 'Positive') {
                    $data['value'][$item->source_id]['data'][$index_label] += 1;
                }

                if ($item->classification_name === 'Negative') {
                    $data['value'][$item->source_id]['data'][$index_label] += 1;
                }

                if ($item->classification_name === 'Neutral') {
                    $data['value'][$item->source_id]['data'][$index_label] += 1;
                }
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
            ->whereIn('classifications.name', ["Level 0", "Level 1", "Level 2", "Level 3"])
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
            ->whereIn('classifications.name', ['NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence'])
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

    private function total_message_by_source_id($start_date, $end_date, $source_id_id)
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

        $sources = self::getAllSource();
        $keyword = self::findKeywords($this->campaign_id, $this->keyword_id);

        $data = null;
        $data['period_over_period'] = $this->PeriodOverPeriodGroup($keyword, $sources);
        $data['engagement_rate'] = $this->totalFromEngagementRate($keyword, $sources, $this->start_date, $this->end_date, 'current_period');
        $data['engagement_rate_previous'] = $this->totalFromEngagementRate($keyword, $sources, $this->start_date_previous, $this->end_date_previous, 'previous_period');
        return parent::handleRespond($data);
    }

    public function PeriodOverPeriodGroup($keywords, $sources)
    {

//        $sourceIds = $sources->pluck('id')->all();
        $keywordIds = $keywords->pluck('id')->all();

        $channel_message_current = $this->total_message_by_source($keywordIds, $this->start_date, $this->end_date);
        $channel_message_previous = $this->total_message_by_source($keywordIds, $this->start_date_previous, $this->end_date_previous);
        $data = array();
        foreach ($sources as $item) {
            $message_current = 1;
            $message_previous = 1;
            foreach ($channel_message_current as $current) {
                if ($current->source_id == $item->id) {
                    $message_current = $current->total_messages;
                    break;
                }
            }
            foreach ($channel_message_previous as $current) {
                if ($current->source_id == $item->id) {
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

    private function total_message_by_source($keywordIds, $start_date, $end_date)
    {

        $result = DB::table('messages');
        if ($this->source_id) {
            $result->where('source_id', $this->source_id);
        }

        $result->whereIn('messages.keyword_id', $keywordIds)
            ->whereBetween('messages.message_datetime', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->groupBy('messages.source_id')
            ->select('messages.source_id', DB::raw('COUNT(*) as total_messages'));

        return $result->get();
    }

    private function totalFromEngagementRate($keywords, $sources, $start_date, $end_date, $value_name)
    {
        $labels = parent::listSource();
        $keywordIds = $keywords->pluck('id')->all();
        $engagement = DB::table('messages')
            ->select([
                'messages.source_id as source_id',
                DB::raw('SUM(tbl_messages.number_of_comments + tbl_messages.number_of_shares + tbl_messages.number_of_reactions) as engagement_count')
            ])
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($this->source_id) {
            $engagement->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $engagement->whereIn('source_id', $source_ids);
        }

        $engagementResults = $engagement->groupBy('messages.source_id')->get();

        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        foreach ($engagementResults as $item) {
            $source_name = self::matchSourceName($sources, $item->source_id);
            $index_label = array_search($source_name, $labels['labels']);

            $data['value'][$value_name]['data'][$index_label] = $item->engagement_count;
        }

        return $data;
    }

    public function sentimentBy(Request $request)
    {
        $sources = self::getAllSource();
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);

        $keywordIds = $keywords->pluck('id')->all();
        $campaignId = $this->campaign_id;
        $data = null;

        $data['sentiment_score'] = $this->totalFromMessageResultSemetic($keywordIds, $this->start_date, $this->end_date, "current period", "current_period");
        $data['sentiment_score_previous'] = $this->totalFromMessageResultSemetic($keywordIds, $this->start_date_previous, $this->end_date_previous, "previous period", "previous_period");
        $data['channel_by_sentiment'] = $this->ChannelBySentiment2Group($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date);
        $data['sentiment_by_level'] = $this->SentimentLevelGroup($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date);

        return parent::handleRespond($data);
    }


    private function ChannelBySentiment2Group($keywordIds, $sources, $start_date, $end_date)
    {
        /*$sources = self::getAllSource();

        $sourceIds = $sources->pluck('id')->all();*/

        /*if (!$this->user_login->is_admin) {
            $sourceIds = $source_id->whereIn('name', $this->organization_group->platform);
        }*/

        $channel_message_all = $this->total_message_by_source($keywordIds, $start_date, $end_date);

        $data = [];
        $totalValue = 0;

        foreach ($channel_message_all as $item) {
            $totalValue += $item->total_messages;
            $source = self::matchSource($sources, $item->source_id);
            if ($source != null) {
                $data[] = [
                    'keyword_name' => $source->name,
                    'total_value' => $item->total_messages,
                    'source_color' => $source->color ?? null
                ];
            }
        }
        $data = array_merge([['keyword_name' => 'all', 'total_value' => $totalValue]], $data);
        return $data;
    }

    public function SentimentLevelGroup($campaignId, $keywordIds, $sources, $start_date, $end_date)
    {
        if ($this->source_id !== 'all') {
            $sources = $this->source_id;
        }

        if ($this->source_id == "") {
            $source_id = Sources::where('status', 1)->get();
            $sourceIds = $source_id->pluck('id')->all();
            $sources = implode(',', $sourceIds);
        }

        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        if ($keyword->get()) {
            $keyword = $keyword->get();
            $keywordIds = $keyword->pluck('id')->all();
            $keywords = implode(',', $keywordIds);
        }

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
            AND s.id IN ($sources)
            AND k.id IN ($keywords)
            AND m.message_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
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
            $i['positive_total'] = $positive;
            $i['negative_total'] = $negative;
            $i['neutral_total'] = $neutral;
            $i['positive'] = self::point_two_digits(($positive / $total) * 100);
            $i['negative'] = self::point_two_digits(($negative / $total) * 100);
            $i['neutral'] = self::point_two_digits(($neutral / $total) * 100);
            $totals['positive'] += intval($item->positive);
            $totals['negative'] += intval($item->negative);
            $totals['neutral'] += intval($item->neutral);
            $totals['total'] += $total;
            $items[] = $i;
        }
        $positive = $totals['total'] ? self::point_two_digits(($totals['positive'] / $totals['total']) * 100) : 0;
        $negative = $totals['total'] ? self::point_two_digits(($totals['negative'] / $totals['total']) * 100) : 0;
        $neutral = $totals['total'] ? self::point_two_digits(($totals['neutral'] / $totals['total']) * 100) : 0;
        $totals['positive'] = $positive;
        $totals['negative'] = $negative;
        $totals['neutral'] = $neutral;
        $result[] = $totals;
        foreach ($items as $item) {
            $result[] = $item;
        }
        return $result;
    }

    private function totalFromMessageResultSemetic($keywordIds, $start_date, $end_date, $keyword_name, $value_name)
    {
        $labels = parent::listSource();

        $data = [
            'labels' => $labels['labels'],
            'value' => [$value_name => ['data' => array_fill(0, count($labels['labels']), 0)]]
        ];

        $engagement = DB::table('messages')
            //->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            //->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            //->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('messages.keyword_id', $keywordIds)
            //->where('keywords.campaign_id', $campaignId)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('messages.source_id')
            ->select([
                'messages.source_id',
                /*'sources.name as source_name',*/
                DB::raw('COUNT(*) as count')
            ]);

        if ($this->source_id) {
            $engagement->where('source_id', $this->source_id);
        }

        $engagement = $engagement->pluck('count', 'source_is');

        $data['value'][$value_name]['keyword_name'] = $keyword_name;
        foreach ($engagement as $source_id => $count) {
            $index_label = array_search($source_id, $data['labels']);
            $data['value'][$value_name]['data'][$index_label] = $count;
        }

        return $data;
    }

    public function ChannelBySentiment2(Request $request)
    {
        $data = null;
        $source_id = Sources::where('status', 1)->get();
        if (!$this->user_login->is_admin) {
            $source_id = $source_id->whereIn('name', $this->organization_group->platform);
        }

        $channal_message_all = $this->total_message_by_source_id($this->start_date, $this->end_date, "all");

        $data["all"] = [
            "keyword_name" => "All",
            "total_value" => $channal_message_all,
        ];
        foreach ($source_id as $item) {

            $channal_message = $this->total_message_by_source_id($this->start_date, $this->end_date, $item->id);
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


    private function countChannelTable($keywordIds, $start_date, $end_date, $source_id = null)
    {
        $data = DB::table('messages')
            ->select([
                /*
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'sources.name as source_name',*/
                'messages.source_id as source_id',
                'messages.keyword_id as keyword_id',
                'messages.message_datetime as date_m',
                'messages.device as device',
                'messages.reference_message_id as reference_message_id',
                'messages.author as author',
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        if ($source_id) {
            $data->where('source_id', $source_id);
        }

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

    private function raw_message($keywordIds, $start_date, $end_date)
    {
        $data = DB::table('messages')
            ->select([
                'messages.id as id',
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.message_datetime as date_m',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'sources.name as source_name',*/
                /*'messages.device as device',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_reactions as number_of_reactions',
                'messages.number_of_shares as number_of_shares',*/
                DB::raw('count(id) as total_messages'),
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
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
