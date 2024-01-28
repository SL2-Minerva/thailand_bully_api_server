<?php

namespace App\Http\Controllers\report;

use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Sources;
use App\Models\Classification;
use App\Models\Keyword;

class BullyDashboardController extends Controller
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

    public function dailyBy()
    {
        $data = null;
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();

        $previous = $this->raw_message_classification_name($keywords, $this->start_date_previous, $this->end_date_previous, 3)->get();

        $prcentage_of_messages_current['prcentage_of_messages_current'] = $this->PercentageToCal($current, $classifications, $this->start_date, $this->end_date);
        $prcentage_of_messages_current['prcentage_of_messages_previous'] = $this->PercentageToCal($previous, $classifications, $this->start_date_previous, $this->end_date_previous);


        $data['percentage_bully'] = $prcentage_of_messages_current;
        $data['daily_bully'] = $this->DailyBullyGroup($current, $keywords, $classifications, $sources);
        return parent::handleRespond($data);
    }

    public function bullyBy()
    {

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();

        $currentSentiment = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 1)->get();

        $data['bully_by_day'] = $this->BullyByDayGroup($current, $classifications);
        $data['bully_by_time'] = $this->BullyByTimeGroup($current, $classifications);
        $data['bully_by_device'] = $this->BullyByDeviceGroup($current, $classifications);
        $data['bully_by_account'] = $this->BullyByAccountGroup($current, $classifications);
        $data['bully_by_channel'] = $this->BullyByChannelGroup($current, $classifications, $sources);
        $data['bully_by_sentiment'] = $this->BullyBySentimentGroup($current, $currentSentiment, $classifications);

        return parent::handleRespond($data);
    }

    private function PercentageToCal($items, $classifications, $start_date, $end_date)
    {
        $data = [];

        $message_total = 0;

        foreach ($items as $item) {
            $message_total += 1;
            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['value']['total'] += 1;
            } else {
                $data[$item->classification_id]['bully_level'] = $this->matchClassificationName($classifications, $item->classification_id);
                /*$data[$item->classification_id]['campaign_id'] = $item->campaign_id;
                $data[$item->classification_id]['campaign_name'] = $item->campaign_name;*/
                $data[$item->classification_id]['value']['total'] = 1;
                $data[$item->classification_id]['value']['date'] = Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y');
            }
        }

        foreach ($data as $key => $value) {
            $data[$key]['value']['percentage'] = $this->point_two_digits(($data[$key]['value']['total'] / $message_total) * 100);
            $data[$key]['value']['total'] = self::point_two_digits($message_total, 0);
        }

        if ($data) {
            $data = array_values($data);
        }

        return $data;
    }

    private function DailyBullyGroup($items, $keywords, $classifications, $sources)
    {
        $data = null;
        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->classification_id])) {

                if (isset($data[$item->classification_id]['value'][$date_format])) {
                    $data[$item->classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$item->classification_id] = [
                    "classification_id" => $item->classification_id,
                    "bully_level" => $this->matchClassificationName($classifications, $item->classification_id),
                    "source_id" => $item->source_id,
                    "source_name" => $this->matchSourceName($sources, $item->source_id),/*
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                ];
                $data[$item->classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                    'date_m' => $item->date_m,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    private function BullyByDayGroup($items, $classifications)
    {
        $data = null;

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        foreach ($items as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByTimeGroup($items, $classifications)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

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
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }
        }
        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByDeviceGroup($items, $classifications)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($items as $item) {
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
                if (isset($data['value'][$item->classification_id])) {
                    $data['value'][$item->classification_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->classification_id] = [
                        'id' => $item->classification_id,
                        'classification_id' => $item->classification_id,
                        'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->classification_id]['data'][$index_label] += 1;
                }
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyByAccountGroup($items, $classifications)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        foreach ($items as $infulencer) {
            if ($infulencer->reference_message_id == '') {
                if (!isset($data['value'][$infulencer->classification_id]['data'][0])) {
                    $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                    $data['value'][$infulencer->classification_id]['classification_id'] = $infulencer->classification_id;
                    $data['value'][$infulencer->classification_id]['keyword_name'] = $this->matchClassificationName($classifications, $infulencer->classification_id);
                    $data['value'][$infulencer->classification_id]['data'][0] = 0;


                }
                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                }
            }
        }


        foreach ($items as $follower) {
            if ($follower->reference_message_id != '') {
                if (isset($data['value'][$follower->classification_id]['data'][1])) {
                    if ($follower->reference_message_id) {
                        $data['value'][$follower->classification_id]['data'][1] += 1;
                    }
                } else {
                    $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                    $data['value'][$follower->classification_id]['keyword_name'] = $this->matchClassificationName($classifications, $follower->classification_id);
                    $data['value'][$follower->classification_id]['data'][1] = 0;

                    if ($follower->reference_message_id) {
                        $data['value'][$follower->classification_id]['data'][0] += 1;
                    }
                }
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return $data;
    }

    private function BullyByChannelGroup($items, $classifications, $sources)
    {
        $source_ids = parent::listSource();
        $data['labels'] = [];

        foreach ($source_ids as $source_id) {
            $data['labels'] = $source_id;
        }

        $data['value'] = null;

        foreach ($items as $item) {
            $source_name = $this->matchSourceName($sources, $item->source_id);
            $index_label = array_search($source_name, $data['labels']);

            if (!isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];

            }
            $data['value'][$item->classification_id]['data'][$index_label] += 1;

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyBySentimentGroup($current, $currentSentiment, $classifications)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $data['value'][10] = ["id" => 10, "classification_id" => 10, "keyword_name" => "Level 0", "data" => [0, 0, 0]];
        $data['value'][11] = ["id" => 11, "classification_id" => 11, "keyword_name" => "Level 1", "data" => [0, 0, 0]];
        $data['value'][12] = ["id" => 12, "classification_id" => 12, "keyword_name" => "Level 2", "data" => [0, 0, 0]];
        $data['value'][13] = ["id" => 13, "classification_id" => 13, "keyword_name" => "Level 3", "data" => [0, 0, 0]];


        $anylsys = [];
        foreach ($current as $item) {

            $anylsys[$item->message_id][$item->classification_type_id] = $this->matchClassificationName($classifications, $item->classification_id);
        }

        foreach ($currentSentiment as $item) {

            $anylsys[$item->message_id][$item->classification_type_id] = $this->matchClassificationName($classifications, $item->classification_id);
        }


        foreach ($anylsys as $anylsy) {

            $index_data = 10;

            if ($anylsy[3] === 'Level 1') {
                $index_data = 11;
            }

            if ($anylsy[3] === 'Level 2') {
                $index_data = 12;
            }

            if ($anylsy[3] === 'Level 3') {
                $index_data = 13;
            }


            $index_label = array_search($anylsy[1], $data['labels']);
            $data['value'][$index_data]['data'][$index_label] += 1;

        }

        if ($data) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function dailyTypeBy()
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();

        $current = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 2)->get();
        $currentSentiment = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 1)->get();

        //$previous = $this->raw_message_classification_name($keywords, $this->start_date_previous, $this->end_date_previous, 3)->get();

        /*$prcentage_of_messages_current['prcentage_of_messages_current'] = $this->PercentageToCal($current, $classifications, $this->start_date, $this->end_date);
        $prcentage_of_messages_current['prcentage_of_messages_previous'] = $this->PercentageToCal($previous, $classifications, $this->start_date_previous, $this->end_date_previous);


        $data['bully_type_percentage'] = $prcentage_of_messages_current;*/
        $data['bully_type_daily'] = $this->BullyTypeDailyGroup($current, $classifications, $sources, $keywords);
        $data['bully_type_by_day'] = $this->BullyTypeByDayGroup($current, $classifications);
        $data['bully_type_by_time'] = $this->BullyTypeByTimeGroup($current, $classifications);
        $data['bully_type_by_device'] = $this->BullyTypeByDeviceGroup($current, $classifications);
        $data['bully_type_by_account'] = $this->BullyTypeByAccountGroup($current, $classifications);
        $data['bully_type_by_channel'] = $this->BullyTypeByChannelGroup($current, $classifications, $sources);
        $data['bully_type_by_sentiment'] = $this->BullyTypeBySentimentGroup($current, $currentSentiment, $classifications);
        return parent::handleRespond($data);
    }

    private function BullyTypeDailyGroup($items, $classifications, $sources, $keywords)
    {
        $data = null;

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->classification_id])) {

                if (isset($data[$item->classification_id]['value'][$date_format])) {
                    $data[$item->classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $this->matchKeywordName($keywords, $item->keyword_id),
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$item->classification_id] = [
                    "classification_id" => $item->classification_id,
                    "bully_level" => $this->matchClassificationName($classifications, $item->classification_id),
                    "source_id" => $item->source_id,
                    "source_name" => $this->matchSourceName($sources, $item->source_id)/*,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                ];
                $data[$item->classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $this->matchKeywordName($keywords, $item->keyword_id),
                    'date_m' => $item->date_m,
                    'total_at_date' => 1
                ];
            }
        }

        if ($data) {

            foreach ($data as $key => $item) {
                if ($item) {
                    $data[$key]['value'] = array_values($item['value']);
                }
            }
        }

        if ($data) {
            return array_values($data);
        }

        return $data;
    }

    private function BullyTypeByDayGroup($items, $classification)
    {
        $data = null;

        $data['labels'] = [
            "Mon",
            "Tue",
            "Wed",
            "Thu",
            "Fri",
            "Sat",
            "Sun"
        ];

        foreach ($items as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classification, $item->classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByTimeGroup($items, $classification)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

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
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classification, $item->classification_id),
                    'data' => [0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByDeviceGroup($items, $classification)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $data['value'] = null;

        foreach ($items as $item) {
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
                if (isset($data['value'][$item->classification_id])) {
                    $data['value'][$item->classification_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->classification_id] = [
                        'id' => $item->classification_id,
                        'classification_id' => $item->classification_id,
                        'keyword_name' => $this->matchClassificationName($classification, $item->classification_id),
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->classification_id]['data'][$index_label] += 1;
                }
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeByAccountGroup($items, $classifications)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];


        foreach ($items as $infulencer) {
            $classificationName = $this->matchClassificationName($classifications, $infulencer->classification_id);
            if ($infulencer->reference_message_id == '') {
                if (!isset($data['value'][$infulencer->classification_id]['data'][0])) {
                    $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                    $data['value'][$infulencer->classification_id]['classification_id'] = $infulencer->classification_id;
                    $data['value'][$infulencer->classification_id]['keyword_name'] = $classificationName;
                    $data['value'][$infulencer->classification_id]['data'][0] = 0;

                }
                $data['value'][$infulencer->classification_id]['data'][0] += 1;
            } else {
                if (!isset($data['value'][$infulencer->classification_id]['data'][1])) {
                    $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                    $data['value'][$infulencer->classification_id]['keyword_name'] = $classificationName;
                    $data['value'][$infulencer->classification_id]['data'][1] = 0;
                }
                $data['value'][$infulencer->classification_id]['data'][1] += 1;
            }
        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return $data;
    }

    private function BullyTypeByChannelGroup($items, $classifications, $sources)
    {
        $data['labels'] = [];

        foreach ($sources as $source_id) {
            $data['labels'][] = $source_id->name;
        }

        $data['value'] = null;

        foreach ($items as $item) {
            $sourceName = $this->matchSourceName($sources, $item->source_id);
            $index_label = array_search($sourceName, $data['labels']);

            if (!isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];

            }
            $data['value'][$item->classification_id]['data'][$index_label] += 1;

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeBySentimentGroup($current, $currentSentiment, $classifications)
    {
        $data['labels'] = ["Positive", "Neutral", "Negative"];
        $bully_types = Classification::where('classification_type_id', 2)->get();

        $data['value'] = [];
        foreach ($bully_types as $bully_type) {
            $data['value'][$bully_type->id] = [
                'id' => $bully_type->id,
                'classification_id' => $bully_type->id,
                'keyword_name' => $bully_type->name,
                'data' => [0, 0, 0]
            ];
        }

        $anylsys = [];

        foreach ($current as $item) {
            $anylsys[$item->message_id][$item->classification_type_id] = $this->matchClassificationName($classifications, $item->classification_id);
        }

        foreach ($currentSentiment as $item) {
            $anylsys[$item->message_id][$item->classification_type_id] = $this->matchClassificationName($classifications, $item->classification_id);
        }

        foreach ($anylsys as $anylsy) {
            foreach ($bully_types as $bully_type) {
                if ($anylsy[2] === $bully_type->name) {
                    $index_label = array_search($anylsy[1], $data['labels']);
                    $data['value'][$bully_type->id]['data'][$index_label]++;
                }
            }
        }

        $data['value'] = array_values($data['value']);

        return $data;
    }


    public function bullyTypeBy()
    {
        $data = null;

        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $classifications = self::getClassificationMaster();
        $sources = self::getAllSource();
        $currentType = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 2)->get();

        $currentLevel = $this->raw_message_classification_name($keywords, $this->start_date, $this->end_date, 3)->get();


        $data['bully_type_by_level'] = $this->BullyChartLevelGroup($currentLevel, $classifications);
        $data['bully_chart_type'] = $this->BullyChartTypeGroup($currentType, $classifications);
        $data['bully_chart_level'] = $this->BullyLevelLevelGroup($currentLevel, $classifications, $sources);
        $data['bully_table_type'] = $this->BullyTableTypeGroup($currentType, $classifications, $sources);

        return parent::handleRespond($data);
    }

    private function BullyChartLevelGroup($items, $classifications)
    {
        $all = 0;
        $data = [];
        foreach ($items as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'classification_id' => $item->classification_id,
                    'data' => 0
                ];
            }
        }

        if (!$data) {
            return $data;
        }

        return array_values($data);
    }

    private function BullyChartTypeGroup($items, $classifications)
    {

        $all = 0;

        $data = [];
        foreach ($items as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $this->matchClassificationName($classifications, $item->classification_id),
                    'classification_id' => $item->classification_id,
                    'data' => 0
                ];
            }
        }

        if (!$data) {
            return $data;
        }

        return array_values($data);
    }

    private function BullyLevelLevelGroup($items, $classifications, $sourceArr)
    {

        $soures = parent::listSource();
        $anylsys = [];
        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
        }


        foreach ($items as $item) {
            $sourceName = $this->matchSourceName($sourceArr, $item->source_id);
            $classification_name = $this->matchClassificationName($classifications, $item->classification_id);
            if (isset($anylsys['all'])) {
                /*$anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;*/
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$classification_name]['total'] += 1;
            }


            if (!isset($anylsys[$classification_name])) {
                $anylsys[$classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $classification_name,
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['id'] = $i;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

            }
            $anylsys[$classification_name]['value'][$sourceName]['total'] += 1;
            $anylsys[$classification_name]['total'] += 1;
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                // 'campaign_id' => $item['campaign_id'],
                // 'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $total ? ($value['total'] / $total) * 100 : 0;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }

        return $data;
    }

    private function BullyTableTypeGroup($items, $classifications, $sourceArr)
    {
        $soures = parent::listSource();
        $anylsys = [];
        $anylsys['all'] = [
            'id' => -1,
            'keyword_name' => "all",
            'total' => 0
        ];

        for ($i = 0; $i < count($soures['labels']); $i++) {
            $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
            $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
            $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
            $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
        }


        foreach ($items as $item) {
            $sourceName = $this->matchSourceName($sourceArr, $item->source_id);
            if (isset($anylsys['all'])) {
                /*$anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;*/
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$sourceName]['total'] += 1;
            }
            $classification_name = $this->matchClassificationName($classifications, $item->classification_id);

            if (!isset($anylsys[$classification_name])) {
                $anylsys[$classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $classification_name,
                    /*"campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,*/
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['id'] = $i;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

            }
            $anylsys[$classification_name]['value'][$sourceName]['total'] += 1;
            $anylsys[$classification_name]['total'] += 1;
        }

        $data = [];

        foreach ($anylsys as $key => $item) {
            $total = $item['total'];
            $data[$key] = [
                'id' => $item['id'],
                'keyword_name' => $item['keyword_name'],
                // 'campaign_id' => $item['campaign_id'],
                // 'campaign_name' => $item['campaign_name'],
                'value' => $item['value'],
                'total' => $item['total'],
            ];

            foreach ($item['value'] as $index => $value) {
                $data[$key]['value'][$index]['percentage'] = $total ? ($value['total'] / $total) * 100 : 0;
            }

        }

        if ($data) {
            $data = array_values($data);
        }

        foreach ($data as $key => $item) {
            $data[$key]['value'] = array_values($item['value']);
        }

        return $data;
    }

    private function raw_message_classification($campaign_id, $start_date, $end_date, $classification_type_id)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.keyword_id as keyword_id',
                'messages.message_datetime as date_m',
                'messages.author as author',
                'messages.source_id as source_id',
                'messages.full_message as full_message',
                'messages.message_type',
                'messages.device as device',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'keywords.name as keyword_name',
                'classifications.classification_type_id',
                'message_results.classification_id',
                'classifications.name as classification_name',
                'classifications.color as classification_color',
                'sources.name as source_name',
                'messages.created_at as created_at'
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->whereIn('classifications.classification_type_id', $classification_type_id);

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }

        return $data;
    }

    private function raw_message_classification_name($keywords, $start_date, $end_date, $classification_type)
    {

        $keywordIds = $keywords->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.message_id as message_id',
                'messages.reference_message_id as reference_message_id',
                'messages.keyword_id as keyword_id',
                'messages.message_datetime as date_m',
                'messages.author as author',
                'messages.source_id as source_id',
                /*'messages.full_message as full_message',*/
                'messages.message_type',
                'messages.device as device',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'message_results.classification_id',
                'message_results.classification_type_id'
            ])
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->where('message_results.classification_type_id', $classification_type)
            ->orderBy('message_results.classification_id', 'asc');

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
