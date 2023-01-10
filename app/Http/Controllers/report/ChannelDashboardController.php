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

    public function __construct(Request $request)
    {

        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->campaign_id = $request->campaign_id;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);

    }

    public function PercentageOfChannel(Request $request)
    {

        $data = null;
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($request->campaign_id, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($request->campaign_id, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($campaign_id, $start_date, $end_date)
    {
        $data = null;
        $percentage_of_channal = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('source_id');

        foreach ($percentage_of_channal->get() as $channal) {
            $source_id = $channal->source_id;
            $data[$source_id]['keyword_id'] = $channal->keyword_id;
            $data[$source_id]['keyword_name'] = $channal->keyword_name;
            $data[$source_id]['campaign_id'] = $channal->campaign_id;
            $data[$source_id]['campaign_name'] = $channal->campaign_name;
            $data[$source_id]['organization_id'] = $channal->organization_id;
            $data[$source_id]['organizations_name'] = $channal->organizations_name;
            $data[$source_id]['source_id'] = $channal->source_id;
            $data[$source_id]['source_name'] = $channal->source_name;

            $channal_message = $this->channelTable($campaign_id, $start_date, $end_date, $source_id);
            $channal_message_total = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->sum('total_at_date');

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
        $data = null;

        $daily_messages = DailyMessage::where('campaign_id', $request->campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($daily_messages->get() as $daily_message) {

            $source_id = $daily_message->source_id;
            $data[$source_id]['source_id'] = $daily_message->source_id;
            $data[$source_id]['source_name'] = $daily_message->source_name;
            $data[$source_id]['campaign_id'] = $daily_message->campaign_id;
            $data[$source_id]['campaign_name'] = $daily_message->campaign_name;
            $data[$source_id]['organization_id'] = $daily_message->organization_id;
            $data[$source_id]['organizations_name'] = $daily_message->organizations_name;

            $nestData = [
                'keyword_id' => $daily_message->keyword_id,
                'keyword_name' => $daily_message->keyword_name,
                'date_m' => $daily_message->date_m,
                'total_at_date' => $daily_message->total_at_date
            ];

            $data[$source_id]['value'][] = $nestData;
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function ChannelByDay(Request $request)
    {

        $table =  'daily_message';
        $data = parent::listDataByType('channel_by_day', $table, $this->campaign_id, $this->start_date, $this->end_date, null, null, 'total_at_date' );

        return parent::handleRespond($data);


        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
                39,
                19,
                38,
                47,
                16,
                30
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
                23,
                56,
                32,
                15,
                78,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                15,
                67,
                23,
                45,
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                67,
                23,
                16,
                38,
                89,
                21,
                45,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                45,
                23,
                56,
                22,
                35,
                67,
                21,
            ]
        ];

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

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
                39,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
                23,
                56,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                45,
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                67,
                89,
                21,
                45,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                45,
                23,
                56,
                21,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelByDevice(Request $request)
    {
        $table =  'daily_message_device';
        $period = $request->period;

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $campaign_id = $request->campaign_id;

        $data = parent::listDataByType('device', $table, $campaign_id, $start_date, $end_date, null, null, null, 'source_id');

        return parent::handleRespond($data);
    }

    public function ChannelByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
                19,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                65,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                67,
                89,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                23,
                56,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelBySentiment(Request $request)
    {
        $table =  'message_result_group';
        $period = $request->period;

        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $campaign_id = $request->campaign_id;
        $data = parent::listDataByType('sentiment', $table, $campaign_id, $start_date, $end_date, null, null, null, null);

        return parent::handleRespond($data);
    }

    public function ChannelBullyLevel(Request $request)
    {

        $data = null;

        $channal_bully = MessageResultGroup::where('classification_type_id', 3)
            ->where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $data = null;
        foreach ($channal_bully->get() as $item) {
            if (isset($data[$item->source_id])) {
                $data[$item->source_id]['value'] =  $data[$item->source_id]['value'] + 1;

            } else {
                $data[$item->source_id] = [
                    'value' => 1,
                    "message_id" => $item->message_id,
                    "source_name" => $this->source_name($item->source_id),
                    "keyword_name" => $item->keyword_name,
                    "bully_type" => $this->bully_type_name($item->classification_id),
                ];
            }
        }

        return parent::handleRespond(array_values($data));
    }

    public function ChannelBullyType(Request $request)
    {
        $data = null;

        $channal_bully = MessageResultGroup::where('classification_type_id', 2)
            ->where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($channal_bully->get() as $item) {
            if (isset($data[$item->source_id])) {
                $data[$item->source_id]['value'] =  $data[$item->source_id]['value'] + 1;

            } else {
                $data[$item->source_id] = [
                    'value' => 1,
                    "message_id" => $item->message_id,
                    "source_name" => $this->source_name($item->source_id),
                    "keyword_name" => $item->keyword_name,
                    "bully_type" => $this->bully_type_name($item->classification_id),
                ];
            }
        }

        return parent::handleRespond(array_values($data));
    }

    public function bully_type_name($class_id)
    {
        $classfication = Classification::where('id', $class_id)->first();
        return $classfication->name;
    }

    private function total_message_by_source_id($table, $campaign_id, $start_date, $end_date, $source_id, $field) {
        if ($source_id === "all") {
            $channal_message_current = DB::table($table)->where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->sum($field);

            return $channal_message_current;
        }
        $channal_message_current = DB::table($table)->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('source_id', $source_id)
            ->sum($field);

        return $channal_message_current;
    }

    public function PeriodOverPeriod(Request $request)
    {
        $source = Sources::where('status', 1)->get();
        foreach ($source as $item) {

            $channal_message_current = $this->total_message_by_source_id('daily_message', $request->campaign_id, $this->start_date, $this->end_date, $item->id, 'total_at_date');
            $channal_message_previous = $this->total_message_by_source_id('daily_message', $request->campaign_id, $this->start_date_previous, $this->end_date_previous, $item->id, 'total_at_date');

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
        $sentiment_current = MessageResultSemetic::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($sentiment_current->groupBy('source_id')->get() as $item) {

            $sum_current = $this->sum_by_source_id($request->campaign_id, $this->start_date, $this->end_date, $item->source_id, 'engagement');
            $sum_previous = $this->sum_by_source_id($request->campaign_id, $this->start_date_previous, $this->end_date_previous, $item->source_id, 'engagement');

            $data['current_period'][] = [
                "keyword_name" => "current period",
                "source_name" => $this->source_name($item->source_id),
                "source_id" => $item->source_id,
                "data" => $sum_current
            ];

            $data['previous_period'][] = [
                "keyword_name" => "previous period",
                "source_name" => $this->source_name($item->source_id),
                "source_id" => $item->source_id,
                "data" => $sum_previous
            ];
        }

        return parent::handleRespond($data);
    }

    private function sum_by_source_id($campaign_id, $start_date, $end_date, $source_id, $sum_from) {
        $data = MessageResultSemetic::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('source_id', $source_id)
            ->sum($sum_from);

        return $data;
    }

    public function SentimentScore(Request $request)
    {

        $data = null;
        $sentiment_current = MessageResultSemetic::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        foreach ($sentiment_current->groupBy('source_id')->get() as $item) {

            $sum_current = $this->sum_by_source_id($request->campaign_id, $this->start_date, $this->end_date, $item->source_id, 'total_sem');
            $sum_previous = $this->sum_by_source_id($request->campaign_id, $this->start_date_previous, $this->end_date_previous, $item->source_id, 'total_sem');

            $data['current_period'][] = [
                "keyword_name" => "current period",
                "source_name" => $this->source_name($item->source_id),
                "source_id" => $item->source_id,
                "data" => $sum_current
            ];

            $data['previous_period'][] = [
                "keyword_name" => "previous period",
                "source_name" => $this->source_name($item->source_id),
                "source_id" => $item->source_id,
                "data" => $sum_previous
            ];
        }

        return parent::handleRespond($data);
    }

    public function ChannelBySentiment2(Request $request)
    {
        $data = null;
        $source = Sources::where('status', 1)->get();
        $channal_message_all = $this->total_message_by_source_id('daily_message', $request->campaign_id, $this->start_date, $this->end_date, "all", 'total_at_date');
        $data["all"] = [
            "keyword_name" => "All",
            "total_value" => $channal_message_all,
        ];
        foreach ($source as $item) {

            $channal_message = $this->total_message_by_source_id('daily_message', $request->campaign_id, $this->start_date, $this->end_date, $item->id, 'total_at_date');
            $data[$item->name] = [
                "keyword_name" => $item->name,
                "total_value" => $channal_message,
            ];
        }

        return parent::handleRespond($data);
    }

    public function SentimentLevel(Request $request)
    {
        $data = null;
        $percentage_of_channal = MessageResultGroup::where('campaign_id', $request->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->groupBy('source_id');

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

            $sum = MessageResultGroup::where('campaign_id', $request->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->where('source_id', $channal->source_id)->get();
            foreach ($sum as $item) {
                if (isset($item->classification_name) && $item->classification_name === "Negative") {
                    $data[$source_id]['negative'] = $data[$source_id]['negative'] + 1;
                } else if(isset($item->classification_name) && $item->classification_name === "Positive") {
                    $data[$source_id]['positive'] = $data[$source_id]['positive'] + 1;
                } else if(isset($item->classification_name) && $item->classification_name === "Neutral") {
                    $data[$source_id]['neutral'] = $data[$source_id]['neutral'] + 1;
                }
            }
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function source_name($source_id)
    {
        $source = Sources::where('id', $source_id)->first();
        return $source->name;
    }
}
