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
            $this->keyword_id = explode(',' , $fillter_keywords);
        }

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();


            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

        if ($request->period === 'customrange') {
            $this->start_date_previous =  $this->date_carbon($request->start_date_period);
            $this->end_date_previous =  $this->date_carbon($request->end_date_period);
        }

    }

    public function dailyBy()
    {
        $data = null;
        $data['percentage_bully'] = $this->PercentageBullyGroup();
        $data['daily_bully'] = $this->DailyBullyGroup();

        return parent::handleRespond($data);
    }

    public function bullyBy()
    {
        $data = null;
        $raw = $this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);

        $data['bully_by_day'] = $this->BullyByDayGroup($raw);
        $data['bully_by_time'] = $this->BullyByTimeGroup($raw);
        $data['bully_by_device'] = $this->BullyByDeviceGroup($raw);
        $data['bully_by_account'] = $this->BullyByAccountGroup($raw);
        $data['bully_by_channel'] = $this->BullyByChannelGroup($raw);
        $data['bully_by_sentiment'] = $this->BullyBySentimentGroup($this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['Positive', 'Negative', 'Neutral', 'Level 0', 'Level 1', 'Level 2', 'Level 3']));

        return parent::handleRespond($data);
    }

    // public function PercentageBully(Request $request)
    // {
    //     $data = null;
    //     $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);
    //     $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);

    //     return parent::handleRespond($data);
    // }

    private function PercentageBullyGroup()
    {
        $data = null;
        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);

        return $data;
    }

    private function PercentageToCal($start_date, $end_date, $classification_id)
    {
        $data = [];

        $raw = $this->raw_message_classification_name($this->campaign_id, $start_date, $end_date, $classification_id);

        $items = $raw->get();
        $message_total = 0;

        foreach ($items as $item) {
            $message_total += 1;
            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['value']['total'] += 1;
            } else {
                $data[$item->classification_id]['bully_level'] = $item->classification_name;
                $data[$item->classification_id]['campaign_id'] = $item->campaign_id;
                $data[$item->classification_id]['campaign_name'] = $item->campaign_name;
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

    private function DailyBullyGroup()
    {
        $data = null;
        $raw = $this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);

        $items = $raw->get();

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->classification_id])) {

                if (isset($data[$item->classification_id]['value'][$date_format])) {
                    $data[$item->classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$item->classification_id] = [
                    "classification_id" =>  $item->classification_id,
                    "bully_level" => $item->classification_name,
                    "source_id" =>  $item->source_id,
                    "source_name" => $item->source_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];
                $data[$item->classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
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

    private function BullyByDayGroup($raw)
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

        $items = $raw->get();

        foreach ($items as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
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

    private function BullyByTimeGroup($raw)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

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
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
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

    private function BullyByDeviceGroup($raw)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $items = $raw->get();

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
                        'keyword_name' => $item->classification_name,
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

    private function BullyByAccountGroup($raw)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table = 'message_result_full_data';

        $infulencer_root = $raw;
        $infulencers = $infulencer_root->get();

        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                }
            } else {
                $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['classification_id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->classification_name;
                $data['value'][$infulencer->classification_id]['data'][0] = 0;

                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                }


            }

        }

        $follower_raw = $raw;
        $followers = $follower_raw->get();

        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->classification_id]['data'][1])) {
                if ($follower->reference_message_id) {
                    $data['value'][$follower->classification_id]['data'][1] += 1;
                }
            } else {
                $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                $data['value'][$follower->classification_id]['data'][1] = 0;

                if ($follower->reference_message_id) {
                    $data['value'][$follower->classification_id]['data'][0] += 1;
                }
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return $data;
    }

    private function BullyByChannelGroup($raw)
    {
        $source_ids = parent::listSource();
        $data['labels'] = [];

        foreach ($source_ids as $source_id) {
            $data['labels'] = $source_id;
        }

        $items = $raw->get();
        $data['value'] = null;

        foreach ($items as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyBySentimentGroup($raw)
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

        $items = $raw->get();
        $anylsys = [];
        foreach ($items as $item) {

            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
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
        $raw = $this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence']);

        $data['bully_type_percentage'] = $this->BullyTypePercentageDailyGroup();
        $data['bully_type_daily'] = $this->BullyTypeDailyGroup($raw);
        $data['bully_type_by_day'] = $this->BullyTypeByDayGroup($raw);
        $data['bully_type_by_time'] = $this->BullyTypeByTimeGroup($raw);
        $data['bully_type_by_device'] = $this->BullyTypeByDeviceGroup($raw);
        $data['bully_type_by_account'] = $this->BullyTypeByAccountGroup($raw);
        $data['bully_type_by_channel'] = $this->BullyTypeByChannelGroup($raw);
        $data['bully_type_by_sentiment'] = $this->BullyTypeBySentimentGroup($this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['Positive', 'Negative', 'Neutral', 'NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence']));
        return parent::handleRespond($data);
    }

    private function BullyTypePercentageDailyGroup()
    {
        $data = null;

        $data['prcentage_of_messages_current'] = $this->PercentageToCal($this->start_date, $this->end_date, ['NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence']);
        $data['prcentage_of_messages_previous'] = $this->PercentageToCal($this->start_date_previous, $this->end_date_previous, ['NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence']);

        return $data;
    }

    private function BullyTypeDailyGroup($raw)
    {
        $data = null;
        $items = $raw->get();

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->classification_id])) {

                if (isset($data[$item->classification_id]['value'][$date_format])) {
                    $data[$item->classification_id]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->classification_id]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$item->classification_id] = [
                    "classification_id" =>  $item->classification_id,
                    "bully_level" => $item->classification_name,
                    "source_id" =>  $item->source_id,
                    "source_name" => $item->source_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                ];
                $data[$item->classification_id]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
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

    private function BullyTypeByDayGroup($raw)
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

        $items = $raw->get();

        foreach ($items as $item) {

            $day_name = Carbon::parse($item->date_m)->format('D');
            $index_label = array_search($day_name, $data['labels']);


            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;


            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
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

    private function BullyTypeByTimeGroup($raw)
    {
        $data = null;
        $data['labels'] = [
            "Before 6 AM",
            "6 AM-12 PM",
            "12 PM-6 PM",
            "After 6 PM"
        ];

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
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
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

    private function BullyTypeByDeviceGroup($raw)
    {
        $data = null;
        $data['labels'] = [
            "Android",
            "Iphone",
            "Web App",
        ];

        $items = $raw->get();

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
                        'keyword_name' => $item->classification_name,
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

    private function BullyTypeByAccountGroup($raw)
    {
        $data['labels'] = [
            "Infulencer",
            "Follower",
        ];

        $table = 'message_result_full_data';

        $infulencer_root = $raw;
        $infulencers = $infulencer_root->get();


        foreach ($infulencers as $infulencer) {

            if (isset($data['value'][$infulencer->classification_id]['data'][0])) {
                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                }
            } else {
                $data['value'][$infulencer->classification_id]['id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['classification_id'] = $infulencer->classification_id;
                $data['value'][$infulencer->classification_id]['keyword_name'] = $infulencer->classification_name;
                $data['value'][$infulencer->classification_id]['data'][0] = 0;

                if (!$infulencer->reference_message_id) {
                    $data['value'][$infulencer->classification_id]['data'][0] += 1;
                }

            }

        }

        $follower_raw = $raw;
        $followers = $follower_raw->get();


        foreach ($followers as $follower) {

            if (isset($data['value'][$follower->classification_id]['data'][1])) {
                if ($follower->reference_message_id) {
                    $data['value'][$follower->classification_id]['data'][1] += 1;
                }
            } else {
                $data['value'][$follower->classification_id]['id'] = $follower->classification_id;
                $data['value'][$follower->classification_id]['keyword_name'] = $follower->classification_name;
                $data['value'][$follower->classification_id]['data'][1] = 0;

                if ($follower->reference_message_id) {
                    $data['value'][$follower->classification_id]['data'][1] += 1;
                }
            }

        }

        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }


        return $data;
    }

    private function BullyTypeByChannelGroup($raw)
    {
        $sources = Sources::where('status', 1)->get();
        $data['labels'] = [];

        if (!$this->user_login->is_admin) {
            $sources = Sources::whereIn('name', $this->organization_group->platform)->get();
        }

        foreach ($sources as $source_id) {
            $data['labels'][] = $source_id->name;
        }

        $items = $raw->get();
        $data['value'] = null;

        foreach ($items as $item) {
            $index_label = 0;
            $index_label = array_search($item->source_name, $data['labels']);

            if (isset($data['value'][$item->classification_id])) {
                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            } else {
                $data['value'][$item->classification_id] = [
                    'id' => $item->classification_id,
                    'classification_id' => $item->classification_id,
                    'keyword_name' => $item->classification_name,
                    'data' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                ];

                $data['value'][$item->classification_id]['data'][$index_label] += 1;
            }

        }


        if (isset($data['value'])) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    private function BullyTypeBySentimentGroup($raw)
    {
        $data['labels'] = [
            "Positive",
            "Neutral",
            "Negative",
        ];

        $bully_types = DB::table('classifications')->where('classification_type_id', 2)->get();

        foreach ($bully_types as $bully_type) {
            $data['value'][$bully_type->id] = [
                'id' => $bully_type->id,
                'classificetion_id' => $bully_type->id,
                'keyword_name' => $bully_type->name,
                'data' => [0, 0, 0]
            ];
        }

        $items = $raw->get();
        $anylsys = [];

        foreach ($items as $item) {
            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
        }

        foreach ($anylsys as $anylsy) {
            foreach ($bully_types as $bully_type) {

                if ($anylsy[2] === $bully_type->name) {
                    $index_label = array_search($anylsy[1], $data['labels']);
                    $data['value'][$bully_type->id]['data'][$index_label] += 1;
                }
            }
        }

        if ($data) {
            $data['value'] = array_values($data['value']);
        }

        return $data;
    }

    public function bullyTypeBy()
    {
        $data = null;
        $raw_classification_two = $this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['NoBully', 'Gossip', 'Harassment', 'Exclusion', 'HateSpeech', 'Violence']);
        $raw_classification_tree = $this->raw_message_classification_name($this->campaign_id, $this->start_date, $this->end_date, ['Level 0', 'Level 1', 'Level 2', 'Level 3']);

        $data['bully_type_by_level'] = $this->BullyChartLevelGroup($raw_classification_tree);
        $data['bully_chart_type'] = $this->BullyChartTypeGroup($raw_classification_two);
        $data['bully_chart_level'] = $this->BullyLevelLevelGroup($raw_classification_tree);
        $data['bully_table_type'] = $this->BullyTableTypeGroup($raw_classification_two);

        return parent::handleRespond($data);
    }

    private function BullyChartLevelGroup($raw)
    {
        $bully = $raw->get();

        $all = 0;
        $data = [];
        foreach ($bully as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $item->classification_name,
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

    private function BullyChartTypeGroup()
    {
        $bully = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
            ->whereIn('classification_type_id', [2]);
            // ->get();

        if ($this->keyword_id) {
            $bully->whereIn('keyword_id', $this->keyword_id);
        }

        if ($this->source_id) {
            $bully->where('source_id', $this->source_id);
        }

        $bully = $bully->get();

        $all = 0;

        $data = [];
        foreach ($bully as $item) {
            $data['all'] = [
                'keyword_name' => "all",
                'data' => $all += 1
            ];

            if (isset($data[$item->classification_id])) {
                $data[$item->classification_id]['data'] += 1;
                $data["all"]['data'] += 1;


            } else {
                $data[$item->classification_id] = [
                    'keyword_name' => $item->classification_name,
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

    private function BullyLevelLevelGroup($raw)
    {
        $items = $raw->get();

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

            if (isset($anylsys['all'])) {
                $anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$item->source_name]['total'] += 1;
            }

            if (isset($anylsys[$item->classification_name])) {
                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            } else {
                $anylsys[$item->classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $item->classification_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['id'] =  $i;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            }
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

    // public function BullyTableType(Request $request)
    // {

    //     $raw = DB::table('message_result_full_data')
    //         ->where('campaign_id', $this->campaign_id)
    //         ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
    //         ->whereIn('classification_type_id', [2]);

    //     if ($this->keyword_id) {
    //         $raw->where('keyword_id', $this->keyword_id);
    //     }

    //     if ($this->source_id) {
    //         $raw->where('source_id', $this->source_id);
    //     }

    //     $items = $raw->get();
    //     $soures = parent::listSource();
    //     $anylsys = [];
    //     $anylsys['all'] = [
    //         'id' => -1,
    //         'keyword_name' => "all",
    //         'total' => 0
    //     ];

    //     for ($i = 0; $i < count($soures['labels']); $i++) {
    //         $anylsys['all']['value'][$soures['labels'][$i]]['id'] = $i;
    //         $anylsys['all']['value'][$soures['labels'][$i]]['channel'] = $soures['labels'][$i];
    //         $anylsys['all']['value'][$soures['labels'][$i]]['percentage'] = 0;
    //         $anylsys['all']['value'][$soures['labels'][$i]]['total'] = 0;
    //     }


    //     foreach ($items as $item) {

    //         if (isset($anylsys['all'])) {
    //             $anylsys['all']["campaign_id"] = $item->campaign_id;
    //             $anylsys['all']["campaign_name"] = $item->campaign_name;
    //             $anylsys['all']['total'] += 1;
    //             $anylsys['all']['value'][$item->source_name]['total'] += 1;
    //         }

    //         if (isset($anylsys[$item->classification_name])) {
    //             $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
    //             $anylsys[$item->classification_name]['total'] += 1;
    //         } else {
    //             $anylsys[$item->classification_name] = [
    //                 "id" => $item->classification_id,
    //                 "keyword_name" => $item->classification_name,
    //                 "campaign_id" => $item->campaign_id,
    //                 "campaign_name" => $item->campaign_name,
    //                 "total" => 1,
    //             ];

    //             for ($i = 0; $i < count($soures['labels']); $i++) {
    //                 $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['id'] =  $i;
    //                 $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
    //                 $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
    //                 $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
    //             }

    //             $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
    //         }
    //     }

    //     $data = [];

    //     foreach ($anylsys as $key => $item) {
    //         $total = $item['total'];
    //         $data[$key] = [
    //             'id' => $item['id'],
    //             'keyword_name' => $item['keyword_name'],
    //             // 'campaign_id' => $item['campaign_id'],
    //             // 'campaign_name' => $item['campaign_name'],
    //             'value' => $item['value'],
    //             'total' => $item['total'],
    //         ];

    //         foreach ($item['value'] as $index => $value) {
    //             $data[$key]['value'][$index]['percentage'] = $value['total'] ? $value['total'] / $total * 100 : 0;
    //         }

    //     }

    //     if ($data) {
    //         $data = array_values($data);
    //     }

    //     foreach ($data as $key => $item) {
    //         $data[$key]['value'] = array_values($item['value']);
    //     }

    //     return parent::handleRespond($data);
    // }

    private function BullyTableTypeGroup($raw)
    {
        $items = $raw->get();
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

            if (isset($anylsys['all'])) {
                $anylsys['all']["campaign_id"] = $item->campaign_id;
                $anylsys['all']["campaign_name"] = $item->campaign_name;
                $anylsys['all']['total'] += 1;
                $anylsys['all']['value'][$item->source_name]['total'] += 1;
            }

            if (isset($anylsys[$item->classification_name])) {
                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            } else {
                $anylsys[$item->classification_name] = [
                    "id" => $item->classification_id,
                    "keyword_name" => $item->classification_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "total" => 0,
                ];

                for ($i = 0; $i < count($soures['labels']); $i++) {
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['id'] =  $i;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['channel'] =  $soures['labels'][$i];
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['total'] = 0;
                    $anylsys[$item->classification_name]['value'][$soures['labels'][$i]]['percentage'] = 0;
                }

                $anylsys[$item->classification_name]['value'][$item->source_name]['total'] += 1;
                $anylsys[$item->classification_name]['total'] += 1;
            }
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

    private function raw_message_classification_name($campaign_id, $start_date, $end_date, $classification_type_name)
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
            ->whereIn('classifications.name', $classification_type_name);

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
