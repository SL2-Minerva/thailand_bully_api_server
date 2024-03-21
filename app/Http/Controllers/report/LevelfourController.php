<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Sources;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Keyword;

class LevelfourController extends Controller
{
    private $start_date;
    private $end_date;
    private $period;

    private $start_date_previous;
    private $end_date_previous;
    private $campaign_id;
    private $source_id;
    private $keyword_id;
    private $report_number;

    public function __construct(Request $request)
    {
        $this->campaign_id = $request->campaign_id ? $request->campaign_id : $request->campaignId;
        $this->start_date = $this->date_carbon($request->start_date) ?? null;
        $this->end_date = $this->date_carbon($request->end_date) ?? null;
        $this->report_number = $request->report_number ?? null;
        $this->period = $request->period;
        $this->start_date_previous = $this->get_previous_date($this->start_date, $this->period);
        $this->end_date_previous = $this->get_previous_date($this->end_date, $this->period);
        $this->source_id = $request->source_id === "all" ? "" : $request->source_id;

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

    public function dailyMessageLevelFour(Request $request)
    {

        //todo something
        $report_number = $request->report_number ?? null;
        $type = 1;

        if ($report_number === 'sna' ||
            // Over all
            $report_number === '1.2.002' ||
            // Voice Dashboard
            $report_number === '2.2.002' ||
            $report_number === '2.2.003' ||
            $report_number === '2.2.004' ||
            $report_number === '2.2.005' ||
            $report_number === '2.2.006' ||
            $report_number === '2.2.007' ||
            $report_number === '2.2.008' ||
            $report_number === '2.2.009' ||
            $report_number === '2.2.010' ||
            $report_number === '2.2.013' ||
            // Channel Dashboard
            $report_number === '3.2.002' ||
            $report_number === '3.2.003' ||
            $report_number === '3.2.004' ||
            $report_number === '3.2.005' ||
            $report_number === '3.2.006' ||
            $report_number === '3.2.007' ||
            $report_number === '3.2.008' ||
            $report_number === '3.2.009'

            // // Engagement Dashboard
            // $report_number === '4.2.002' ||
            // $report_number === '4.2.003' ||
            // $report_number === '4.2.004' ||
            // $report_number === '4.2.005' ||
            // $report_number === '4.2.006' ||
            // $report_number === '4.2.007' ||
            // $report_number === '4.2.008' ||
            // $report_number === '4.2.012' ||
            // $report_number === '4.2.013' ||
            // $report_number === '4.2.014' ||
            // $report_number === '4.2.015' ||
            // $report_number === '4.2.016' ||
            // $report_number === '4.2.017' ||
            // // Sentiment Dashboard
            // $report_number === '5.2.002' ||
            // $report_number === '5.2.003' ||
            // $report_number === '5.2.004' ||
            // $report_number === '5.2.005' ||
            // $report_number === '5.2.006' ||
            // $report_number === '5.2.007' ||
            // $report_number === '5.2.008' ||
            // $report_number === '5.2.009' ||
            // // Bully Dashboard
            // $report_number === '6.2.002' ||
            // $report_number === '6.2.003' ||
            // $report_number === '6.2.004' ||
            // $report_number === '6.2.005' ||
            // $report_number === '6.2.006' ||
            // $report_number === '6.2.007' ||
            // $report_number === '6.2.008' ||
            // $report_number === '6.2.012' ||
            // $report_number === '6.2.013' ||
            // $report_number === '6.2.014' ||
            // $report_number === '6.2.015' ||
            // $report_number === '6.2.016' ||
            // $report_number === '6.2.017' ||
            // $report_number === '6.2.018'
        ) {

            if ($request->sna_type) {
                if ($request->sna_type === 'sentiment') {
                    $data = $this->getSNAbyType($request, 1);
                }

                if ($request->sna_type === 'bullyLevel') {
                    $data = $this->getSNAbyType($request, 3);
                }

                if ($request->sna_type === 'bullyType') {
                    $data = $this->getSNAbyType($request, 2);
                }
            }

            return parent::handleRespond($data);
        } else {
            return parent::handleRespond($this->getSNAbyType($request, $type));
        }


    }


    private function getSNAbyType($request, $type = 1)
    {
        $keywords = self::findKeywords($this->campaign_id, $this->keyword_id);
        $roots = $this->getNode($keywords, $request->source, $request->message_id, $request->limit,false, $type, []);

        $messageIds = [];
        if ($roots && $roots["nodes"]) {

            foreach ($roots["nodes"] as $node) {
                $messageIds[] = $node["id"];
            }
        }

        $child = $this->getNode($keywords, $request->source, $request->message_id, $request->limit,true, $type, $messageIds);

        $nodes = array_merge($roots['nodes'] ?? [], $child['nodes'] ?? []);
        $data = ['nodes' => null, 'edges' => null];

        $check = [];
        foreach ($nodes as $node) {
            // data from each node;

            if (!in_array($node['id'], $check)) {
                $check[] = $node['id'];
                $data['nodes'][] = $node;

                if (isset($node['parent_id']) && $node['parent_id']) {

                    $data['edges'][] = [
//                    'from' => $node['parent_id'],
//                    'to' => $node['id'],
                        'from' => $node['id'],
                        'to' => $node['parent_id'],
                        "width" => 10,
//                        "width" => (int)$node['length'] >= 30 ? (int)$node['length'] / 10 : (int)$node['length'],
                        "length" => (int)$node['length'] ? (int)$node['length'] * 10 : 250,
                        "color" => $node['color'],
                        "link_message" => $node['link_message']
                    ];
                }
            }
        }

        return $data;
    }

    private function getNode($keywords,$source, $message_id,$limit, $is_child, $type, $parentMessageIds)
    {

        $keywordIds = $keywords->pluck('id')->all();

        if ($is_child) {
        
            $raw = $this->message()
                ->where('message_results.classification_type_id', $type);
                if  ($parentMessageIds!=null){
                    $raw = $raw->whereIn('messages.reference_message_id', $parentMessageIds);
                }
                $raw =$raw->limit($limit);
        } else {
            if ($message_id) {
                $raw = $this->message()
                ->where("message_results.classification_type_id", $type)
                ->where("messages.message_id", $message_id);
            } else {
                $raw = $this->message_root($keywordIds, $this->start_date, $this->end_date)
                    ->where('message_results.classification_type_id', $type)
                    ->where('messages.reference_message_id', '')
                    ->limit(500)
                    ->groupBy("messages.message_id");
            }
        }

        error_log("source ID: ".$source);

        

        if ($source != "all") {
            $raw= $raw->where('messages.source_id', $source);
            error_log($raw->toSql());
        }
        $items = $raw->get();
        $data = [];
        //$checkparent = [];

        $classification = parent::getClassificationMaster();
        foreach ($items as $item) {
            $influent_rate = $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions;
            $influent_rate = $item->total_engagement > 0 ? $influent_rate / $item->total_engagement * 10 : 0;

            $data_push = [
                "id" => $item->message_id,
                "label_name" => $item->author,
                "title" => $item->author,
                "color" => parent::matchClassificationColor($classification, $item->classification_id),
                "shape" => "dot",
                "size" => $this->factorNodeSize($influent_rate, $is_child),
                'link_message' => $item->link_message ?? ""
            ];


//            if ($is_child) {
            if ($influent_rate){
                $data_push["length"] = (int)$influent_rate ?? 1;
            }else{
                $data_push["length"] = (int)$influent_rate <= 0 ? 10 : (int)$influent_rate + 5;
            }
            $data_push["parent_id"] = $item->reference_message_id;
//            }

            $data['nodes'][] = $data_push;
        }

        return $data;
    }


    private function factorNodeSize($influent_rate = 0, $is_child = false)
    {
        if ($is_child) {
            if (!$influent_rate || $influent_rate <= 0) {
                return 40;
            }

            return round($influent_rate) != 0 ? round($influent_rate) * 10 : 40;
        }

        return round($influent_rate) != 0 ? round($influent_rate) * 10 : 80;
    }

    private function message()
    {

        //->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);


        return DB::table('messages')
            ->select([
                'messages.keyword_id as keyword_id',
                'messages.source_id as source_id',
                'messages.message_datetime as date_m',
                'messages.number_of_views as number_of_views',
                'messages.number_of_comments as number_of_comments',
                'messages.number_of_shares as number_of_shares',
                'messages.number_of_reactions as number_of_reactions',
                'messages.reference_message_id as reference_message_id',
                'message_results.classification_id as classification_id',
                'messages.author as author',
                'messages.message_id as message_id',
                'messages.link_message as link_message',
                DB::raw('COALESCE(number_of_comments, 0) +
                    COALESCE(number_of_shares, 0) +
                    COALESCE(number_of_reactions, 0) AS total_engagement'),
            ])
            ->leftJoin('message_results', 'message_results.message_id', '=', 'messages.id');
    }

    private function message_root($keywordIds, $start_date, $end_date)
    {

        return DB::table('messages')
            ->select([
                'messages.message_id',
                'messages.reference_message_id',
                'messages.keyword_id',
                'messages.message_datetime as date_m',
                'messages.author',
                'messages.source_id',
                'messages.full_message',
                'messages.message_type',
                'messages.device as device',
                'messages.number_of_views',
                'messages.number_of_comments',
                'messages.number_of_shares',
                'messages.number_of_reactions',
                'messages.created_at as created_at',
                'messages.link_message as link_message',
                'message_results.classification_type_id',
                'message_results.classification_id',
                /*'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'keywords.name as keyword_name',
                'classifications.name as classification_name',
                'classifications.color as classification_color',*/
                DB::raw('SUM(number_of_shares + number_of_comments + number_of_reactions) as total_engagement'),

            ])
            /*->join('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->join('sources', 'messages.source_id', '=', 'sources.id')
            ->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
            */ ->join('message_results', 'message_results.message_id', '=', 'messages.id')
            //->where('campaigns.id', $campaign_id)
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);
    }

}
