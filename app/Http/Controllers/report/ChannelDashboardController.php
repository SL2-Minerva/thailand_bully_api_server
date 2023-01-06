<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use App\Models\PercentageOfMessages;
use App\Models\DailyMessage;
use App\Models\Sources;

class ChannelDashboardController extends Controller
{
    public function PercentageOfChannel(Request $request)
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
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($campaign_id, $start_date, $end_date);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($campaign_id, $start_date_previous, $end_date_previous);

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
        $campaign_id = $request->campaign_id;
        $source = $request->source;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        if ($request->start_date) {
            $start_date = $this->date_carbon($request->start_date);
        }

        if ($request->end_date) {
            $end_date = $this->date_carbon($request->end_date);
        }
        $daily_messages = DailyMessage::where('campaign_id', $campaign_id);
        $daily_messages = $daily_messages->whereBetween('date_m', [$start_date, $end_date]);
        // if ($source !== 'all') {
        //     $daily_messages = $daily_messages->where('source_id', $request->source);
        // }

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
        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

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
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
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
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
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
                45,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                23,
                56,
                21,
            ]
        ];

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
        $data['labels'] = [
            "Negative",
            "Neutral",
            "Positive",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                20,
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
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
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
                45,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                23,
                56,
                21,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelBullyLevel(Request $request)
    {
        $data['labels'] = [
            "Level 0",
            "Level 1",
            "Level 2",
            "Level 3",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                19,
                38,
                47,
                16,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
                32,
                78,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                15,
                45,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                67,
                16,
                38,
                89,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                45,
                23,
                67,
                21,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelBullyType(Request $request)
    {
        $data['labels'] = [
            "No Bully",
            "Gossip",
            "Harassment",
            "Exclusion",
            "Hate Speech",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                19,
                38,
                47,
                16,
                30,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                12,
                16,
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
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                45,
                23,
                56,
                67,
                21,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $campaign_id = $request->campaign_id;
        if (!$campaign_id) {
            return parent::handleNotFound('Campaign id is required');
        }

        $start_date = $this->date_carbon($request->start_date);
        $end_date = $this->date_carbon($request->end_date);
        $start_date_period = $this->date_carbon($request->start_date_period);
        $end_date_period = $this->date_carbon($request->end_date_period);
        $period = $request->period;

        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);

        if ($period === "customrange") {
            $start_date_previous = $this->get_previous_date($start_date_period, $period);
            $end_date_previous = $this->get_previous_date($end_date_period, $period);
        }

        $source = Sources::where('status', 1)->get();
        foreach ($source as $item) {
            $channal_message_current = DailyMessage::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->where('source_id', $item->id)
                ->sum('total_at_date');

            $channal_message_previous = DailyMessage::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date_previous, $end_date_previous])
                ->where('source_id', $item->id)
                ->sum('total_at_date');

            $comparison = $channal_message_current - $channal_message_previous;
            $percentage = (($channal_message_current - $channal_message_previous) / ($channal_message_previous === 0 ? 1 : $channal_message_previous)) * 100;
            
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
        $data['labels'] = [
            "facebook",
            "twitter",
            "youtube",
            "instagram",
            "pantip",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "current period",
            "data" => [
                100, 290, 283, 182, 177,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "previous period",
            "data" => [
                39, 89, 134, 82, 129,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function SentimentScore(Request $request)
    {
        $data['labels'] = [
            "facebook",
            "twitter",
            "youtube",
            "instagram",
            "pantip",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "current period",
            "data" => [
                100, 290, 283, 182, 177,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "previous period",
            "data" => [
                39, 89, 134, 82, 129,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelBySentiment2(Request $request)
    {
        $data[] = [
            "keyword_name" => "All",
            "total_value" => "323"
        ];

        $data[] = [
            "keyword_name" => "Facebook",
            "total_value" => "40"
        ];

        $data[] = [
            "keyword_name" => "Twitter",
            "total_value" => "50"
        ];

        $data[] = [
            "keyword_name" => "Youtube",
            "total_value" => "23"
        ];

        $data[] = [
            "keyword_name" => "Instagram",
            "total_value" => "160"
        ];

        $data[] = [
            "keyword_name" => "Pantip",
            "total_value" => "50"
        ];

        return parent::handleRespond($data);
    }

    public function SentimentLevel(Request $request)
    {
        $data[] = [
            "keyword_id" => 1,
            "keyword_name" => "All",
            "campaign_id" => 1,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 1,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];

        $data[] = [
            "keyword_id" => 2,
            "keyword_name" => "Facebook",
            "campaign_id" => 2,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 2,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];

        $data[] = [
            "keyword_id" => 3,
            "keyword_name" => "Twitter",
            "campaign_id" => 3,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 3,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];

        $data[] = [
            "keyword_id" => 4,
            "keyword_name" => "Youtube",
            "campaign_id" => 4,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 4,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];

        $data[] = [
            "keyword_id" => 5,
            "keyword_name" => "Instagram",
            "campaign_id" => 5,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 5,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];

        $data[] = [
            "keyword_id" => 6,
            "keyword_name" => "Pantip",
            "campaign_id" => 6,
            "campaign_name" => "campaign_name 1",
            "organization_id" => 6,
            "organizations_name" => "organizations_name 1",
            "negative" => 10,
            "neutral" => 30,
            "positive" => 60,
        ];


        return parent::handleRespond($data);
    }
}
