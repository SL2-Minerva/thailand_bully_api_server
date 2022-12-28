<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ChannelDashboardController extends Controller
{
    public function PercentageOfChannel(Request $request)
    {
        $data['previous_period']['labels'] = [
            "Facebook",
            "Instagram",
            "Pantip",
            "Twitter",
            "Youtube",
        ];
        $data['previous_period']['data'] = [
            395, 285, 484, 128, 90,
        ];
        $data['previous_period']['total'] = 57392;

        $data['current_period']['labels'] = [
            "Facebook",
            "Instagram",
            "Pantip",
            "Twitter",
            "Youtube",
        ];
        $data['current_period']['data'] = [
            623, 384, 282, 238, 199,
        ];
        $data['current_period']['total'] = 38273;

        return parent::handleRespond($data);
    }

    public function DailyChannel(Request $request)
    {
        $data[] = [
            "name" =>  "Facebook",
            "data" => [
                44, 55, 41, 67, 22, 43, 21, 49,
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
            ]
        ];

        $data[] = [
            "name" =>  "Twitter",
            "data" => [
                13, 23, 20, 8, 13, 27, 33, 12,
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
            ]
        ];

        $data[] = [
            "name" =>  "Instagram",
            "data" => [
                11, 17, 15, 15, 21, 14, 15, 13,
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
            ]
        ];

        $data[] = [
            "name" =>  "Youtube",
            "data" => [
                44, 55, 41, 67, 22, 43, 21, 49,
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
            ]
        ];

        $data[] = [
            "name" =>  "Pantip",
            "data" => [
                30, 23, 20, 8, 13, 27, 33, 12,
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
            ]
        ];

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
        $data['facebook'] = [
            "comparison_value" => 40000,
            "percentage" => "10",
            "type" => "minus",
        ];

        $data['twitter'] = [
            "comparison_value" => 200,
            "percentage" => "20",
            "type" => "plus",
        ];

        $data['youtube'] = [
            "comparison_value" => 2000,
            "percentage" => "20",
            "type" => "minus",
        ];

        $data['instagram'] = [
            "comparison_value" => 200,
            "percentage" => "20",
            "type" => "plus",
        ];

        $data['pantip'] = [
            "comparison_value" => 100,
            "percentage" => "20",
            "type" => "plus",
        ];

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
