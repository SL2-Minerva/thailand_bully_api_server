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
    private $start_date, $end_date, $period;

    private $start_date_previous, $end_date_previous;
    private $campaign_id, $source_id, $keyword_id;

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
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

        if ($request->period === 'custom_range') {
            $this->start_date_previous = $this->date_carbon($request->start_date_period);
            $this->end_date_previous = $this->date_carbon($request->end_date_period);
        }

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
        $data['period_over_period'] = $this->PeriodOverPeriodGroup($campaignId, $keywordIds, $sources);

        $data['engagement_rate'] = $this->totalFromEngagementRate($campaignId, $keywordIds, $sources, $this->start_date, $this->end_date, 'current_period');
        $data['engagement_rate_previous'] = $this->totalFromEngagementRate($campaignId, $keywordIds, $sources, $this->start_date_previous, $this->end_date_previous, 'previous_period');

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriodGroup($campaignId, $keywordIds, $sources)
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

    private function totalFromEngagementRate($campaignId, $keywordIds, $sources, $start_date, $end_date, $value_name)
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
        $data['sentiment_score_previous'] = $this->totalFromMessageResultSemetic($campaignId, $keywordIds, $sources, $this->start_date_previous, $this->end_date_previous, "current period", "current_period");
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
        $sourceIds = $sources->pluck('id')->all();
        /*  $query = "SELECT
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
  */

        $data = DB::table('messages')
            ->select('sources.id AS source_id', 'sources.name AS source_name')
            ->selectRaw('SUM(CASE WHEN tbl_classifications.name = "Positive" THEN 1 ELSE 0 END) AS positive')
            ->selectRaw('SUM(CASE WHEN tbl_classifications.name = "Negative" THEN 1 ELSE 0 END) AS negative')
            ->selectRaw('SUM(CASE WHEN tbl_classifications.name = "Neutral" THEN 1 ELSE 0 END) AS neutral')
            ->leftJoin('keywords', 'keywords.id', '=', 'messages.keyword_id')
            ->leftJoin('message_results', 'messages.id', '=', 'message_results.message_id')
            ->leftJoin('classifications', 'classifications.id', '=', 'message_results.classification_id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->where('keywords.campaign_id', $campaignId)
            ->whereIn('sources.id', $sourceIds)
            ->whereBetween('messages.message_datetime', [$start_date.' 00:00:00', $end_date.' 23:59:59'])
            ->groupBy('source_id')
            ->get();

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
        $positive = self::point_two_digits(($totals['positive'] / $totals['total']) * 100);
        $negative = self::point_two_digits(($totals['negative'] / $totals['total']) * 100);
        $neutral = self::point_two_digits(($totals['neutral'] / $totals['total']) * 100);
        $totals['positive'] = $positive;
        $totals['negative'] = $negative;
        $totals['neutral'] = $neutral;
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

    public function dailyBy(Request $request)
    {
        $source_group = $this->organization_group->platform;
        $campaignId = $this->campaign_id;
        if ($this->user_login->is_admin) {
            $sources = Sources::where('status', 1)->get();
        } else {
            $sources = Sources::where('status', 1)
                ->whereIn('name', $source_group)
                ->get();
        }

        $keyword = Keyword::where('campaign_id', $campaignId);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }
        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = null;

        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $sourceIds = $sources->pluck('id')->all();
        $dataCurrent = $this->queryMessageChannel($campaignId, $keywordIds, $sourceIds, $start_date, $end_date);
        $dataPrevious = $this->queryMessageChannel($campaignId, $keywordIds, $sourceIds, $this->start_date_previous, $this->end_date_previous);
        
        $data['daily_message'] = $this->DailyChannelGroup($dataCurrent, $keywordIds, $sources, $start_date, $end_date);
        $data['percentage_of_messages_current'] = $this->PercentageToCal($dataCurrent, $keywordIds, $sources, $start_date, $end_date);
        $data['percentage_of_messages_previous'] = $this->PercentageToCal($dataPrevious, $keywordIds, $sources, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);

    }

    private function queryMessageChannel($campaignId, $keywordIds, $sourceIds, $start_date, $end_date)
    {
        return DB::table('messages')
            ->selectRaw('COUNT(*) AS total_at_date, source_id, DATE(message_datetime) as date')
            ->leftJoin('keywords AS k', 'k.id', '=', 'messages.keyword_id')
            ->where('k.campaign_id', $campaignId)
            ->whereIn('k.id', $keywordIds)
            ->whereIn('messages.source_id', $sourceIds)
            ->whereBetween('messages.message_datetime', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])
            ->groupBy('source_id', DB::raw('DATE(message_datetime)'))
            ->get()
            ->keyBy(function ($item) {
                return $item->source_id . '-' . $item->date;
            });
    }

    private function PercentageToCal($data, $keywordIds, $sources, $start_date, $end_date)
    {
        $result = [];
        $calculate = [];
        $totalSum = 0;

        foreach ($data as $item) {
            $sourceId = $item->source_id;
            $totalSum += $item->total_at_date;

            if (!isset($calculate[$sourceId])) {
                $calculate[$sourceId] = [
                    "source_id" => $sourceId,
                    "message_count" => 0,
                ];
            }

            $calculate[$sourceId]['message_count'] += $item->total_at_date;
        }

        foreach ($sources as $source) {
            $sourceId = $source->id;

            // Check if the source_id exists in the $calculate array
            if (isset($calculate[$sourceId])) {
                $percentage = ($calculate[$sourceId]['message_count'] / $totalSum) * 100;
                $calculate[$sourceId]['percentage'] = round($percentage, 2);
                $calculate[$sourceId]['source_name'] = $source->name;
            } else {
                // If the source_id doesn't exist in the $calculate array, set percentage to 0
                $calculate[$sourceId] = [
                    "source_id" => $sourceId,
                    "source_name" => $source->name,
                    "message_count" => 0,
                    "percentage" => 0,
                ];
            }
        }

        $calculate = array_values($calculate);
        $result['total_message'] = $totalSum;
        $result['date_text'] = date('d/m/Y', strtotime($start_date)) . ' - ' . date('Y/m/d', strtotime($end_date));
        $result['value'] = $calculate;
        return $result;
    }

    public function DailyChannelGroup($data, $keywordIds, $sources, $start_date, $end_date)
    {

        $result = [];
        $sortedDaysOfWeek = [1, 2, 3, 4, 5, 6, 7];
        $firstDayOfWeek = 1; // Monday

        foreach ($sources as $source) {
            $s = [
                "source_id" => $source->id,
                "source_name" => $source->name,
                "value" => [],
            ];

            $currentDate = $start_date;
            $prevDayOfWeek = null;
            $sum = 0;

            while ($currentDate <= $end_date) {
                $key = $source->id . '-' . $currentDate;
                $item = $data->get($key);

                if ($item) {
                    $currentDayOfWeek = date('N', strtotime($currentDate));

                    if ($currentDayOfWeek == $prevDayOfWeek || $prevDayOfWeek === null) {
                        $sum += $item->total_at_date;
                    } else {
                        if ($prevDayOfWeek !== null) {
                            $s['value'][] = [
                                "total_at_date" => $sum,
                                "source_id" => $source->id,
                                "date" => $prevDate,
                            ];
                        }

                        $sum = $item->total_at_date;
                    }

                    $prevDayOfWeek = $currentDayOfWeek;
                    $prevDate = $currentDate;
                } else {
                    // Handle the case when no data is available for the current date
                    if ($prevDayOfWeek !== null && $currentDayOfWeek != $prevDayOfWeek) {
                        $s['value'][] = [
                            "total_at_date" => $sum,
                            "source_id" => $source->id,
                            "date" => $prevDate,
                        ];
                        $prevDayOfWeek = null;
                    }
                }

                $currentDate = date('Y-m-d', strtotime($currentDate . ' + 1 day'));
            }

            // Add the last sum value if there is no data for the end date
            if ($prevDayOfWeek !== null) {
                $s['value'][] = [
                    "total_at_date" => $sum,
                    "source_id" => $source->id,
                    "date" => $prevDate,
                ];
            }

            // Sort the value array based on the days of the week
            $s['value'] = $this->sortArrayByDaysOfWeek($s['value'], $sortedDaysOfWeek, $firstDayOfWeek);

            $result[] = $s;
        }

        return $result;
    }

    private function sortArrayByDaysOfWeek($array, $sortedDaysOfWeek, $firstDayOfWeek)
    {
        $sortedArray = [];
        
        foreach ($sortedDaysOfWeek as $dayOfWeek) {
            $matchingItems = array_filter($array, function ($item) use ($dayOfWeek) {
                $date = $item['date'];
                $currentDayOfWeek = date('N', strtotime($date));
                return $currentDayOfWeek == $dayOfWeek;
            });

            if (!empty($matchingItems)) {
                $sum = array_sum(array_column($matchingItems, 'total_at_date'));
                $firstMatchingItem = reset($matchingItems);
                $dayOfWeekName = date('D', strtotime($firstMatchingItem['date']));
                $sortedArray[] = [
                    "total_at_date" => $sum,
                    "day_of_week" => $dayOfWeekName,
                ];
            }
        }

        return $sortedArray;
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
            $data['value'] = array_values($data['value']);
        }

        return $data;
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

}