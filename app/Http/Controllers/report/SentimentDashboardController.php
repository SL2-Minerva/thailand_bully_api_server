<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SentimentDashboardController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;
    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;

    private $table = 'message_result_semetic';

    public function __construct(Request $request)
    {

        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
    }

    public function DailySeniment(Request $request)
    {
        $table = 'message_result_semetic';
        $data = null;
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $raw_pre = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous]);

        $data['sentiment'] = $this->sentiment($raw_current);
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($raw_current, $this->start_date, $this->end_date);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($raw_pre, $this->start_date_previous, $this->end_date_previous);

        return parent::handleRespond($data);
    }


    private function percentageOfMessages($raw, $start_date, $end_date)
    {

        $raw->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $items = $raw->get();

        $data = null;
        if ($items->count() <= 0) return null;

        $message_keyword = [];
        $message_total = 0;

        $column = 'total_sem';

        foreach ($items as $object) {
            $item = (array)$object;

            if (!isset($data[$item['classification_id']])) {

                $data[$item['classification_id']] = [
                    'keyword_id' => $item['classification_id'],
                    'keyword_name' => $item['classification_name'],
                    'campaign_id' => $item['campaign_id'],
                    'campaign_name' => $item['campaign_name'],
                ];
            }

            if (isset($message_keyword[$item['classification_id']])) {

                $message_keyword[$item['classification_id']] += $item[$column];
            } else {

                $message_keyword[$item['classification_id']] = $item[$column];
            }

            $message_total += $item[$column];
        }


        foreach ($message_keyword as $classification_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = self::point_two_digits(($value / $message_total) * 100);
            }

            $data[$classification_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
            ];
        }


        if ($data) {
            $data = array_values($data);
        }

        return $data;

    }

    private function sentiment($raw)
    {
        $labels = [
            "Positive",
            "Neutral",
            "Negative"
        ];

        $raw->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $items = $raw->get();

        $data = null;
        if ($items->count() <= 0) return null;

        foreach ($items as $item) {
            $index_label = array_search($item->classification_name, $labels);

            if (isset($data[$index_label])) {
                $data[$index_label]['value'][] = [
                    'date' => $item->date_m,
                    'source_id' => $item->source_id,
                    'total_at_date' => $item->total_sem
                ];
            } else {
                $data[$index_label] = [
                    'keyword_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'value' => []
                ];

                $data[$index_label]['value'][] = [
                    'date' => $item->date_m,
                    'source_id' => $item->source_id,
                    'total_at_date' => $item->total_sem
                ];
            }
        }
        if ($data) {
            $data = array_values($data);
        }
        return $data;
    }

    public function SentimentByDay(Request $request)
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

        $raw = DB::table($this->table)
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        $items = $raw->get();
        foreach ($items as $item) {
            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);

            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];
                $data['value'][$item->classification_id]['data'][$index_label] += $item->total_sem;

            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function SentimentByTime(Request $request)
    {

        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $raw = DB::table('message_result_semetic_d_m_y_h_i_s')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        $items = $raw->get();
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


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }
        return parent::handleRespond($data);
    }

    public function SentimentByDevice(Request $request)
    {

        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];


        $raw = DB::table('message_device_bully')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        $items = $raw->get();
        foreach ($items as $item) {

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
                    'campaign_id' => $item->campaign_id,
                    'campaign_name' => $item->campaign_name,
                    'source_id' => $item->source_id,
                    'source_name' => $item->source_name,
                    'data' => [0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += $item->total_at_date;
            }

        }

        if (isset($data['value']) && $data['value']) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);

    }

    public function SentimentByAccount(Request $request)
    {

        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        $table = 'sna_root_node';

        $infulencer_root = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);

        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                $data['value'][$infulencer->classification_id]['data'][0] += 1;
            } else {
                $data['value'][$infulencer->classification_id]['id'] = $infulencer->keyword_id;
                $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->keyword_name;
                $data['value'][$infulencer->classification_id]['data'][0] = 1;

            }
        }

        $table = 'sna_child_node';
        $follower_raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);


        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->classification_id]['data'][1])) {
                $data['value'][$follower->classification_id]['data'][1] += 1;
            } else {
                $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                $data['value'][$follower->classification_id]['data'][1] = 1;
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return parent::handleRespond($data);
    }

    public function SentimentByChannel(Request $request)
    {

        $data = parent::listSource();

        $table = 'message_result_bully';

        $raw = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date]);

        $items = $raw->get();

        foreach ($items as $item) {
            $index_label = array_search($item->source_name, $data['labels']);
            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                ];

                for ($i = 0; $i <= count($data['labels']); $i++) {
                    $data['value'][$item->classification_id]['data'][] = 0;
                }

                $data['value'][$item->classification_id]['data'][$index_label] +=1;
            }
        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return parent::handleRespond($data);
    }

    public function SentimentBullyLevel(Request $request)
    {

        $data['labels'] = [
            "Level 0",
            "Level 1",
            "Level 2",
            "Level 3",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Negative",
            "data" => [
                19,
                38,
                47,
                16,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Neutral",
            "data" => [
                12,
                16,
                32,
                78,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Positive",
            "data" => [
                15,
                45,
                23,
                53,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function SentimentBullyType(Request $request)
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
            "keyword_name" => "Negative",
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
            "keyword_name" => "Neutral",
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
            "keyword_name" => "Positive",
            "data" => [
                15,
                45,
                65,
                23,
                53,
            ]
        ];

        return parent::handleRespond([]);
    }

    public function PeriodOverPeriod(Request $request)
    {

        $data = null;
        $table = 'message_result_bully';
        $raw_current = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date, $this->end_date])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);
        $raw_previous = DB::table($table)->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date_previous, $this->end_date_previous])
            ->whereIn('classification_name', ['Positive', 'Negative', 'Neutral']);;


        $totalEngagement_current = $raw_current->sum('total_at_date');
        $totalEngagement_previous = $raw_previous->sum('total_at_date');


        $total_share_current = $raw_current->where('classification_name', 'Positive')->count();
        $total_share_previous = $raw_previous->where('classification_name', 'Positive')->count();

        $total_comment_current = $raw_current->where('classification_name', 'Neutral')->count();
        $total_comment_previous = $raw_previous->where('classification_name', 'Neutral')->count();

        $total_reactions_current = $raw_current->where('classification_name', 'Negative')->count();
        $total_reactions_previous = $raw_previous->where('classification_name', 'Negative')->count();

        $data['totalSentiment'] = [
            "totalValue" => parent::custom_number_format((int)$totalEngagement_current),
            "comparison" => (float)parent::point_two_digits($totalEngagement_current- $totalEngagement_previous !== 0 ? $this->overPeriodComparison($totalEngagement_current, $totalEngagement_previous)  : 0),
            "type" => $totalEngagement_current- $totalEngagement_previous > 0 ? "plus" :"minus",
        ];

        $data['positive'] = [
            "totalValue" => parent::custom_number_format((int)$total_share_current),
            "comparison" => (float)parent::point_two_digits($total_share_current - $total_share_previous !== 0 ? (($total_share_current - $total_share_previous) / $total_share_previous  * 100) : 0),
            "type" => $total_share_current- $total_share_previous > 0 ? "plus" :"minus",
        ];

        $data['neutral'] = [
            "totalValue" => parent::custom_number_format((int)$total_comment_current),
            "comparison" => (float)parent::point_two_digits($total_comment_current - $total_comment_previous !== 0 ? (($total_comment_current - $total_comment_previous) / $total_comment_previous * 100) : 0),
            "type" => $total_comment_current- $total_comment_previous > 0 ? "plus" :"minus",
        ];

        $data['negative'] = [
            "totalValue" => parent::custom_number_format((int)$total_reactions_current),
            "comparison" => (float)parent::point_two_digits($this->overPeriodComparison($total_reactions_current, $total_reactions_previous)),
            "type" => $total_reactions_current- $total_reactions_previous > 0 ? "plus" :"minus",
        ];


        return parent::handleRespond($data);

