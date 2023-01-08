<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EngagementDashboardController extends Controller
{
    public function EngagementTrans(Request $request)
    {
        $start_date = $this->date_carbon($request->start_date) ?? null;
        $end_date = $this->date_carbon($request->end_date) ?? null;

        $period = $request->period;
        $start_date_previous = $this->get_previous_date($start_date, $period);
        $end_date_previous = $this->get_previous_date($end_date, $period);
        $data = [
            "engagement" => $this->engagement($request->campaign_id, $start_date, $end_date, $request->source),
            "prcentage_of_engagement_current" => $this->percentageOfEngagement($request->campaign_id, $start_date, $end_date, $request->keyword_id ?? null, $request->source ?? null),
            "prcentage_of_engagement_previous" => $this->percentageOfEngagement($request->campaign_id, $start_date_previous, $end_date_previous, $request->keyword_id ?? null, $request->source ?? null),
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByDay(Request $request)
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

    public function EngagementByTime(Request $request)
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

    public function EngagementByDevice(Request $request)
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

    public function EngagementByAccount(Request $request)
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

    public function EngagementChannel(Request $request)
    {
        $data['labels'] = [
            "Facebook",
            "Twitter",
            "Instagram",
            "Youtube",
            "Pantip",
        ];

        $data['data'][] = [
            "id" => 1,
            "name" => "Keyword 1",
            "data" => [
                80, 50, 30, 40, 100
            ]
        ];

        $data['data'][] = [
            "id" => 2,
            "name" => "Keyword 2",
            "data" => [
                20, 30, 40, 80, 20
            ]
        ];

        $data['data'][] = [
            "id" => 3,
            "name" => "Keyword 3",
            "data" => [
                44, 76, 78, 13, 43
            ]
        ];

        $data['data'][] = [
            "id" => 4,
            "name" => "Keyword 4",
            "data" => [
                20, 30, 48, 23, 53
            ]
        ];

        $data['data'][] = [
            "id" => 5,
            "name" => "Keyword 5",
            "data" => [
                45, 26, 38, 53, 13
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementType(Request $request)
    {
        $data['engagement'][] = [
            "keyword_id" => 1,
            "keyword_name" => "Share",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                "source_id" => 2,
                "source_name" => "twitter",
                "date_m" => "2022-12-17",
                "total_at_date" => 2,
            ],
            [
                "source_id" => 2,
                "source_name" => "twitter",
                "date_m" => "2022-12-18",
                "total_at_date" => 3,
            ],
            [
                "source_id" => 2,
                "source_name" => "twitter",
                "date_m" => "2022-12-19",
                "total_at_date" => 10,
            ],
            [
                "source_id" => 2,
                "source_name" => "twitter",
                "date_m" => "2022-12-20",
                "total_at_date" => 9,
            ],
            [
                "source_id" => 2,
                "source_name" => "twitter",
                "date_m" => "2022-12-21",
                "total_at_date" => 1,
            ]
        ];

        $data['engagement'][] = [
            "keyword_id" => 2,
            "keyword_name" => "Comment",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-17",
                    "total_at_date" => 2,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-18",
                    "total_at_date" => 3,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-19",
                    "total_at_date" => 10,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-20",
                    "total_at_date" => 9,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-21",
                    "total_at_date" => 1,
                ]
            ],

        ];

        $data['engagement'][] = [
            "keyword_id" => 3,
            "keyword_name" => "Reaction",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-17",
                    "total_at_date" => 2,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-18",
                    "total_at_date" => 3,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-19",
                    "total_at_date" => 10,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-20",
                    "total_at_date" => 9,
                ],
                [
                    "source_id" => 2,
                    "source_name" => "twitter",
                    "date_m" => "2022-12-21",
                    "total_at_date" => 1,
                ]
            ],
        ];

        $data['prcentage_of_engagement_current'][] = [
            "keyword_id" => 1,
            "keyword_name" => "Share",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "17/12/2022 - 23/12/2022",
                    "percentage" => 35,
                ]
            ]
        ];

        $data['prcentage_of_engagement_current'][] = [
            "keyword_id" => 2,
            "keyword_name" => "Comment",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "16/12/2022 - 22/12/2022",
                    "percentage" => 45,
                ]
            ]
        ];

        $data['prcentage_of_engagement_current'][] = [
            "keyword_id" => 3,
            "keyword_name" => "Reaction",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "16/12/2022 - 22/12/2022",
                    "percentage" => 20,
                ]
            ]
        ];

        $data['prcentage_of_engagement_previous'][] = [
            "keyword_id" => 1,
            "keyword_name" => "Share",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "17/12/2022 - 23/12/2022",
                    "percentage" => 30,
                ]
            ]
        ];

        $data['prcentage_of_engagement_previous'][] = [
            "keyword_id" => 2,
            "keyword_name" => "Comment",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "16/12/2022 - 22/12/2022",
                    "percentage" => 30,
                ]
            ]

        ];

        $data['prcentage_of_engagement_previous'][] = [
            "keyword_id" => 3,
            "keyword_name" => "Reaction",
            "campaign_id" => 2,
            "campaign_name" => "ข่าวบันเทิง",
            "value" => [
                [
                    "date" => "16/12/2022 - 22/12/2022",
                    "percentage" => 40,
                ]
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByDayKey(Request $request)
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
            "keyword_name" => "Share",
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
            "keyword_name" => "Comment",
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
            "keyword_name" => "Reaction",
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

        return parent::handleRespond($data);
    }

    public function EngagementByTimeKey(Request $request)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Share",
            "data" => [
                20,
                39,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Comment",
            "data" => [
                12,
                16,
                23,
                56,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Reaction",
            "data" => [
                45,
                65,
                23,
                53,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByDeviceKey(Request $request)
    {
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Share",
            "data" => [
                20,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Comment",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Reaction",
            "data" => [
                65,
                23,
                53,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByAccountKey(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Share",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Comment",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Reaction",
            "data" => [
                23,
                53,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementChannelKey(Request $request)
    {
        $data['labels'] = [
            "Facebook",
            "Twitter",
            "Instagram",
            "Youtube",
            "Pantip",
        ];

        $data['data'][] = [
            "id" => 1,
            "name" => "Share",
            "data" => [
                80, 50, 30, 40, 100
            ]
        ];

        $data['data'][] = [
            "id" => 2,
            "name" => "Comment",
            "data" => [
                20, 30, 40, 80, 20
            ]
        ];

        $data['data'][] = [
            "id" => 3,
            "name" => "Reaction",
            "data" => [
                44, 76, 78, 13, 43
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementComparison(Request $request)
    {
        $data['totalEngagement'] = [
            "totalValue" => "1.2M",
            "comparison" => "-1%",
            "type" => "minus",
        ];

        $data['share'] = [
            "totalValue" => "800k",
            "comparison" => "3%",
            "type" => "plus",
        ];

        $data['comment'] = [
            "totalValue" => "20k",
            "comparison" => "-3%",
            "type" => "minus",
        ];

        $data['reaction'] = [
            "totalValue" => "1.45M",
            "comparison" => "-1%",
            "type" => "minus",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementPeriodPlarform(Request $request)
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
                19, 38, 47, 16, 30
            ],
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Current",
            "data" => [
                15, 45, 65, 23, 53,
            ],
        ];

        $data['share'] = [
            "-30%", "-30%", "-30%", "-30%", "-30%",
        ];

        $data['comment'] = [
            "-23%", "-23%", "-23%", "-23%", "-23%",
        ];

        $data['reaction'] = [
            "-56%", "-56%", "-56%", "-56%", "-56%",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementPeriodSentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Previous",
            "data" => [
                47, 16, 30,
            ],
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Current",
            "data" => [
                12, 16, 78,
            ],
        ];

        $data['share'] = [
            "-30%", "-30%", "-30%", "-30%", "-30%",
        ];

        $data['comment'] = [
            "-23%", "-23%", "-23%", "-23%", "-23%",
        ];

        $data['reaction'] = [
            "-56%", "-56%", "-56%", "-56%", "-56%",
        ];

        return parent::handleRespond($data);
    }

    public function EngagementTypeComparison(Request $request)
    {

        $data = [
            [
                "keyword_name" => "keyword 1",
                "total" => [
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
                "total" => [
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
                "total" => [
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
                "total" => [
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
                "total" => [
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

    public function EngagementActionComparison(Request $request)
    {

        $data = [
            [
                "keyword_id" => 1,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 1,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 1,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "reaction" => 30,
                "comment" => 60
            ],
            [
                "keyword_id" => 2,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 2,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 2,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 3,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 3,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 3,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 4,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 4,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 4,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ],
            [
                "keyword_id" => 5,
                "keyword_name" => "keyword_name 1",
                "campaign_id" => 5,
                "campaign_name" => "campaign_name 1",
                "organization_id" => 5,
                "organizations_name" => "organizations_name 1",
                "share" => 10,
                "comment" => 30,
                "reaction" => 60
            ]
        ];

        return parent::handleRespond($data);
    }

    public function EngagementByInfulencer(Request $request)
    {

        $data = [
            [
                "infulencer" => "User 1",
                "total" => 39,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 2",
                "total" => 25,
                "share" => 25,
                "comment" => 0,
                "reaction" => 0,
                "period_over_preiod" => "+10",
                "period_over_period_percentage" => "+2"
            ],
            [
                "infulencer" => "User 3",
                "total" => 29,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 4",
                "total" => 95,
                "share" => 90,
                "comment" => 0,
                "reaction" => 5,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 5",
                "total" => 29,
                "share" => 29,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "-10",
                "period_over_period_percentage" => "-1"
            ],
            [
                "infulencer" => "User 6",
                "total" => 32,
                "share" => 32,
                "comment" => 0,
                "reaction" => 10,
                "period_over_preiod" => "+20",
                "period_over_period_percentage" => "+30"
            ]
        ];

        return parent::handleRespond($data);
    }

    private function percentageOfEngagement($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $table = 'total_engagement_of_source';
        $column = 'engagement';
        return parent::getDataByCondition($table,$campaign_id, $start_date, $end_date,  $keyword_id, $source_id, $column, 'percentage');
    }

    private function engagement($campaign_id, $start_date, $end_date, $source)
    {
        $table = 'total_engagement_of_source';
        $column = 'engagement';
        return parent::getDataByCondition($table, $campaign_id, $start_date, $end_date, null, $source, $column, 'engagement');

    }
}
