<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Carbon;
use App\Models\DailyMessage;
use App\Models\PercentageOfMessages;

class VoiceDashboardController extends Controller
{
    public function PercentageOfMessage(Request $request)
    {
        $data = null;
        $campaign_id = $request->campaign_id;
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

        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);
        $prcentage_of_messages_current = $this->percentageOfMessages($campaign_id, $start_date, $end_date);
        $prcentage_of_messages_previous = $this->percentageOfMessages($campaign_id, $start_date_previous, $end_date_previous);
        foreach ($prcentage_of_messages_previous as $item) {
            $data['previous_period']['label'][] = $item['keyword_name'];
            foreach ($item['value'] as $item_value) {
                $data['previous_period']['data'][] = $item_value['percentage'];
            }
            $data['previous_period']['total'] = array_sum($data['previous_period']['data']);
        }

        foreach ($prcentage_of_messages_current as $item) {
            $data['current_period']['label'][] = $item['keyword_name'];
            foreach ($item['value'] as $item_value) {
                $data['current_period']['data'][] = $item_value['percentage'];
            }
            $data['current_period']['total'] = array_sum($data['previous_period']['data']);
        }

        return parent::handleRespond($data);
    }

    private function percentageOfMessages($campaign_id, $start_date, $end_date)
    {
        $data = null;
        $percentage_of_messages = PercentageOfMessages::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->groupBy('keyword_name');

        foreach ($percentage_of_messages->get() as $percentage_of_message) {
            $keyword_id = $percentage_of_message->keyword_id;
            $data[$keyword_id]['keyword_id'] = $percentage_of_message->keyword_id;
            $data[$keyword_id]['keyword_name'] = $percentage_of_message->keyword_name;
            $data[$keyword_id]['campaign_id'] = $percentage_of_message->campaign_id;
            $data[$keyword_id]['campaign_name'] = $percentage_of_message->campaign_name;
            $data[$keyword_id]['organization_id'] = $percentage_of_message->organization_id;
            $data[$keyword_id]['organizations_name'] = $percentage_of_message->organizations_name;

            $message_keyword = $this->messagesTable($campaign_id, $start_date, $end_date, $keyword_id);
            $message_total = PercentageOfMessages::where('campaign_id', $campaign_id)
                ->whereBetween('date_m', [$start_date, $end_date])
                ->sum('total_at_keyword');

            $nestData = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $this->point_two_digits(($message_keyword / $message_total) * 100),
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    private function messagesTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_message = PercentageOfMessages::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_at_keyword');

        return $total_message;
    }

    public function dailyMessage(Request $request)
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

            $keyword_id = $daily_message->keyword_id;
            $data[$keyword_id]['keyword_id'] = $daily_message->keyword_id;
            $data[$keyword_id]['keyword_name'] = $daily_message->keyword_name;
            $data[$keyword_id]['campaign_id'] = $daily_message->campaign_id;
            $data[$keyword_id]['campaign_name'] = $daily_message->campaign_name;
            $data[$keyword_id]['organization_id'] = $daily_message->organization_id;
            $data[$keyword_id]['organizations_name'] = $daily_message->organizations_name;

            $nestData = [
                'source_id' => $daily_message->source_id,
                'source_name' => $daily_message->source_name,
                'date_m' => $daily_message->date_m,
                'total_at_date' => $daily_message->total_at_date
            ];

            $data[$keyword_id]['value'][] = $nestData;
        }

        if ($data) {
            return parent::handleRespond(array_values($data));
        }

        return parent::handleRespond($data);
    }

    public function MessageByDay(Request $request)
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

    public function MessageByTime(Request $request)
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

    public function MessageByDevice(Request $request)
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

    public function MessageByAccount(Request $request)
    {
        $data['labels'] = [
            "Post Owner",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                89,
                45,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                56,
                21,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function MessageByChannel(Request $request)
    {
        $data['labels'] = [
            "Facebook",
            "Twitter",
            "Instagram",
            "Youtube",
            "Pantip",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Keyword 1",
            "data" => [
                45,
                23,
                56,
                67,
                21,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Keyword 2",
            "data" => [
                19,
                38,
                47,
                16,
                30,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Keyword 3",
            "data" => [
                12,
                16,
                32,
                15,
                78,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Keyword 4",
            "data" => [
                15,
                45,
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Keyword 5",
            "data" => [
                67,
                23,
                16,
                38,
                89,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function MessageBySentiment(Request $request)
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

    public function MessageByLevel(Request $request)
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

    public function MessageByType(Request $request)
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

    public function NumberOfAccount(Request $request)
    {
        $data[] = [
            "name" =>  "Keyword 1",
            "data" => [
                44,
                55,
                41,
                67,
                22,
                43,
                21,
                49,
                29,
                36,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 2",
            "data" => [
                13,
                23,
                20,
                8,
                13,
                27,
                33,
                12,
                29,
                34,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 3",
            "data" => [
                11,
                17,
                15,
                15,
                21,
                14,
                15,
                13,
                65,
                29,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 4",
            "data" => [
                44,
                55,
                41,
                67,
                22,
                43,
                21,
                49,
                58,
                37,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        $data[] = [
            "name" =>  "Keyword 5",
            "data" => [
                30,
                23,
                20,
                8,
                13,
                27,
                33,
                12,
                62,
                43,
            ],
            "date" => [
                "01/10",
                "02/10",
                "03/10",
                "04/10",
                "05/10",
                "06/10",
                "07/10",
                "08/10",
                "09/10",
                "10/10",
            ]
        ];

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $data['total_messages'] = [
            "total_message" => 40000,
            "percentage" => "10",
            "type" => "minus",
        ];

        $data['total_account'] = [
            "total_message" => 200,
            "percentage" => "20",
            "type" => "plus",
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeComparison(Request $request)
    {
        $data[] = [
            "name" =>  "Mon.",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data[] = [
            "name" =>  "Tue.",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data[] = [
            "name" =>  "Wed.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        $data[] = [
            "name" =>  "Thu.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        $data[] = [
            "name" =>  "Fri.",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data[] = [
            "name" =>  "Sat.",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data[] = [
            "name" =>  "Sun.",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeSentiment(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "Negative",
            "data" => [
                10, 20, 30, 40, 50, 60, 70,
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Neutral",
            "data" => [
                10, 20, 30, 20, 60, 100, 70,
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Positive",
            "data" => [
                10, 20, 20, 20, 60, 100, 20,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Negative",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Neutral",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Positive",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20, 20,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeLevel(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "level 3",
            "data" => [
                10, 20, 30, 40, 50, 60, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 2",
            "data" => [
                10, 20, 30, 20, 60, 100, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 1",
            "data" => [
                10, 20, 20, 20, 60, 100, 20
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "level 0",
            "data" => [
                10, 20, 30, 20, 60, 100, 74
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 3",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 2",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 1",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "level 0",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
            ]
        ];

        return parent::handleRespond($data);
    }

    public function DayTimeType(Request $request)
    {
        $data['day_value'][] = [
            "name" =>  "Hate Speech",
            "data" => [
                10, 20, 30, 40, 50, 60, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Exclusion",
            "data" => [
                10, 20, 30, 20, 60, 100, 70
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Harassment",
            "data" => [
                10, 20, 20, 20, 60, 100, 20
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "Gossip",
            "data" => [
                10, 20, 30, 20, 60, 100, 74
            ]
        ];

        $data['day_value'][] = [
            "name" =>  "No Bully",
            "data" => [
                10, 20, 30, 20, 60, 100, 70,
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Hate Speech",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Exclusion",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Harassment",
            "data" => [
                10, 20, 20, 20, 60, 100, 20, 20, 30, 20, 60, 100, 100, 70, 90, 100, 10, 80, 10, 20, 90, 140, 20
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "Gossip",
            "data" => [
                10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 10, 20, 30, 40
            ]
        ];

        $data['time_value'][] = [
            "name" =>  "No Bully",
            "data" => [
                10, 20, 30, 20, 60, 100, 70, 40, 90, 140, 20, 20, 100, 70, 40, 90, 140, 80, 10, 20, 30, 20, 60, 100,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function ChannelPlatform(Request $request)
    {
        $data['previous_period']['label'] = [
            "Facebook",
            "Instagram",
            "Pantip",
            "Twitter",
            "Youtube",
        ];
        $data['previous_period']['data'] = [
            395, 285, 484, 291, 499,
        ];
        $data['previous_period']['total'] = 57392;

        $data['current_period']['label'] = [
            "Facebook",
            "Instagram",
            "Pantip",
            "Twitter",
            "Youtube",
        ];
        $data['current_period']['data'] = [
            623, 384, 282, 483, 823,
        ];
        $data['current_period']['total'] = 38273;

        return parent::handleRespond($data);
    }

    public function Device(Request $request)
    {
        $data['previous_period']['label'] = [
            "Andriod",
            "Iphone",
            "Web App"
        ];
        $data['previous_period']['data'] = [
            395, 291, 499,
        ];
        $data['previous_period']['total'] = 7392;

        $data['current_period']['label'] = [
            "Andriod",
            "Iphone",
            "Web App"
        ];
        $data['current_period']['data'] = [
            623, 483, 823,
        ];
        $data['current_period']['total'] = 3273;

        return parent::handleRespond($data);
    }

    public function ChannelDevice(Request $request)
    {
        $data['labels'][] = [
            "Andriod",
            "Facebook"
        ];

        $data['labels'][] = [
            "iPhone",
            "Twitter"
        ];

        $data['labels'][] = [
            "Web",
            "Youtube"
        ];

        $data['data'] = [
            44, 50, 6,
        ];

        return parent::handleRespond($data);
    }

    public function KeywordSentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Negative",
            "Neutral"
        ];

        $data['data'][] = [
            "name" => "All",
            "data" => [
                44, 50, 6,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 1",
            "data" => [
                80, 50, 100
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 2",
            "data" => [
                20, 40, 10
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 3",
            "data" => [
                44, 76, 45
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 4",
            "data" => [
                20, 40, 12
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 5",
            "data" => [
                45, 26, 30
            ]
        ];

        return parent::handleRespond($data);
    }

    public function KeywordBullyLevel(Request $request)
    {
        $data['labels'] = [
            "Level 0",
            "Level 1",
            "Level 2",
            "Level 3",
        ];

        $data['data'][] = [
            "name" => "All",
            "data" => [
                100, 150, 200, 150,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 1",
            "data" => [
                80, 50, 100, 49,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 2",
            "data" => [
                20, 40, 10, 19,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 3",
            "data" => [
                44, 76, 45, 100,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 4",
            "data" => [
                20, 30, 12, 30,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 5",
            "data" => [
                45, 26, 30, 80,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function KeywordBullyType(Request $request)
    {
        $data['labels'] = [
            "No Bully",
            "Gossip",
            "Harassment",
            "Exclusion",
            "Hate Speech",
        ];

        $data['data'][] = [
            "name" => "All",
            "data" => [
                45, 28, 45, 98, 73,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 1",
            "data" => [
                80, 50, 100, 49, 60,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 2",
            "data" => [
                20, 40, 10, 19, 100,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 3",
            "data" => [
                44, 76, 45, 100, 30,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 4",
            "data" => [
                20, 30, 12, 30, 80,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 5",
            "data" => [
                45, 26, 30, 80, 100,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function KeywordChannel(Request $request)
    {
        $data['labels'] = [
            "Facebook",
            "Pantip",
            "Twitter",
            "Youtube",
            "Instagram"
        ];

        $data['data'][] = [
            "name" => "All",
            "data" => [
                45, 28, 45, 98, 73,
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 1",
            "data" => [
                80, 50, 30, 40, 100
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 2",
            "data" => [
                20, 30, 40, 80, 20
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 3",
            "data" => [
                44, 76, 78, 13, 43
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 4",
            "data" => [
                20, 30, 48, 23, 53
            ]
        ];

        $data['data'][] = [
            "name" => "Keyword 5",
            "data" => [
                45, 26, 38, 53, 13
            ]
        ];

        return parent::handleRespond($data);
    }
}
