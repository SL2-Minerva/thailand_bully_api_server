<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BullyDashboardController extends Controller
{
    public function DailyBully(Request $request)
    {

        $data = [
            "bully_level" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 3
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 10
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 9
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 2,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-22",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-22",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 3
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 10
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 9
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ]
                    ]
                ]
            ],
            "percentage_of_bully_current" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ],
                [
                    "id" => 2,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "26.47"
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "33.53"
                        ]
                    ]
                ]
            ],
            "percentage_of_bully_previous" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "65.85"
                        ]
                    ]
                ],
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "34.15"
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "21.53"
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ]
            ]

        ];

        return parent::handleRespond($data);
    }

    public function BullyByDay(Request $request)
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
            "keyword_name" => "Level 0",
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
            "keyword_name" => "Level 1",
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
            "keyword_name" => "Level 2",
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
            "keyword_name" => "Level 3",
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

        return parent::handleRespond($data);
    }

    public function BullyByTime(Request $request)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                20,
                39,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                12,
                16,
                23,
                56,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                45,
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                29,
                35,
                63,
                33,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyByDevice(Request $request)
    {
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                20,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                10,
                33,
                46,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                15,
                35,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyByChannel(Request $request)
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
            "keyword_name" => "Level 0",
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
            "keyword_name" => "Level 1",
            "data" => [
                19,
                38,
                47,
                16,
                30,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                25,
                65,
                25,
                28,
                23,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "Level 0",
            "data" => [
                19,
                38,
                47,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Level 1",
            "data" => [
                12,
                16,
                32,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Level 2",
            "data" => [
                15,
                45,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Level 3",
            "data" => [
                23,
                17,
                34,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyPercentageDaily(Request $request)
    {
        $data = [
            "bully_level" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 3
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 10
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 9
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 2,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-22",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-22",
                            "total_at_date" => 1
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-17",
                            "total_at_date" => 2
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-18",
                            "total_at_date" => 3
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-19",
                            "total_at_date" => 10
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-20",
                            "total_at_date" => 9
                        ],
                        [
                            "source_id" => 2,
                            "source_name" => "twitter",
                            "date_m" => "2022-12-21",
                            "total_at_date" => 1
                        ]
                    ]
                ]
            ],
            "percentage_of_bully_current" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ],
                [
                    "id" => 2,
                    "bully_level" => "Level 1",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "26.47"
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "33.53"
                        ]
                    ]
                ]
            ],
            "percentage_of_bully_previous" => [
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "65.85"
                        ]
                    ]
                ],
                [
                    "id" => 1,
                    "bully_level" => "Level 0",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "34.15"
                        ]
                    ]
                ],
                [
                    "id" => 3,
                    "bully_level" => "Level 2",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "17/12/2022 - 23/12/2022",
                            "percentage" => "21.53"
                        ]
                    ]
                ],
                [
                    "id" => 4,
                    "bully_level" => "Level 3",
                    "campaign_id" => 2,
                    "campaign_name" => "ข่าวบันเทิง",
                    "organization_id" => 1,
                    "organizations_name" => "test",
                    "value" => [
                        [
                            "date" => "16/12/2022 - 22/12/2022",
                            "percentage" => "73.53"
                        ]
                    ]
                ]
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTypeByDay(Request $request)
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
            "keyword_name" => "No Bully",
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
            "keyword_name" => "Gossip",
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
            "keyword_name" => "Harassment",
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
            "keyword_name" => "Exclusion",
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
            "id" => 5,
            "keyword_name" => "Hate Speech",
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

        return parent::handleRespond($data);
    }

    public function BullyTypeByTime(Request $request)
    {
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                20,
                39,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                12,
                16,
                23,
                56,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Harassment",
            "data" => [
                45,
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                29,
                35,
                63,
                33,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                12,
                16,
                23,
                56,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTypeByDevice(Request $request)
    {
        $data['labels'] = [
            "Andriod",
            "Iphone",
            "Web App",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                20,
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Harassment",
            "data" => [
                65,
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                10,
                33,
                46,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTypeByAccount(Request $request)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                19,
                38,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                16,
                23,
            ]
        ];

        $data['value'][] = [

            "id" => 3,
            "keyword_name" => "Harassement",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                23,
                53,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                23,
                53,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTypeByChannel(Request $request)
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
            "keyword_name" => "No Bully",
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
            "keyword_name" => "Gossip",
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
            "keyword_name" => "Harassement",
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
            "keyword_name" => "Exclusion",
            "data" => [
                12,
                16,
                32,
                15,
                78,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                12,
                16,
                32,
                15,
                78,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTypeBySentiment(Request $request)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][] = [
            "id" => 1,
            "keyword_name" => "No Bully",
            "data" => [
                19,
                38,
                47,
            ]
        ];

        $data['value'][] = [
            "id" => 2,
            "keyword_name" => "Gossip",
            "data" => [
                12,
                16,
                32,
            ]
        ];

        $data['value'][] = [
            "id" => 3,
            "keyword_name" => "Harassment",
            "data" => [
                15,
                45,
                23,
            ]
        ];

        $data['value'][] = [
            "id" => 4,
            "keyword_name" => "Exclusion",
            "data" => [
                23,
                17,
                34,
            ]
        ];

        $data['value'][] = [
            "id" => 5,
            "keyword_name" => "Hate Speech",
            "data" => [
                12,
                16,
                23,
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyChartLevel(Request $request)
    {
        $data = [
            [
                "keyword_name" => "all",
                "number_of_massage" => "92"
            ],
            [
                "keyword_name" => "Level 0",
                "number_of_massage" => "23"
            ],
            [
                "keyword_name" => "Level 1",
                "number_of_massage" => "23"
            ],
            [
                "keyword_name" => "Level 2",
                "number_of_massage" => "60"
            ],
            [
                "keyword_name" => "Level 3",
                "number_of_massage" => "45"
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyLevelLevel(Request $request)
    {
        $data = [
            [
                "id" => 1,
                "keyword_name" => "all",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 0",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 1",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 2",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Level 3",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyChartType(Request $request)
    {
        $data = [
            [
                "keyword_name" => "all",
                "number_of_massage" => "92"
            ],
            [
                "keyword_name" => "No Bully",
                "number_of_massage" => "23"
            ],
            [
                "keyword_name" => "Gossip",
                "number_of_massage" => "23"
            ],
            [
                "keyword_name" => "Harassment",
                "number_of_massage" => "60"
            ],
            [
                "keyword_name" => "Exclusion",
                "number_of_massage" => "45"
            ],
            [
                "keyword_name" => "Hate Speech",
                "number_of_massage" => "39"
            ]
        ];

        return parent::handleRespond($data);
    }

    public function BullyTableType(Request $request)
    {
        $data = [
            [
                "id" => 1,
                "keyword_name" => "all",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "No Bully",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Gossip",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Harassment",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Exclusion",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ],
            [
                "id" => 1,
                "keyword_name" => "Hate Speech",
                "campaign_id" => 2,
                "campaign_name" => "ข่าวบันเทิง",
                "organization_id" => 1,
                "organizations_name" => "test",
                "value" => [
                    [
                        "channel" => "facebook",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "twitter",
                        "percentage" => "30"
                    ],
                    [
                        "channel" => "youtube",
                        "percentage" => "10"
                    ],
                    [
                        "channel" => "instagram",
                        "percentage" => "20"
                    ],
                    [
                        "channel" => "pantip",
                        "percentage" => "15"
                    ]
                ]
            ]
        ];

        return parent::handleRespond($data);
    }
}
