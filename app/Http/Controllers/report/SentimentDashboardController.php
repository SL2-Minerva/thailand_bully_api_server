<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SentimentDashboardController extends Controller
{
    public function DailySeniment(Request $request)
    {
            $data = [];
//        $data = [
//            "sentiment" => [
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "positive",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-17",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-18",
//                            "total_at_date" => 3
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-19",
//                            "total_at_date" => 10
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-20",
//                            "total_at_date" => 9
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-21",
//                            "total_at_date" => 1
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 9,
//                    "keyword_name" => "neutral",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-17",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-18",
//                            "total_at_date" => 1
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-19",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-20",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-21",
//                            "total_at_date" => 1
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-22",
//                            "total_at_date" => 1
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 9,
//                    "keyword_name" => "negative",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-17",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-18",
//                            "total_at_date" => 1
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-19",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-20",
//                            "total_at_date" => 2
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-21",
//                            "total_at_date" => 1
//                        ],
//                        [
//                            "source_id" => 2,
//                            "source_name" => "twitter",
//                            "date_m" => "2022-12-22",
//                            "total_at_date" => 1
//                        ]
//                    ]
//                ]
//            ],
//            "percentage_of_sentitment_current" => [
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "positive",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "17/12/2022 - 23/12/2022",
//                            "percentage" => "73.53"
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 9,
//                    "keyword_name" => "neutral",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "17/12/2022 - 23/12/2022",
//                            "percentage" => "26.47"
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "negative",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "17/12/2022 - 23/12/2022",
//                            "percentage" => "73.53"
//                        ]
//                    ]
//                ]
//            ],
//            "percentage_of_sentitment_previous" => [
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "บันเทิง",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "16/12/2022 - 22/12/2022",
//                            "percentage" => "65.85"
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 9,
//                    "keyword_name" => "แต่งงาน",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "16/12/2022 - 22/12/2022",
//                            "percentage" => "34.15"
//                        ]
//                    ]
//                ],
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "negative",
//                    "campaign_id" => 2,
//                    "campaign_name" => "ข่าวบันเทิง",
//                    "organization_id" => 1,
//                    "organizations_name" => "test",
//                    "value" => [
//                        [
//                            "date" => "16/12/2022 - 22/12/2022",
//                            "percentage" => "73.53"
//                        ]
//                    ]
//                ]
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentByDay(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Mon",
//            "Tue",
//            "Wed",
//            "Thu",
//            "Fri",
//            "Sat",
//            "Sun"
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                20,
//                39,
//                19,
//                38,
//                47,
//                16,
//                30
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                23,
//                56,
//                32,
//                15,
//                78,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                15,
//                67,
//                23,
//                45,
//                65,
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentByTime(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Before 6 AM",
//            "6 AM-12 PM",
//            "12 PM-6 PM",
//            "After 6 PM"
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                20,
//                39,
//                19,
//                38,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                23,
//                56,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                45,
//                65,
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentByDevice(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Andriod",
//            "Iphone",
//            "Web App",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                20,
//                19,
//                38,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                23,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                65,
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentByAccount(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Infulencer",
//            "Follower",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                19,
//                38,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                16,
//                23,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Negative",
//            "data" => [
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentByChannel(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Facebook",
//            "Twitter",
//            "Instagram",
//            "Youtube",
//            "Pantip",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                45,
//                23,
//                56,
//                67,
//                21,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                19,
//                38,
//                47,
//                16,
//                30,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                12,
//                16,
//                32,
//                15,
//                78,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentBullyLevel(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "Level 0",
//            "Level 1",
//            "Level 2",
//            "Level 3",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                19,
//                38,
//                47,
//                16,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                32,
//                78,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                15,
//                45,
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentBullyType(Request $request)
    {
        $data = [];
//        $data['labels'] = [
//            "No Bully",
//            "Gossip",
//            "Harassment",
//            "Exclusion",
//            "Hate Speech",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Negative",
//            "data" => [
//                19,
//                38,
//                47,
//                16,
//                30,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Neutral",
//            "data" => [
//                12,
//                16,
//                32,
//                15,
//                78,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 3,
//            "keyword_name" => "Positive",
//            "data" => [
//                15,
//                45,
//                65,
//                23,
//                53,
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function PeriodOverPeriod(Request $request)
    {
        $data = [];
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
        $data = [];
//        $data['labels'] = [
//            "Facebook",
//            "Twitter",
//            "Instagram",
//            "Youtube",
//            "Pantip",
//        ];
//
//        $data['value'][] = [
//            "id" => 1,
//            "keyword_name" => "Previous",
//            "data" => [
//                19,
//                38,
//                47,
//                16,
//                30,
//            ]
//        ];
//
//        $data['value'][] = [
//            "id" => 2,
//            "keyword_name" => "Current",
//            "data" => [
//                15,
//                45,
//                65,
//                23,
//                53,
//            ]
//        ];
//
//        $data['positive'] = [
//            "-30%",
//            "-30%",
//            "-30%",
//            "-30%",
//            "-30%",
//        ];
//
//        $data['neutral'] = [
//            "-23%",
//            "-23%",
//            "-23%",
//            "-23%",
//            "-23%",
//        ];
//
//        $data['negative'] = [
//            "-56%",
//            "-56%",
//            "-56%",
//            "-56%",
//            "-56%",
//        ];

        return parent::handleRespond($data);
    }

    public function ComparisonByEngagementType(Request $request)
    {

        $data = [];
//        $data = [
//            "labels" => [
//                "Share",
//                "Comment",
//                "Reaction"
//            ],
//            "value" => [
//                [
//                    "id" => 1,
//                    "keyword_name" => "Previous",
//                    "data" => [
//                        47,
//                        16,
//                        30
//                    ]
//                ],
//                [
//                    "id" => 2,
//                    "keyword_name" => "Current",
//                    "data" => [
//                        12,
//                        16,
//                        78
//                    ]
//                ]
//            ],
//            "positive" => [
//                "-30%",
//                "-30%",
//                "-30%"
//            ],
//            "neutral" => [
//                "-23%",
//                "-23%",
//                "-23%"
//            ],
//            "negative" => [
//                "-56%",
//                "-56%",
//                "-56%"
//            ]
//        ];


        return parent::handleRespond($data);
    }

    public function SentimentScore(Request $request)
    {
        $data = [];
//        $data = [
//            "senitment_score_data" => [
//                [
//                    "keyword_name" => "keyword 1",
//                    "sentimentScore" => 3.2,
//                    "previous_period" => 2.55,
//                    "type" => "plus",
//                    "hightlightColor" => "neutral"
//                ],
//                [
//                    "keyword_name" => "keyword 2",
//                    "sentimentScore" => 4.7,
//                    "previous_period" => 4.6,
//                    "type" => "plus",
//                    "hightlightColor" => "positive"
//                ],
//                [
//                    "keyword_name" => "keyword 3",
//                    "sentimentScore" => 1.8,
//                    "previous_period" => 2.75,
//                    "type" => "minus",
//                    "hightlightColor" => "negative"
//                ],
//                [
//                    "keyword_name" => "keyword 4",
//                    "sentimentScore" => 4.9,
//                    "previous_period" => 2.6,
//                    "type" => "minus",
//                    "hightlightColor" => "positive"
//                ],
//                [
//                    "keyword_name" => "keyword 5",
//                    "sentimentScore" => 3.1,
//                    "previous_period" => 4,
//                    "type" => "minus",
//                    "hightlightColor" => "neutral"
//                ]
//            ],
//            "senitment_score_percentage" => [
//                [
//                    "keyword_id" => 1,
//                    "keyword_name" => "keyword_name 1",
//                    "campaign_id" => 1,
//                    "campaign_name" => "campaign_name 1",
//                    "organization_id" => 1,
//                    "organizations_name" => "organizations_name 1",
//                    "negative" => 10,
//                    "neutral" => 60,
//                    "positive" => 40
//                ],
//                [
//                    "keyword_id" => 2,
//                    "keyword_name" => "keyword_name 2",
//                    "campaign_id" => 2,
//                    "campaign_name" => "campaign_name 1",
//                    "organization_id" => 2,
//                    "organizations_name" => "organizations_name 1",
//                    "negative" => 20,
//                    "neutral" => 20,
//                    "positive" => 60
//                ],
//                [
//                    "keyword_id" => 3,
//                    "keyword_name" => "keyword_name 3",
//                    "campaign_id" => 3,
//                    "campaign_name" => "campaign_name 1",
//                    "organization_id" => 3,
//                    "organizations_name" => "organizations_name 1",
//                    "negative" => 60,
//                    "neutral" => 30,
//                    "positive" => 20
//                ],
//                [
//                    "keyword_id" => 4,
//                    "keyword_name" => "keyword_name 4",
//                    "campaign_id" => 4,
//                    "campaign_name" => "campaign_name 1",
//                    "organization_id" => 4,
//                    "organizations_name" => "organizations_name 1",
//                    "negative" => 10,
//                    "neutral" => 30,
//                    "positive" => 60
//                ],
//                [
//                    "keyword_id" => 5,
//                    "keyword_name" => "keyword_name 5",
//                    "campaign_id" => 5,
//                    "campaign_name" => "campaign_name 1",
//                    "organization_id" => 5,
//                    "organizations_name" => "organizations_name 1",
//                    "negative" => 10,
//                    "neutral" => 60,
//                    "positive" => 30
//                ]
//            ]
//        ];

        return parent::handleRespond($data);
    }

    public function SentimentComparison(Request $request)
    {
        $data = [];
//        $data = [
//            [
//                "keyword_name" => "keyword 1",
//                "total" => 500,
//                "comparison" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 2",
//                "total" => 1000,
//                "comparison" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 3",
//                "total" => 2000,
//                "comparison" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 4",
//                "total" => 300,
//                "comparison" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ],
//            [
//                "keyword_name" => "keyword 5",
//                "total" => 3000,
//                "comparison" => [
//                    "value" => "-500",
//                    "percentage" => "-20",
//                    "type" => "minus"
//                ],
//                "share" => [
//                    "value" => "80",
//                    "percentage" => "5",
//                    "type" => "plus"
//                ],
//                "comment" => [
//                    "value" => "-200",
//                    "percentage" => "-10",
//                    "type" => "minus"
//                ],
//                "reaction" => [
//                    "value" => "-380",
//                    "percentage" => "-2",
//                    "type" => "minus"
//                ]
//            ]
//
//        ];


        return parent::handleRespond($data);
    }

    public function SummaryScoreAccount(Request $request)
    {
        $data = [];
//        $data[] = [
//            "infulencer" => "User 1",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 1",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 2",
//            "sentiment_score" => 2.7,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 3",
//            "sentiment_score" => 2.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 4",
//            "sentiment_score" => 2.1,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 5",
//            "sentiment_score" => 2.4,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "infulencer" => "User 6",
//            "sentiment_score" => 3.8,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];

        return parent::handleRespond($data);
    }

    public function SummaryScoreChannel(Request $request)
    {
        $data = [];
//        $data[] = [
//            "channel" => "Facebook",
//            "sentiment_score" => 3.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "channel" => "Twitter",
//            "sentiment_score" => 2.7,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "channel" => "Youtube",
//            "sentiment_score" => 2.9,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "channel" => "Instagram",
//            "sentiment_score" => 2.1,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "channel" => "Pantip",
//            "sentiment_score" => 2.4,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];


        return parent::handleRespond($data);
    }

    public function SummaryKeyword(Request $request)
    {
        $data = [];
//        $data[] = [
//            "keyword" => "Keyword 1",
//            "total_messages" => 212,
//            "percentage" => 61,
//            "positive" => 30,
//            "neutral" => 45,
//            "negative" => 5,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 2",
//            "total_messages" => 75,
//            "percentage" => 22,
//            "positive" => 30,
//            "neutral" => 70,
//            "negative" => 10,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 3",
//            "total_messages" => 29,
//            "percentage" => 6,
//            "positive" => 30,
//            "neutral" => 40,
//            "negative" => 30,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 4",
//            "total_messages" => 20,
//            "percentage" => 6,
//            "positive" => 30,
//            "neutral" => 40,
//            "negative" => 30,
//        ];
//
//        $data[] = [
//            "keyword" => "Keyword 5",
//            "total_messages" => 10,
//            "percentage" => 3,
//            "positive" => 15,
//            "neutral" => 50,
//            "negative" => 35,
//        ];




        return parent::handleRespond($data);
    }
}
