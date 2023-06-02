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

class LevelThreeTableController extends Controller
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

        if ($request->period === 'customrange') {
            $this->start_date_previous =  $this->date_carbon($request->start_date_period);
            $this->end_date_previous =  $this->date_carbon($request->end_date_period);
        }

        if (auth('api')->user()) {
            $this->user_login = auth('api')->user();

            $this->organization = Organization::find($this->user_login->organization_id);
            $this->organization_group = UserOrganizationGroup::find($this->organization->organization_group_id);
        }

    }

    public function messageLevelThree(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);

        $raw = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date);
        $total = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date);

        if ($request->message_id) {
            $raw->where('message_id', $request->message_id);
            $total->where('message_id', $request->message_id);
        }

        //Overall Dashboard
        if ($request->report_number === '1.2.002' ||
            $request->report_number === '2.2.002' ||
            $request->report_number === '2.2.013' ||
            $request->report_number === '3.2.002'

        ) {

            $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');

            $raw->whereBetween('message_datetime', [$date_request . " 00:00:00", $date_request . " 23:59:59"]);
            $total->whereBetween('message_datetime', [$date_request . " 00:00:00", $date_request . " 23:59:59"]);

            if ($request->report_number === '2.2.013') {
                $raw->whereNotNull('author')->groupBy('author');
                $total->whereNotNull('author')->groupBy('author');
            }

            if ($request->report_number === '3.2.002') { 
                $raw->where('sources.name', $Llabel);
                $total->where('sources.name', $Llabel);
            }
        }

        // Date Format
        if ($request->report_number === '2.2.003' || 
            $request->report_number === '3.2.003'
        ) {
            
            $raw->whereRaw('DATE_FORMAT(message_datetime, "%a") = ?', [$request->label]);
            $total->whereRaw('DATE_FORMAT(message_datetime, "%a") = ?', [$request->label]);

            if ($request->report_number === '3.2.003') { 
                $raw->where('sources.name', $Llabel);
                $total->where('sources.name', $Llabel);
            }
            
        }

        // time Format
        if ($request->report_number === '2.2.004') {

            if ($request->label === 'Before 6 AM') {

                $raw->whereRaw('HOUR(message_datetime) < ?', [6]);
                $total->whereRaw('HOUR(message_datetime) < ?', [6]);

            }

            if ($request->label === '6 AM-12 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [6, 12]);
                $total->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [6, 12]);
            }

            if ($request->label === '12 PM-6 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [12, 18]);
                $total->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [12, 18]);
            }

            if ($request->label === 'After 6 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ?', [18]);
                $total->whereRaw('HOUR(message_datetime) >= ?', [18]);
            }

        }

        //device Format
        if ($request->report_number === '2.2.005') {

            $target = 'dddddd';

            if ($label === 'Andriod' || $label === 'Android') {
                $target = 'android';
            }

            if ($label === 'Iphone') {
                $target = 'iphone';
            }

            if ($label === 'Web App') {
                $target = 'webapp';
            }
            // dd($target);
            $raw->where('device', $target);
            $total->where('device', $target);

        }

        //user_typr
        if ($request->report_number === '2.2.006') {

            if ($request->label === 'Post Owner') {
                $raw->where('reference_message_id', '');
                $total->where('reference_message_id', '');
            } else {
                $raw->where('reference_message_id', '!=', '');
                $total->where('reference_message_id', '!=', '');
            }

        }

        //source
        if ($request->report_number === '2.2.007') {
            $raw->where('sources.name', $label);
            $total->where('sources.name', $label);
        }

        // position
        if ($request->report_number === '2.2.008' ||
            $request->report_number === '2.2.009' ||
            $request->report_number === '2.2.010'
        ) {
            if ($label === 'Hate Speech') {
                $label = 'HateSpeech';
            } else if ($label === 'No Bully') {
                $label = 'NoBully';
            } else if ($label === 'Violence') {
                $label = 'Violence';
            }

            $total->where('classifications.name', '=', $label);
            $raw->where('classifications.name', '=', $label);
        }
        
        

        // Last
        if (isset($request->keyword_id)) {
            $raw->where('keyword_id', $request->keyword_id);
            $total->where('keyword_id', $request->keyword_id);
        }

        if (isset($request->meesage_id)) {
            $raw->where('message_id', $request->meesage_id);
            $total->where('message_id', $request->meesage_id);
        }

        $items = $raw->get();

        $parents = [];
        foreach ($items as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }


        foreach ($items as $ke => $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;

            if (array_search($item->message_id, $parents) !== false) {
                $parent = $item->message_id;
            }


            $data_push = [
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

            $data['message'][$item->message_id] = $data_push;
        }


        if (isset($data['message'])) {
            $data['message'] = array_values($data['message']);

            usort($data['message'], function ($a, $b) {
                return $b['engagement'] - $a['engagement'];
            });

            $data['total'] = count($data['message']);
            $offset = 9 + 1;

            $data['message'] = array_slice($data['message'], $start, $offset);
        }

        $data['total'] = $total->get()->count();
        return parent::handleRespond($data);
    }

    private function raw_message_classification(Request $request, $campaign_id, $start_date, $end_date)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $data = DB::table('messages')
            ->select([
                'messages.message_id AS message_id',
                'messages.reference_message_id AS reference_message_id',
                'messages.keyword_id AS keyword_id',
                'messages.message_datetime AS date_m',
                'messages.author AS author',
                'messages.source_id AS source_id',
                'messages.full_message AS full_message',
                'messages.message_type',
                'messages.link_message AS link_message',
                'messages.device AS device',
                'messages.number_of_views AS number_of_views',
                'messages.number_of_comments AS number_of_comments',
                'messages.number_of_shares AS number_of_shares',
                'messages.number_of_reactions AS number_of_reactions',
                'keywords.campaign_id AS campaign_id',
                'campaigns.name AS campaign_name',
                'keywords.name AS keyword_name',
                'classifications.classification_type_id',
                'message_results.classification_id',
                'classifications.color AS classification_color',
                'sources.name AS source_name',
                'messages.created_at AS created_at',
                'classifications.name AS classification_name'
                
            ])
            ->join('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->join('sources', 'messages.source_id', '=', 'sources.id')
            ->join('message_results', 'message_results.message_id', '=', 'messages.id')
            ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }

        if ($request->label === 'Sentiment' ||
            $request->label === 'Positive' ||
            $request->label === 'Neutral' ||
            $request->label === 'Negative' ||
            $request->label === 'Level 0' ||
            $request->label === 'Level 1' ||
            $request->label === 'Level 2' ||
            $request->label === 'Level 3' ||
            $request->label === 'No Bully' ||
            $request->label === 'Gossip' ||
            $request->label === 'Harassment' ||
            $request->label === 'Exclusion' ||
            $request->label === 'Hate Speech' ||
            $request->label === 'Violence'
        
        ) {
            $label = $request->label;
            if ($label === 'Hate Speech') {
                $label = 'HateSpeech';
            } else if ($label === 'No Bully') {
                $label = 'NoBully';
            } else if ($label === 'Violence') {
                $label = 'Violence';
            }
            $data->where('classifications.name', $label);
        } else {
            $data->whereIn('classifications.classification_type_id', [1]);
        }

        return $data;
    }

    private function getClassificationName($message_id)
    {
        return DB::table('message_result_full_data')->where('message_id', $message_id)
            ->limit(3)->get(['classification_type_id', 'classification_name']);
    }
}
