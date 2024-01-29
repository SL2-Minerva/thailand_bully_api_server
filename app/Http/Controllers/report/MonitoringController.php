<?php

namespace App\Http\Controllers\report;

use App\Exports\MonitoringExport;
use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

    private function rawMessageCampaign($keywords, $start_date, $end_date)
    {

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
                /*'keywords.name AS keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'classifications.classification_type_id',
                'classifications.name AS classification_name',
                'classifications.color AS classification_color',
                'sources.name AS source_name',*/
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) AS total_engagement')
            ])
            //->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            //->leftJoin('sources', 'messages.source_id', '=', 'sources.id')
            //->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            /*->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')*/
            /*->join('classifications', 'message_results.classification_id', '=', 'classifications.id')*/

            ->where(function ($query) {
                $query->where('message_type', '=', 'Post')
                    ->orWhere('message_type', '=', 'post');
            })
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
            ->groupBy('messages.author')
            ->havingRaw('total_engagement > 0'); // Exclude rows with total_engagement = 0 if desired
        // ->orderByDesc('total_engagement');
        // ->get();

        $keywordIds = $keywords->pluck('id')->all();
        if ($keywordIds)
            $data->whereIn('messages.keyword_id', $keywordIds);

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
                'messages.source_id as source_id',
                /*'sources.name as source_name',
                'campaigns.name AS campaign_name',
                'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',*/
                'messages.message_datetime as date_m',
                'messages.reference_message_id as reference_message_id',
            ])
            //->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            /*->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }

        $total_keywords_previous = DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                /*'keywords.name as keyword_name',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'sources.name as source_name',*/
                'messages.source_id as source_id',
                'messages.message_datetime as date_m',
                'messages.reference_message_id as reference_message_id',
            ])
            /*->leftJoin('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->leftJoin('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->leftJoin('sources', 'messages.source_id', '=', 'sources.id')*/
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$this->start_date_previous . " 00:00:00", $this->end_date_previous . " 23:59:59"]);

        if ($this->source_id) {
            $total_keywords->where('source_id', $this->source_id);
        }


        $sources = DB::table('sources')->where("status", "=", 1)->get();
        $keywords = DB::table("keywords")->where('campaign_id', $this->campaign_id)->get();
        $campaign = DB::table('campaigns')->where('id', $this->campaign_id)->first();
        $data['daily_message'] = $this->dailyMessage($total_keywords, $sources, $keywords, $campaign);
        $data['date_of_messages_current'] = Carbon::createFromFormat('Y-m-d', $this->start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date)->format('d/m/Y');
        $data['date_of_messages_previous'] = Carbon::createFromFormat('Y-m-d', $this->start_date_previous)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $this->end_date_previous)->format('d/m/Y');
        $data['prcentage_of_messages_current'] = $this->percentageOfMessages($this->start_date, $this->end_date, $total_keywords, $sources, $keywords, $campaign);
        $data['prcentage_of_messages_previous'] = $this->percentageOfMessages($this->start_date_previous, $this->end_date_previous, $total_keywords_previous, $sources, $keywords, $campaign);

        return parent::handleRespond($data);
    }

    private function dailyMessage($total_keywords, $sources, $keywords, $campaign)
    {
        $data = [];

        $items = $total_keywords
            ->get();

        foreach ($items as $item) {
            $date_format = Carbon::parse($item->date_m)->format('Y-m-d');
            $keyword = self::matchKeywordName($keywords, $item->keyword_id);

            $data[$keyword]['keyword_id'] = $item->keyword_id;
            $data[$keyword]['keyword_name'] = $keyword;
            $data[$keyword]['campaign_id'] = $campaign->id;
            $data[$keyword]['campaign_name'] = $campaign->name;
            $data[$keyword]['source_id'] = $item->source_id;
            $data[$keyword]['source_name'] = self::matchSourceName($sources, $item->source_id);

            if (!isset($data[$keyword]['value'][$date_format])) {
                $data[$keyword]['value'][$date_format] = [
                    'keyword_id' => $item->keyword_id,
                    'keyword_name' => $keyword,
                    'date_m' => $date_format,
                    'total_at_date' => 0,
                ];
            }

            $data[$keyword]['value'][$date_format]['total_at_date'] += 1;
        }

        $data = array_values($data);

        foreach ($data as &$item) {
            $item['value'] = array_values($item['value']);
        }

        return $data;
    }


    private function percentageOfMessages($start_date, $end_date, $total_keywords, $sources, $keywords, $campaign)
    {

        $items = $total_keywords->get();

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
            $data[$keyword_id]['keyword_name'] = self::matchKeywordName($keywords, $keyword_id);
            $data[$keyword_id]['campaign_id'] = $campaign->id;
            $data[$keyword_id]['campaign_name'] = $campaign->name;
            $data[$keyword_id]['total'] = self::point_two_digits($message_total, 0);
        }

        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    private function getClassificationName($message_id)
    {
        return DB::table('message_results')
            ->select([
                'classifications.name AS classification_name',
                'classifications.classification_type_id AS classification_type_id'
            ])
            ->where('message_results.message_id', $message_id)
            //->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id')
            ->leftJoin('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->limit(3)
            ->get(['classifications.classification_type_id', 'classification_name']);
    }


    public function topEngagementOfPost(Request $request)
    {

        // $classificationTypes = self::getClassificationMaster();
        $keywords = $this->findKeywords($request->campaign_id, $request->keyword_id);
        $raw = self::rawMessageCampaign($keywords, $this->start_date, $this->end_date);
        $raw_data = $raw->orderByDesc('total_engagement')->limit(5)->get();
        $source = DB::table('sources')->where("status", "=", 1)->get();
        foreach ($raw_data as $item) {

            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->id);
            $parent = null;

            if ($item->reference_message_id && $item->reference_message_id != '') {
                $parent = $item->reference_message_id;
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
                "full_message" => $item->full_message,
                "device" => $item->device,
                "source_name" => parent::matchSourceName($source, $item->source_id),
                "link_message" => $item->link_message,
                "parent" => $parent,
                "total_engagement" => $item->total_engagement,
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
        $keywords = $this->findKeywords($request->campaign_id, $request->keyword_id);
        $raw = self::rawMessageCampaign($keywords, $this->start_date, $this->end_date);
        $query = $raw->orderByDesc('total_engagement');

        $limit = self::selectData($request->select);
        if ($limit == 0) {
            $limit = $request->limit;
            $count = $raw->count(["*"]);
        } else {
            $count = $limit;
        }

        $page = $request->page;
        if ($page == null || $page == 0)
            $page = 1;
        if ($limit == null || $limit == 0)
            $limit = 10;

        $offset = $limit * ($page - 1);

        $raw_data = $query->limit($limit)->offset($offset)->get();


        $result = self::parseEngamement($raw_data);
        return parent::handleRespondPage($result, ['total_rows' => $count, 'limit' => intval($limit), 'page' => intval($request->page)]);
    }

    public function engagementExport(Request $request)
    {
        $keywords = $this->findKeywords($request->campaign_id, $request->keyword_id);
        $raw = self::rawMessageCampaign($keywords, $this->start_date, $this->end_date);
        $raw = $raw->orderByDesc('total_engagement');
        $raw_data = $raw->limit(self::selectData($request->select))->get();
        $result = self::parseEngamement($raw_data);
        return Excel::download(new MonitoringExport($result, 'engagement'), 'monitoring-engagement-' . Carbon::now() . '.xlsx');
    }

    private function parseEngamement($raw_data)
    {
        $data = array();
        $messageIds = $raw_data->pluck('id')->all();
        $source = DB::table('sources')->where("status", "=", 1)->get();
        $keywordName = DB::table('keywords')->where("status", "=", 1)
            ->where("campaign_id", "=", $this->campaign_id)->get();
        foreach ($raw_data as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "account_name" => $item->author,
                "full_message" => $item->full_message,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('H:i'),
                "day" => $date_d,
                "date_m" => $item->date_m,
                "message_type" => $item->message_type,
                "device" => $item->device,
                //"source_name" => $item->source_name,
                "source_id" => $item->source_id,
                "source_name" => self::matchSourceName($source, $item->source_id),
                "keyword_name" => self::matchKeywordName($keywordName, $item->keyword_id),
                /*"source_name" => $item->source_name,*/
                "link_message" => $item->link_message,
                "parent" => $item->reference_message_id,
                "total_engagement" => $item->total_engagement
            ];
            $data[] = $data_push;
        }
        return self::parseEngagementLevel($messageIds, $data);
    }

    private function rawQueryMessage()
    {
        return DB::table('messages')->select(['messages.*']);
    }

    public function detailOfPost(Request $request)
    {
        $messageId = $request->message_id;


        $post = self:: rawQueryMessage()->where('messages.id', $messageId)->first();
        $comment = self::rawQueryMessage()->where('messages.message_type', '=', "Comment")
            ->where('messages.reference_message_id', '=', $post->message_id)
            ->get();
        if (!$post) {
            return parent::handleNotFound(null);
        }

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
        $resultComment = [];

        if ($comment != null && count($comment) > 0) {
            $messageIds = $comment->pluck('id')->all();
            $commentData = [];
            foreach ($comment as $item) {
                $rs = ["id" => $item->id ?? null,
                    "message_id" => $item->message_id,
                    "message_detail" => $item->full_message,
                    "post_date" => Carbon::parse($item->message_datetime)->format('Y/m/d'),
                    "post_time" => Carbon::parse($item->message_datetime)->format('H:i'),
                    "icon" => "",
                    "cover_image" => "",
                    "source_id" => $item->source_id,
                    "account_name" => $item->author,
                    "message_type" => $item->message_type,
                    "device" => $item->device,
                    "message_datetime" => $item->message_datetime,
                    "author" => $item->author,
                    "number_of_shares" => $item->number_of_shares,
                    "number_of_reactions" => $item->number_of_reactions,
                    "number_of_comments" => $item->number_of_comments,
                    "number_of_views" => $item->number_of_views,
                    "link_message" => $item->link_message];
                $commentData[] = $rs;
            }

            $resultComment = self::parseEngagementLevel($messageIds, $commentData);
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
            "parent" => "",
            "number_of_shares" => $post->number_of_shares,
            "number_of_reactions" => $post->number_of_reactions,
            "number_of_comments" => $post->number_of_comments,
            "number_of_views" => $post->number_of_views,
            "sentiment" => $post->sentiment,
            "bully_type" => $post->bully_type,
            "bully_level" => $post->bully_level,
            "link_message" => $post->link_message, "comments" => $resultComment];
        return parent::handleRespond($data);
    }

    public function topInfluencerPost(Request $request)
    {
        $raw = self::rawMessageInfluencerCampaign($this->campaign_id, $this->start_date, $this->end_date, $request->limit, $request->page);
        $result = self::parseInfluencer($raw);
        if (count($result) > 5) {
            $result = array_slice($result, 0, 5);
        }
        return parent::handleRespondPage($result, ['total_rows' => count($result), 'limit' => intval($request->limit), 'page' => intval($request->page)]);
    }

    function parseInfluencer($raw)
    {
        {
            $source = DB::table('sources')->where("status", "=", 1)->get();
            $finalResults = [];

// Loop through each author's messages
            foreach ($raw as $messages) {
                $positiveCount = $messages["positive"];
                $negativeCount = $messages["negative"];
                $neutralCount = $messages["neutral"];
                $totalSentiment = $messages["total_sentiment"];

                // Loop through each message
                foreach ($messages["classification"] as $classification) {
                    // Count classifications
                    switch ($classification) {
                        case 1:
                            $positiveCount++;
                            break;
                        case 2:
                            $negativeCount++;
                            break;
                        case 3:
                            $neutralCount++;
                            break;
                        // Add more cases if needed
                    }
                    $totalSentiment++;
                }
                $messages['positive'] = round(($positiveCount / $totalSentiment) * 100, 2);
                $messages['negative'] = round(($negativeCount / $totalSentiment) * 100, 2);
                $messages['neutral'] = round(($neutralCount / $totalSentiment) * 100, 2);
                $messages['total_sentiment'] = $totalSentiment;
                $messages["icon"] = "";
                $messages["cover_image"] = "";
                $messages["account_name"] = $messages['author'];
                $messages["source_name"] = self::matchSourceName($source, $messages['source_id']);
                unset($messages["classification"]);
                unset($messages["author"]);
                $finalResults[] = $messages;
            }
        }
        return $finalResults;
    }

    public function influencerPost(Request $request)
    {
        $limit = self::selectData($request->select);
        $page = $request->page;
        if ($limit == null || $limit == 0)
            $limit = 10;
        if ($page == null || $page == 0)
            $page = 1;
        $raw = self::rawMessageInfluencerCampaign($this->campaign_id, $this->start_date, $this->end_date);
        $result = self::parseInfluencer($raw);
        $count = count($result);
        if (count($result) > $limit) {
            $offset = $limit * ($page - 1);
            $result = array_slice($result, $offset, $limit);
        }
        return parent::handleRespondPage($result, ['total_rows' => $count, 'limit' => intval($limit), 'page' => intval($page)]);
    }

    /*
        private function parseInfluencer($raw)
        {
            $data = array();

            //error_log(json_encode($source));

            foreach ($raw as $item) {
                $data_push = [
                    "account_name" => $item->author,
                    "source_name" => self::matchSource($source, $item->source_id),
                    "icon" => "",
                    "cover_image" => "",
                    "total_post" => ($item->total_post / 3),
                    "total_engagement" => ($item->total_engagement / 3),
                    "positive" => $item->positive,
                    "negative" => $item->negative,
                    "neutral" => $item->neutral,
                ];
                $data[] = $data_push;
            }
            return $data;
        }*/


    public function influencerAuthor(Request $request)
    {
        $keywords = $this->findKeywords($request->campaign_id,$request->keyword_id);

        $keywordIds = $keywords->pluck('id')->all();
        $query = DB::table('messages')
            ->select('messages.*', DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) AS total_engagement'))
            //->leftJoin('sources as s', 's.id', '=', 'messages.source_id')
            ->where('message_type', '!=', 'Comment')
            ->where('message_type', '!=', 'Reply Comment')
            ->whereIn('messages.keyword_id', $keywordIds)
            ->whereBetween('messages.message_datetime', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);

        if ($this->source_id) {
            $query->where('source_id', $this->source_id);
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $query->whereIn('source_id', $source_ids);

        }
        $limit = $request->limit;
        $page = $request->page;
        if ($page == null || $page == 0)
            $page = 1;
        if ($limit == null || $limit == 0)
            $limit = 10;
        $offset = $limit * ($page - 1);
        $baseQuery = $query->where("messages.author", $request->author);
        $total = $baseQuery->count();
        $dataRaw = $query->where("messages.author", $request->author)->limit($limit)->offset($offset)->orderByDesc("messages.message_datetime")->get();


        $source = DB::table('sources')->where("status", "=", 1)->get();
        $data = array();
        $messageIds = $dataRaw->pluck('id')->all();
        foreach ($dataRaw as $item) {
            $data_push = [
                "id" => $item->id ?? null,
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->message_datetime)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->message_datetime)->format('H:i'),
                "icon" => "",
                "cover_image" => "",
                "message_type" => $item->message_type,
                "device" => $item->device,
                "source_name" => self::matchSourceName($source, $item->source_id),
                "source_id" => $item->source_id,
                "link_message" => $item->link_message,
                "parent" => $item->reference_message_id,
                "total_engagement" => $item->total_engagement
            ];
            $data[] = $data_push;
        }
        $result = self::parseEngagementLevel($messageIds, $data);
        return parent::handleRespondPage($result, ['total_rows' => $total, 'limit' => intval($limit), 'page' => intval($page)]);
    }

    private function parseEngagementLevel($messageIds, $data)
    {
        $classificationTypes = self::getClassificationJoinTypeMaster();
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
                        $message = $this->packClassificationTypeName($classificationTypes, $item, $message);
                    }
                    if ($count > 2) {
                        break;
                    }
                }
                $result[] = $message;
            }
        }
        return $result;
    }

    public function influencerExport(Request $request)
    {
        $raw = self::rawMessageInfluencerCampaign($request->campaign_id, $this->start_date, $this->end_date);
        $result = self::parseInfluencer($raw);
        return Excel::download(new MonitoringExport($result, 'sentiment'), 'monitoring-sentiment-' . Carbon::now() . '.xlsx');
    }

    private function rawMessageInfluencerCampaign($campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();

        $keywordIds = $keyword->pluck('id')->all();


        $sourceQuery = "";
        if ($this->source_id) {
            $sourceQuery = "m.source_id = $this->source_id AND ";
        }

        if (!$this->user_login->is_admin) {
            $source_ids = Sources::whereIn('name', $this->organization_group->platform)->pluck('id')->toArray();
            $sourceQuery = "m.source_id IN (" . implode(",", $source_ids) . ") AND ";
        }

        $rows = DB::select("SELECT m.id,m.source_id,
    m.author,m.message_id,m.message_type,m.message_datetime,m.message_id,m.number_of_comments,m.number_of_shares,m.number_of_reactions,mr.classification_id,m.reference_message_id
FROM
    tbl_messages m
    LEFT JOIN tbl_message_results mr ON m.id = mr.message_id
WHERE
    $sourceQuery
    keyword_id IN (" . implode(",", $keywordIds) . ")
    AND author != ''
   AND mr.classification_type_id=1
    AND message_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59' ORDER BY message_type DESC");
        $newGroupedData = [];


        foreach ($rows as $message) {
            if (($message->message_type !== 'Comment' && $message->message_type !== 'comment' && $message->message_type !== 'Reply Comment') && $message->reference_message_id === "") {
                $totalEngagement = $message->number_of_comments + $message->number_of_reactions + $message->number_of_shares;
                //if ($totalEngagement > 10) {
                $messageId = $message->message_id;
                $author = $message->author;

                // Increment post count for the author
                if (!isset($authorPostCount[$author])) {
                    $authorPostCount[$author] = 1;
                } else {
                    $authorPostCount[$author]++;
                }

                if (!isset($newGroupedData[$messageId])) {
                    $newGroupedData[$messageId] = [
                        'author' => $author,
                        'number_of_comments' => 0,
                        'number_of_shares' => 0,
                        'source_id' => 0,
                        'negative' => 0,
                        'positive' => 0,
                        'neutral' => 0,
                        'total_sentiment' => 0,
                        'number_of_reactions' => 0,
                        'message_datetime' => '',
                        'total_engagement' => 0,
                        'classification' => [],
                        'total_post' => 0
                    ];
                }

                switch ($message->classification_id) {
                    case 1:
                        $newGroupedData[$messageId]['positive'] = $newGroupedData[$messageId]['positive'] + 1;
                        break;
                    case 2:
                        $newGroupedData[$messageId]['negative'] = $newGroupedData[$messageId]['negative'] + 1;
                        break;
                    case 3:
                        $newGroupedData[$messageId]['neutral'] = $newGroupedData[$messageId]['neutral'] + 1;
                        break;
                }

                $newGroupedData[$messageId]['total_sentiment'] = $newGroupedData[$messageId]['total_sentiment'] + 1;
                $newGroupedData[$messageId]['number_of_comments'] += $message->number_of_comments;
                $newGroupedData[$messageId]['number_of_shares'] += $message->number_of_shares;
                $newGroupedData[$messageId]['number_of_reactions'] += $message->number_of_reactions;

                if ($message->message_datetime > $newGroupedData[$messageId]['message_datetime']) {
                    $newGroupedData[$messageId]['message_datetime'] = $message->message_datetime;
                }

                $newGroupedData[$messageId]['source_id'] = $message->source_id;

                $newGroupedData[$messageId]['total_engagement'] += $totalEngagement;
                $newGroupedData[$messageId]['total_post'] = $authorPostCount[$author];
                //   }
            } else if ($message->reference_message_id !== null && $message->reference_message_id !== "") {
                if (isset($newGroupedData[$message->reference_message_id])) {
                    $newGroupedData[$message->reference_message_id]['classification'][] = $message->classification_id;
                }
            }
        }

        foreach ($newGroupedData as $messageData) {
            $author = $messageData['author'];
            if (!isset($groupedResults[$author])) {
                $groupedResults[$author] = [
                    'author' => $author,
                    'number_of_comments' => 0,
                    'number_of_shares' => 0,
                    'number_of_reactions' => 0,
                    'negative' => 0,
                    'positive' => 0,
                    'neutral' => 0,
                    'total_sentiment' => 0,
                    'source_id' => 0,
                    'message_datetime' => '',
                    'total_engagement' => 0,
                    'classification' => [],
                    'total_post' => 0, // Initialize post count
                ];
            }

            $groupedResults[$author]['number_of_comments'] += $messageData['number_of_comments'];
            $groupedResults[$author]['number_of_shares'] += $messageData['number_of_shares'];
            $groupedResults[$author]['number_of_reactions'] += $messageData['number_of_reactions'];
            $groupedResults[$author]['negative'] += $messageData['negative'];
            $groupedResults[$author]['neutral'] += $messageData['neutral'];
            $groupedResults[$author]['positive'] += $messageData['positive'];
            $groupedResults[$author]['total_sentiment'] += $messageData['total_sentiment'];
            $groupedResults[$author]['source_id'] = $messageData['source_id'];

            if ($messageData['message_datetime'] > $groupedResults[$author]['message_datetime']) {
                $groupedResults[$author]['message_datetime'] = $messageData['message_datetime'];
            }

            $groupedResults[$author]['total_engagement'] += $messageData['total_engagement'];
            $groupedResults[$author]['classification'] = array_merge($groupedResults[$author]['classification'], $messageData['classification']);
            $groupedResults[$author]['total_post'] = $messageData['total_post'];
        }

// Convert associative array to indexed array
        $result = array_values($groupedResults);
        usort($result, function ($a, $b) {
            return $b['total_engagement'] <=> $a['total_engagement'];
        });
        return $result;
    }
}