//        $data = [
//            "totalSentiment" => [
//                "totalValue" => "1.2M",
//                "comparison" => "-1%",
//                "type" => "minus"
//            ],
//            "positive" => [
//                "totalValue" => "800K",
//                "comparison" => "3%",
//                "type" => "plus"
//            ],
//            "neutral" => [
//                "totalValue" => "20K",
//                "comparison" => "-3%",
//                "type" => "minus"
//            ],
//            "negative" => [
//                "totalValue" => "1.45M",
//                "comparison" => "-1%",
//                "type" => "minus"
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function ComparisonByChannel(Request $request)
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
            "keyword_name" => "Previous",
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
            "keyword_name" => "Current",
            "data" => [
                15,
                45,
                65,
                23,
                53,
            ]
        ];

        $data['positive'] = [
            "-30%",
            "-30%",
            "-30%",
            "-30%",
            "-30%",
        ];

        $data['neutral'] = [
            "-23%",
            "-23%",
            "-23%",
            "-23%",
            "-23%",
        ];

        $data['negative'] = [
            "-56%",
            "-56%",
            "-56%",
            "-56%",
            "-56%",
        ];

        return parent::handleRespond($data);
    }

    public function ComparisonByEngagementType(Request $request)
    {


        $data = [
            "labels" => [
                "Share",
                "Comment",
                "Reaction"
            ],
            "value" => [
                [
                    "id" => 1,
                    "keyword_name" => "Previous",
                    "data" => [
                        47,
                        16,
                        30
                    ]
                ],
                [
                    "id" => 2,
                    "keyword_name" => "Current",
                    "data" => [
                        12,
                        16,
                        78
                    ]
                ]
            ],
            "positive" => [
                "-30%",
                "-30%",
                "-30%"
            ],
            "neutral" => [
                "-23%",
                "-23%",
                "-23%"
            ],
            "negative" => [
                "-56%",
                "-56%",
                "-56%"
            ]
        ];


        return parent::handleRespond($data);
    }

    public function SentimentScore(Request $request)
    {

        $data = [
            "senitment_score_data" => [
                [
                    "keyword_name" => "keyword 1",
                    "sentimentScore" => 3.2,
                    "previous_period" => 2.55,
                    "type" => "plus",
                    "hightlightColor" => "neutral"
                ],
                [
                    "keyword_name" => "keyword 2",
                    "sentimentScore" => 4.7,
                    "previous_period" => 4.6,
                    "type" => "plus",
                    "hightlightColor" => "positive"
                ],
                [
                    "keyword_name" => "keyword 3",
                    "sentimentScore" => 1.8,
                    "previous_period" => 2.75,
                    "type" => "minus",
                    "hightlightColor" => "negative"
                ],
                [
                    "keyword_name" => "keyword 4",
                    "sentimentScore" => 4.9,
                    "previous_period" => 2.6,
                    "type" => "minus",
                    "hightlightColor" => "positive"
                ],
                [
                    "keyword_name" => "keyword 5",
                    "sentimentScore" => 3.1,
                    "previous_period" => 4,
                    "type" => "minus",
                    "hightlightColor" => "neutral"
                ]
            ],
            "senitment_score_percentage" => [
                [
                    "keyword_id" => 1,
                    "keyword_name" => "keyword_name 1",
                    "campaign_id" => 1,
                    "campaign_name" => "campaign_name 1",
                    "organization_id" => 1,
                    "organizations_name" => "organizations_name 1",
                    "negative" => 10,
                    "neutral" => 60,
                    "positive" => 40
                ],
                [
                    "keyword_id" => 2,
                    "keyword_name" => "keyword_name 2",
                    "campaign_id" => 2,
                    "campaign_name" => "campaign_name 1",
                    "organization_id" => 2,
                    "organizations_name" => "organizations_name 1",
                    "negative" => 20,
                    "neutral" => 20,
                    "positive" => 60
                ],
                [
                    "keyword_id" => 3,
                    "keyword_name" => "keyword_name 3",
                    "campaign_id" => 3,
                    "campaign_name" => "campaign_name 1",
                    "organization_id" => 3,
                    "organizations_name" => "organizations_name 1",
                    "negative" => 60,
                    "neutral" => 30,
                    "positive" => 20
                ],
                [
                    "keyword_id" => 4,
                    "keyword_name" => "keyword_name 4",
                    "campaign_id" => 4,
                    "campaign_name" => "campaign_name 1",
                    "organization_id" => 4,
                    "organizations_name" => "organizations_name 1",
                    "negative" => 10,
                    "neutral" => 30,
                    "positive" => 60
                ],
                [
                    "keyword_id" => 5,
                    "keyword_name" => "keyword_name 5",
                    "campaign_id" => 5,
                    "campaign_name" => "campaign_name 1",
                    "organization_id" => 5,
                    "organizations_name" => "organizations_name 1",
                    "negative" => 10,
                    "neutral" => 60,
                    "positive" => 30
                ]
            ]
        ];

        return parent::handleRespond($data);
    }

    public function SentimentComparison(Request $request)
    {

        $data = [
            [
                "keyword_name" => "keyword 1",
                "total" => 500,
                "comparison" => [
                    "value" => "-500",
                    "percentage" => "-20",
                    "type" => "minus"
                ],
                "share" => [
                    "value" => "80",
                    "percentage" => "5",
                    "type" => "plus"
                ],
                "comment" => [
                    "value" => "-200",
                    "percentage" => "-10",
                    "type" => "minus"
                ],
                "reaction" => [
                    "value" => "-380",
                    "percentage" => "-2",
                    "type" => "minus"
                ]
            ],
            [
                "keyword_name" => "keyword 2",
                "total" => 1000,
                "comparison" => [
                    "value" => "-500",
                    "percentage" => "-20",
                    "type" => "minus"
                ],
                "share" => [
                    "value" => "80",
                    "percentage" => "5",
                    "type" => "plus"
                ],
                "comment" => [
                    "value" => "-200",
                    "percentage" => "-10",
                    "type" => "minus"
                ],
                "reaction" => [
                    "value" => "-380",
                    "percentage" => "-2",
                    "type" => "minus"
                ]
            ],
            [
                "keyword_name" => "keyword 3",
                "total" => 2000,
                "comparison" => [
                    "value" => "-500",
                    "percentage" => "-20",
                    "type" => "minus"
                ],
                "share" => [
                    "value" => "80",
                    "percentage" => "5",
                    "type" => "plus"
                ],
                "comment" => [
                    "value" => "-200",
                    "percentage" => "-10",
                    "type" => "minus"
                ],
                "reaction" => [
                    "value" => "-380",
                    "percentage" => "-2",
                    "type" => "minus"
                ]
            ],
            [
                "keyword_name" => "keyword 4",
                "total" => 300,
                "comparison" => [
                    "value" => "-500",
                    "percentage" => "-20",
                    "type" => "minus"
                ],
                "share" => [
                    "value" => "80",
                    "percentage" => "5",
                    "type" => "plus"
                ],
                "comment" => [
                    "value" => "-200",
                    "percentage" => "-10",
                    "type" => "minus"
                ],
                "reaction" => [
                    "value" => "-380",
                    "percentage" => "-2",
                    "type" => "minus"
                ]
            ],
            [
                "keyword_name" => "keyword 5",
                "total" => 3000,
                "comparison" => [
                    "value" => "-500",
                    "percentage" => "-20",
                    "type" => "minus"
                ],
                "share" => [
                    "value" => "80",
                    "percentage" => "5",
                    "type" => "plus"
                ],
                "comment" => [
                    "value" => "-200",
                    "percentage" => "-10",
                    "type" => "minus"
                ],
                "reaction" => [
                    "value" => "-380",
                    "percentage" => "-2",
                    "type" => "minus"
                ]
            ]

        ];


        return parent::handleRespond($data);
    }

    public function SummaryScoreAccount(Request $request)
    {

        $data[] = [
            "infulencer" => "User 1",
            "sentiment_score" => 3.9,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 1",
            "sentiment_score" => 3.9,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 2",
            "sentiment_score" => 2.7,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 3",
            "sentiment_score" => 2.9,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 4",
            "sentiment_score" => 2.1,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 5",
            "sentiment_score" => 2.4,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "infulencer" => "User 6",
            "sentiment_score" => 3.8,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        return parent::handleRespond($data);
    }

    public function SummaryScoreChannel(Request $request)
    {

        $data[] = [
            "channel" => "Facebook",
            "sentiment_score" => 3.9,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "channel" => "Twitter",
            "sentiment_score" => 2.7,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "channel" => "Youtube",
            "sentiment_score" => 2.9,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "channel" => "Instagram",
            "sentiment_score" => 2.1,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "channel" => "Pantip",
            "sentiment_score" => 2.4,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];


        return parent::handleRespond($data);
    }

    public function SummaryKeyword(Request $request)
    {

        $data[] = [
            "keyword" => "Keyword 1",
            "total_messages" => 212,
            "percentage" => 61,
            "positive" => 30,
            "neutral" => 45,
            "negative" => 5,
        ];

        $data[] = [
            "keyword" => "Keyword 2",
            "total_messages" => 75,
            "percentage" => 22,
            "positive" => 30,
            "neutral" => 70,
            "negative" => 10,
        ];

        $data[] = [
            "keyword" => "Keyword 3",
            "total_messages" => 29,
            "percentage" => 6,
            "positive" => 30,
            "neutral" => 40,
            "negative" => 30,
        ];

        $data[] = [
            "keyword" => "Keyword 4",
            "total_messages" => 20,
            "percentage" => 6,
            "positive" => 30,
            "neutral" => 40,
            "negative" => 30,
        ];

        $data[] = [
            "keyword" => "Keyword 5",
            "total_messages" => 10,
            "percentage" => 3,
            "positive" => 15,
            "neutral" => 50,
            "negative" => 35,
        ];


        return parent::handleRespond($data);
    }
}
