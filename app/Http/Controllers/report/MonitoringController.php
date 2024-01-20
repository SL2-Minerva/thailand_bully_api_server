<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
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

        $this->request = $request;

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

    private function rawMessageCampaign($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.id AS id',
                'messages.author AS author',
                'messages.message_id AS message_id',
                'messages.reference_message_id AS reference_message_id',
                'messages.keyword_id AS keyword_id',
                'messages.message_datetime AS date_m',
                'messages.source_id AS source_id',
                'messages.full_message AS full_message',
                'messages.message_type',
                'messages.link_message AS link_message',
                'messages.device AS device',
                'messages.number_of_views AS number_of_views',
                'messages.number_of_comments AS number_of_comments',
                'messages.number_of_shares AS number_of_shares',
                'messages.number_of_reactions AS number_of_reactions',
                /*'message_results.classification_id',
                'message_results.classification_type_id',*/
                'messages.created_at AS created_at',
                'keywords.name AS keyword_name',
                'keywords.campaign_id AS campaign_id',
                /*'campaigns.name AS campaign_name',
                'classifications.classification_type_id',
                'classifications.name AS classification_name',
                'classifications.color AS classification_color',*/
                'sources.name AS source_name',
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) AS total_engagement')
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            //->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            /*->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')*/
            /*->join('classifications', 'message_results.classification_id', '=', 'classifications.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->where(function ($query) {
                $query->where('message_type', '=', 'Post')
                    ->orWhere('message_type', '=', 'post');
            })
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('messages.author')
            ->havingRaw('total_engagement > 0'); // Exclude rows with total_engagement = 0 if desired
        // ->orderByDesc('total_engagement');
        // ->get();

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }
        // $data->dd();
        return $data;
    }

    public function dailyBy(Request $request)
    {
        $data = null;
        $keyword = Keyword::where('campaign_id', $this->campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();

        $total_keywords = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.reference_message_id as reference_message_id',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        $total_keywords_previous = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'messages.source_id as source_id',
                'sources.name as source_name',
                'messages.message_datetime as date_m',
                'messages.reference_message_id as reference_message_id',
            ])
            ->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"]);

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        $data['daily_message'] = $this->dailyMessage($total_keywords);
        $data['date_of_messages_current'] = Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y');
        $data['date_of_messages_previous'] = Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y');
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->start_date, $this->end_date, $total_keywords);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->start_date_previous, $this->end_date_previous, $total_keywords_previous);

        return parent::handleRespond($data);
    }

    private function dailyMessage($total_keywords)
    {

        $items = $total_keywords->get();
        $data = null;

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');

            if (isset($data[$item->keyword_name])) {

                if (isset($data[$item->keyword_name]['value'][$date_format])) {
                    $data[$item->keyword_name]['value'][$date_format]['total_at_date'] += 1;
                } else {
                    $data[$item->keyword_name]['value'][$date_format] = [
                        "keyword_id" => $item->keyword_id,
                        "keyword_name" => $item->keyword_name,
                        "date_m" => $date_format,
                        'total_at_date' => 1
                    ];
                }

            } else {
                $data[$item->keyword_name] = [
                    "keyword_id" => $item->keyword_id,
                    "keyword_name" => $item->keyword_name,
                    "campaign_id" => $item->campaign_id,
                    "campaign_name" => $item->campaign_name,
                    "source_id" => $item->source_id,
                    "source_name" => $item->source_name,
                ];
                $data[$item->keyword_name]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $item->keyword_name,
                    'date_m' => $date_format,
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
            $data = array_values($data);
        }

        return $data;
    }

    private function percentageOfMessages($start_date, $end_date, $total_keywords)
    {

        $items = $total_keywords->get();
        $data = null;

        $message_keyword = [];
        $message_total = 0;

        foreach ($items as $item) {

            if (isset($message_keyword[$item->keyword_id])) {
                $message_keyword[$item->keyword_id] += 1;
            } else {
                $message_keyword[$item->keyword_id] = 1;
            }

            $message_total += 1;
        }

        $data = null;

        foreach ($message_keyword as $keyword_id => $value) {
            $percentage = 0;
            if ($value && $message_total) {
                $percentage = $message_total ? self::point_two_digits(($value / $message_total) * 100) : $message_total;
            }
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),
                'percentage' => $percentage,
            ];

        }

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;
            $data[$keyword_id]['keyword_id'] = $keyword_id;
            $data[$keyword_id]['keyword_name'] = $item->keyword_name;
            $data[$keyword_id]['campaign_id'] = $item->campaign_id;
            $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            $data[$keyword_id]['total'] = self::point_two_digits($message_total, 0);
        }


        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    /*private function getClassificationMaster()
    {
        return DB::table('tbl_classifications')
            ->join('tbl_classification_types', 'tbl_classifications.classification_type_id', '=', 'tbl_classification_types.id')
            ->get();
    }*/

    private function getClassificationName($message_id)
    {
        return DB::table('messages')
            ->select([
                'classifications.name AS classification_name',
                'classifications.classification_type_id AS classification_type_id'
            ])
            ->where('messages.id', $message_id)
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->limit(3)
            ->get(['classifications.classification_type_id', 'classification_name']);
    }

    private function selectData($raw, $select, $type = null)
    {
        return match ($select) {
            "top10" => $raw->limit(10)->get(),
            "top20" => $raw->limit(20)->get(),
            "top50" => $raw->limit(50)->get(),
            "top100" => $raw->limit(100)->get(),
            default => $raw->get(),
        };
    }

    private function getClassificationMaster()
    {
        return DB::table('classifications')->select("classifications.*", "classification_types.name as classification_type_name")
            ->leftJoin('classification_types', 'classifications.classification_type_id', '=', 'classification_types.id')
            ->get();
    }

    public function topEngagementOfPost(Request $request)
    {

        // $classificationTypes = self::getClassificationMaster();
        $raw = self::rawMessageCampaign($this->campaign_id, $this->start_date, $this->end_date);
        $raw_data = $raw->orderByDesc('total_engagement')->limit(5)->get();
        foreach ($raw_data as $item) {

            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->id);
            $parent = null;

            if (!$item->reference_message_id || $item->reference_message_id === null || $item->reference_message_id === '') {
                $parent = $item->message_id;
            }

            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->channel,
                /*"source_name" => $item->source_name,*/
                "link_message" => $item->link_message,
                "parent" => $parent,
                "engagement" => $item->number_of_shares + $item->number_of_comments + $item->number_of_reactions,
            ];


            // loop for get classification name
            foreach ($types as $type) {
                if ($type->classification_type_id == 1) {
                    $data_push['sentiment'] = $type->classification_name;
                }

                if ($type->classification_type_id == 2) {
                    $data_push['bully_type'] = $type->classification_name;
                }

                if ($type->classification_type_id == 3) {
                    $data_push['bully_level'] = $type->classification_name;
                }
            }

            $data[] = $data_push;
        }

        return parent::handleRespond($data);
    }

    public function engagementOfPost(Request $request)
    {
        $raw = self::rawMessageCampaign($this->campaign_id, $this->start_date, $this->end_date);
        $raw = $raw->orderByDesc('total_engagement');
        $raw_data = self::selectData($raw, $request->select);

        $classificationTypes = self::getClassificationMaster();
        $messageIds = $raw_data->pluck('id')->all();
        foreach ($raw_data as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->source_name,
                "source_name" => $item->source_name,
                "source_id" => $item->source_id,
                /*"source_name" => $item->source_name,*/
                "link_message" => $item->link_message,
                "parent" =>  $item->reference_message_id,
                "engagement" => $item->total_engagement
            ];
            $data[] = $data_push;
        }
        $result = array();
        if (count($messageIds) > 0) {
            $messageResult = DB::table('message_results')
                ->select('*')
                ->whereIn('message_id', $messageIds)->get();

            foreach ($data as $message) {
                $message['sentiment'] = "";
                $message['bully_type'] = "";
                $message['bully_level'] = "";
                $count = 0;
                foreach ($messageResult as $item) {
                    if ($message['id'] == $item->message_id) {
                        $count++;
                        $message = $this->packClassification($classificationTypes, $item, $message);
                    }
                    if ($count > 2) {
                        break;
                    }
                }
                $result[] = $message;
            }
        }

        return parent::handleRespond($result);
    }

    private function rawQueryMessage()
    {
        $query = DB::table('messages')
            ->select([
                'messages.*'/*,
                'message_results.classification_id as classification_id',
                'classifications.classification_type_id',
                'classifications.name AS classification_name',
                'classifications.color AS classification_color',*/
            ])
            /*->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')*/
            ->groupBy('messages.author');
        return $query;
    }

    public function detailOfPost(Request $request)
    {
        $messageId = $request->message_id;

        $comment = self::rawQueryMessage()->where('messages.message_id', '!=', $messageId)
            ->where('messages.reference_message_id', '=', $messageId)
            ->get();

        $post = self:: rawQueryMessage()->where('messages.message_id', $messageId)
            ->where('messages.reference_message_id', $messageId)->first();

        $post->sentiment = "";
        $post->bully_type = "";
        $post->bully_level = "";
        $types = $this->getClassificationName($post->id);
        foreach ($types as $type) {
            if ($type->classification_type_id == 1) {
                $post->sentiment = $type->classification_name;
            }

            if ($type->classification_type_id == 2) {
                $post->bully_type = $type->classification_name;
            }

            if ($type->classification_type_id == 3) {
                $post->bully_level = $type->classification_name;
            }
        }

        $classificationTypes = self::getClassificationMaster();
        $result = array();
        $messageIds = $comment->pluck('id')->all();
        if (count($messageIds) > 0) {
            $messageResult = DB::table('message_results')
                ->select('*')
                ->whereIn('message_id', $messageIds)->get();

            foreach ($post as $message) {
                $message['sentiment'] = "";
                $message['bully_type'] = "";
                $message['bully_level'] = "";
                $count = 0;
                foreach ($messageResult as $item) {
                    if ($message['id'] == $item->message_id) {
                        $count++;
                        $message = $this->packClassification($classificationTypes, $item, $message);
                    }
                    if ($count > 2) {
                        break;
                    }
                }
                $result[] = $message;
            }
        }
        $data = ["id" => $post->id ?? null,
            "message_id" => $post->message_id,
            "message_detail" => $post->full_message,
            "post_date" => Carbon::parse($post->message_datetime)->format('Y/m/d'),
            "post_time" => Carbon::parse($post->message_datetime)->format('H:i'),
            "icon" => "",
            "cover_image" => "",
            "source_id" => $post->source_id,
            "account_name" => $post->author,
            "message_type" => $post->message_type,
            "device" => $post->device,
            "message_datetime" => $post->message_datetime,
            "author" => $post->author,
            "parent" =>  "",
            "number_of_shares" => $post->number_of_shares,
            "number_of_reactions" => $post->number_of_reactions,
            "number_of_comments" => $post->number_of_comments,
            "number_of_views" => $post->number_of_views,
            "sentiment" => $post->sentiment,
            "bully_type" => $post->bully_type,
            "bully_level" => $post->bully_level,
            "link_message" => $post->link_message, "comments" => $result];
        return parent::handleRespond($data);
    }

    function packClassification($classificationTypes, $item, $message)
    {
        if ($item->classification_type_id == 1) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_id) {
                    $message['sentiment'] = $classificationType->name;
                    break;
                }
            }
        } else if ($item->classification_type_id == 2) {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_id) {
                    $message['bully_type'] = $classificationType->name;
                    break;
                }
            }
        } else {
            foreach ($classificationTypes as $classificationType) {
                if ($classificationType->id == $item->classification_id) {
                    $message['bully_level'] = $classificationType->name;
                    break;
                }
            }
        }
        return $message;
    }

    public function topInfluencerPost(Request $request)
    {
        $raw = self::rawMessageInfluencerCampaign($this->campaign_id, $this->start_date, $this->end_date);//->omit(['full_message']);
        $raw_data = $raw->limit(5)->get();

        $classificationTypes = self::getClassificationMaster();
        $messageIds = $raw_data->pluck('id')->all();
        foreach ($raw_data as $item) {
            //$date_d = Carbon::parse($item->date_m)->format('D');
            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->message_datetime)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->message_datetime)->format('H:i'),
                "icon" => "",
                "cover_image" => "",
                "message_count"=> $item->message_count,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->source_name,
                "source_name" => $item->source_name,
                "source_id" => $item->source_id,
                "link_message" => $item->link_message,
                "parent" =>  $item->reference_message_id,
                "engagement" => $item->total_engagement
            ];
            $data[] = $data_push;
        }
        $result = array();
        if (count($messageIds) > 0) {
            $messageResult = DB::table('message_results')
                ->select('*')
                ->whereIn('message_id', $messageIds)->get();

            foreach ($data as $message) {
                $message['sentiment'] = "";
                $message['bully_type'] = "";
                $message['bully_level'] = "";
                $count = 0;
                foreach ($messageResult as $item) {
                    if ($message['id'] == $item->message_id) {
                        $count++;
                        $message = $this->packClassification($classificationTypes, $item, $message);
                    }
                    if ($count > 2) {
                        break;
                    }
                }
                $result[] = $message;
            }
        }

        return parent::handleRespond($result);
    }

    public function influencerPost(Request $request)
    {

        $raw = self::rawMessageInfluencerCampaign($this->campaign_id, $this->start_date, $this->end_date);//->omit(['full_message']);
        $raw_data  = self::selectData($raw, $request->select);

        $classificationTypes = self::getClassificationMaster();
        $messageIds = $raw_data->pluck('id')->all();
        foreach ($raw_data as $item) {
            //$date_d = Carbon::parse($item->date_m)->format('D');
            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->message_datetime)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->message_datetime)->format('H:i'),
                "icon" => "",
                "cover_image" => "",
                "message_count"=> $item->message_count,
                "message_type" => $item->message_type,
                "device" => $item->device,
                "channel" => $item->source_name,
                "source_name" => $item->source_name,
                "source_id" => $item->source_id,
                "link_message" => $item->link_message,
                "parent" =>  $item->reference_message_id,
                "engagement" => $item->total_engagement
            ];
            $data[] = $data_push;
        }
        $result = array();
        if (count($messageIds) > 0) {
            $messageResult = DB::table('message_results')
                ->select('*')
                ->whereIn('message_id', $messageIds)->get();

            foreach ($data as $message) {
                $message['sentiment'] = "";
                $message['bully_type'] = "";
                $message['bully_level'] = "";
                $count = 0;
                foreach ($messageResult as $item) {
                    if ($message['id'] == $item->message_id) {
                        $count++;
                        $message = $this->packClassification($classificationTypes, $item, $message);
                    }
                    if ($count > 2) {
                        break;
                    }
                }
                $result[] = $message;
            }
        }

        return parent::handleRespond($result);
    }


    private function rawMessageInfluencerCampaign($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();
        $data = DB::table('messages')
            ->select('messages.*',/* 'k.name as keyword_name', 'k.campaign_id as campaign_id', */'s.name as source_name',  DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) AS total_engagement'))
            ->selectRaw('COUNT(CASE WHEN message_type IN (?, ?) THEN 1 END) AS message_count', ['post', 'Post'])
            //->leftJoin('keywords as k', 'k.id', '=', 'messages.keyword_id')

            ->leftJoin('sources as s', 's.id', '=', 'messages.source_id')
            //->where('k.campaign_id', 3)
            ->whereIn('messages.keyword_id', $keywordIds)
            ->whereBetween('messages.message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('messages.author')
            ->having('message_count', '>', 0)
            ->orderByDesc('message_count');

        if ($this->source_id) {
            $data->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $data->whereIn('source_id', $source_ids);
        }
        // $data->dd();
        return $data;
    }

}
