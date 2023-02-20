<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $report_number === '3.2.009' ||
            
            // Engagement Dashboard
            $report_number === '4.2.002' ||
            $report_number === '4.2.003' ||
            $report_number === '4.2.004' ||
            $report_number === '4.2.005' ||
            $report_number === '4.2.006' ||
            $report_number === '4.2.007' ||
            $report_number === '4.2.008' ||
            $report_number === '4.2.012' ||
            $report_number === '4.2.013' ||
            $report_number === '4.2.014' ||
            $report_number === '4.2.015' ||
            $report_number === '4.2.016' ||
            $report_number === '4.2.017' ||
            // Sentiment Dashboard
            $report_number === '5.2.002' ||
            $report_number === '5.2.003' ||
            $report_number === '5.2.004' ||
            $report_number === '5.2.005' ||
            $report_number === '5.2.006' ||
            $report_number === '5.2.007' ||
            $report_number === '5.2.008' ||
            $report_number === '5.2.009' ||
            // Bully Dashboard
            $report_number === '6.2.002' ||
            $report_number === '6.2.003' ||
            $report_number === '6.2.004' ||
            $report_number === '6.2.005' ||
            $report_number === '6.2.006' ||
            $report_number === '6.2.007' ||
            $report_number === '6.2.008' ||
            $report_number === '6.2.012' ||
            $report_number === '6.2.013' ||
            $report_number === '6.2.014' ||
            $report_number === '6.2.015' ||
            $report_number === '6.2.016' ||
            $report_number === '6.2.017' ||
            $report_number === '6.2.018'
            ) {

            $data = [
                "sentiment" => $this->getSNAbyType($request, 1),
                "bullyLevel" => $this->getSNAbyType($request, 2),
                "bullyType" => $this->getSNAbyType($request, 3),
            ];

            return parent::handleRespond($data);
        } else {
            return parent::handleRespond($this->getSNAbyType($request, $type));
        }


    }


    private function getSNAbyType($request, $type = 1)
    {
        $roots = $this->getNode($request, $request->message_id, $this->start_date, $this->end_date, false, $type);
        $childs = $this->getNode($request, $request->message_id, $this->start_date, $this->end_date, true, $type);
        $nodes = array_merge($roots['nodes'] ?? [], $childs['nodes'] ?? []);

        $data = ['nodes' => null, 'edges' => null];

        $check = [];
        foreach ($nodes as $node) {
            // data from each node;
            $data['nodes'][] = $node;

            if (!isset($check[$node['id']])) {
                $check[] = $node['id'];
//                $check[] = $node;
            }

            if (isset($node['parent_id']) && $node['parent_id']) {

                $data['edges'][] = [
//                    'from' => $node['parent_id'],
//                    'to' => $node['id'],

                    'from' => $node['id'],
                    'to' => $node['parent_id'],
                    "width" => (int)$node['length'] >= 30 ? (int)$node['length'] / 10 : (int)$node['length'],
                    "length" => (int)$node['length'] ? (int)$node['length'] * 10 : 150,
                    "color" => $node['color']
                ];
            }
        }

        return $data;
    }

    private function getNode($request, $message_id, $start_date, $end_date, $is_child = false, $type = 1)
    {


        if ($message_id) {
            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);
        } else {
            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
//                ->where('message_type', 'Post')
                ->Where('reference_message_id', '')
                ->where('classification_type_id', $type)
                ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);
        }


//        17-02-2566 14:05 debug not use
//        $raw_total = DB::table('message_result_full_data')
//            ->where('campaign_id', $this->campaign_id)
//            ->where('message_type', 'Post')
//            ->orWhere('reference_message_id',  '')
//            ->whereIn('classification_type_id', [1])
//            ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);


        if ($is_child) {
            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->where('reference_message_id', '!=', '')
                ->where('classification_type_id', $type)
                ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"]);
        }


        if ($message_id && !$is_child) {
            $raw = $raw->where('message_id', $message_id);
        }

        if ($message_id && $is_child) {
            $raw = $raw->where('reference_message_id', $message_id);
        }

        $raw_total = DB::table('message_result_full_data')
            ->where('campaign_id', $this->campaign_id)
            ->where('classification_type_id', $type)
            ->whereBetween('date_m', [$start_date, $end_date]);

        $total_interaction_from = (int)$raw_total->sum(DB::raw('number_of_comments + number_of_shares + number_of_reactions'));

        if ($type) {
            $raw = $raw->where('classification_type_id', $type);
            $raw_total = $raw_total->where('classification_type_id', $type);

        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
            $raw_total->where('source_id', $this->source_id);

        }


        $items = $raw->get();

//        if ($is_child) {
//            dd($items);
//        }
        $data = [];
        $checkparent = [];
        foreach ($items as $item) {
            $influent_rate = $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions;
            $influent_rate = $total_interaction_from > 0 ? $influent_rate / $total_interaction_from * 100 : 0;
            $data_push = [
                "id" => $item->message_id,
                "label" => $item->author,
                "title" => $item->author,
                "color" => $item->classification_color,
                "shape" => "dot",
                "size" => $this->factorNodeSize($influent_rate),
            ];


//            if ($is_child) {
            $data_push["length"] = (int)$influent_rate <= 0 ? 10 : (int)$influent_rate + 10;
            $data_push["parent_id"] = $item->reference_message_id;
//            }


//            if ($message_id && $item->reference_message_id !== '') {
//                $parent = DB::table('message_result_full_data')
//                    ->where('message_id', $item->reference_message_id)
//                    ->first();
//                $checkparent[] = $parent->message_id;
//                $parent_data = [
//                    "id" => $parent->message_id,
//                    "label" => $parent->author,
//                    "title" => $parent->author,
//                    "color" => $parent->classification_color,
//                    "shape" => "dot",
//                    "size" => $this->factorNodeSize($influent_rate),
//                ];
//
//                $data['nodes'][] = $parent_data;
//
//            }
            
            if (isset($data['nodes'])) {
                $search = print_r(array_search($data_push['id'], $data['nodes']));
            } else {
                $search = false;
            }

            if(!$search) {
                $data['nodes'][] = $data_push;
            }
            
        }
        // dd($data);
        
        return $data;
    }


    private function factorNodeSize($influent_rate = 0)
    {
        if (!$influent_rate || $influent_rate <= 0) {
            return 20;
        }
        if ($influent_rate > 10) {
            return $influent_rate * 20;
        } else {
            return $influent_rate * 100;
        }

    }
}
