<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\Message;
use App\Models\MessageDeleteLog;
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

        if ($request->report_number === '5.2.008' ||
            $request->report_number === '5.2.009' ||
            $request->report_number === '6.2.008' ||
            $request->report_number === '6.2.018'
        ) {
            return $this->classifacation_multiple($request, $this->campaign_id, $this->start_date, $this->end_date, $request->report_number);
        } else {
            $raw = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date);
            $total = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date);
        }
        

        if ($request->message_id) {
            $raw->where('messages.message_id', $request->message_id);
            //$total->where('message_id', $request->message_id);
        }

        //Overall Dashboard
        if ($request->report_number === '1.2.002' ||
            $request->report_number === '2.2.002' ||
            $request->report_number === '2.2.013' ||
            $request->report_number === '3.2.002' ||
            $request->report_number === '4.2.002' ||
            $request->report_number === '4.2.012' ||
            $request->report_number === '5.2.002' ||
            $request->report_number === '6.2.002' ||
            $request->report_number === '6.2.012'

        ) {

            $date_request = Carbon::createFromFormat('d/m/Y', $request->label)->format('Y-m-d');

            $raw->whereBetween('message_datetime', [$date_request . " 00:00:00", $date_request . " 23:59:59"]);
            //$total->whereBetween('message_datetime', [$date_request . " 00:00:00", $date_request . " 23:59:59"]);

            if ($request->report_number === '2.2.013') {
                // $raw->whereNotNull('author')->groupBy('author');
                return $this->raw_account($request, $this->campaign_id, $date_request, $date_request, $request->report_number);
                // dd($raw->first());
                //$total->whereNotNull('author')->groupBy('author');
            }

            if ($request->report_number === '4.2.002') {
                //$total->where('keywords.name', $Llabel);
                $raw->where('keywords.name', $Llabel);
            }

            if ($request->report_number === '4.2.012') {
                if ($Llabel === 'Comment') {
                    $raw->where('messages.number_of_comments', '>', 0);
                    //$total->where('messages.number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('messages.number_of_reactions', '>', 0);
                    //$total->where('messages.number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('messages.number_of_shares', '>', 0);
                    //$total->where('messages.number_of_shares', '>', 0);
                }

            }
        }


        // Date Format
        if ($request->report_number === '2.2.003' || 
            $request->report_number === '3.2.003' ||
            $request->report_number === '4.2.003' ||
            $request->report_number === '4.2.013' ||
            $request->report_number === '5.2.003' ||
            $request->report_number === '6.2.003' ||
            $request->report_number === '6.2.013'
        ) {
            
            $raw->whereRaw('DATE_FORMAT(message_datetime, "%a") = ?', [$request->label]);
            //$total->whereRaw('DATE_FORMAT(message_datetime, "%a") = ?', [$request->label]);
            
            if ($request->report_number === '4.2.013') {
                if ($Llabel === 'Comment') {
                    $raw->where('number_of_comments', '>', 0);
                    //$total->where('number_of_comments', '>', 0);
                }

                if ($Llabel === 'reactions') {
                    $raw->where('number_of_reactions', '>', 0);
                    //$total->where('number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('number_of_shares', '>', 0);
                    //$total->where('number_of_shares', '>', 0);
                }

            }

        }

        // time Format
        if ($request->report_number === '2.2.004' ||
            $request->report_number === '3.2.004' ||
            $request->report_number === '4.2.004' ||
            $request->report_number === '4.2.014' ||
            $request->report_number === '5.2.004' ||
            $request->report_number === '6.2.004' ||
            $request->report_number === '6.2.014'
        ) {

            if ($request->label === 'Before 6 AM') {

                $raw->whereRaw('HOUR(message_datetime) < ?', [6]);
                //$total->whereRaw('HOUR(message_datetime) < ?', [6]);

            }

            if ($request->label === '6 AM-12 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [6, 12]);
                //$total->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [6, 12]);
            }

            if ($request->label === '12 PM-6 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [12, 18]);
                //$total->whereRaw('HOUR(message_datetime) >= ? AND HOUR(message_datetime) < ?', [12, 18]);
            }

            if ($request->label === 'After 6 PM') {
                $raw->whereRaw('HOUR(message_datetime) >= ?', [18]);
                //$total->whereRaw('HOUR(message_datetime) >= ?', [18]);
            }

            if ($request->report_number === '4.2.014') {
                if ($Llabel === 'Comment') {
                    $raw->where('messages.number_of_comments', '>', 0);
                    //$total->where('messages.number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('messages.number_of_reactions', '>', 0);
                    //$total->where('messages.number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('messages.number_of_shares', '>', 0);
                    //$total->where('messages.number_of_shares', '>', 0);
                }

            }

        }

        //device Format
        if ($request->report_number === '2.2.005' ||
            $request->report_number === '3.2.005' ||
            $request->report_number === '4.2.005' ||
            $request->report_number === '4.2.015' ||
            $request->report_number === '5.2.005' ||
            $request->report_number === '6.2.005' ||
            $request->report_number === '6.2.015'
        ) {

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
            $raw->where('device', $target);
            //$total->where('device', $target);
            
            if ($request->report_number === '4.2.015') {
                if ($Llabel === 'Comment') {
                    $raw->where('messages.number_of_comments', '>', 0);
                    //$total->where('messages.number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reactions') {
                    $raw->where('messages.number_of_reactions', '>', 0);
                    //$total->where('messages.number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('messages.number_of_shares', '>', 0);
                    //$total->where('messages.number_of_shares', '>', 0);
                }

            }

        }

        //user_typr
        if ($request->report_number === '2.2.006' ||
            $request->report_number === '3.2.006' ||
            $request->report_number === '4.2.006' ||
            $request->report_number === '4.2.016' ||
            $request->report_number === '5.2.006' ||
            $request->report_number === '6.2.006' ||
            $request->report_number === '6.2.016'
        ) {
            
            if ($request->report_number === '2.2.006') {
                if ($request->label === 'Post Owner') {
                    $raw->where('reference_message_id', '');
                    //$total->where('reference_message_id', '');
                } else {
                    $raw->where('reference_message_id', '!=', '');
                    //$total->where('reference_message_id', '!=', '');
                }
            } else {
                if ($label === 'Influencer') {
                    $raw->where('reference_message_id', '');
                    //$total->where('reference_message_id', '');
                } else {
                    $raw->where('reference_message_id', '!=', '');
                    //$total->where('reference_message_id', '!=', '');
                }
            }
            

            if ($request->report_number === '4.2.016') {
                if ($Llabel === 'Comment') {
                    $raw->where('messages.number_of_comments', '>', 0);
                    //$total->where('messages.number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reaction') {
                    $raw->where('messages.number_of_reactions', '>', 0);
                    //$total->where('messages.number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('messages.number_of_shares', '>', 0);
                    //$total->where('messages.number_of_shares', '>', 0);
                }

            }

        }

        //source
        if ($request->report_number === '2.2.007' ||
            $request->report_number === '4.2.007' ||
            $request->report_number === '4.2.017' ||
            $request->report_number === '5.2.007' ||
            $request->report_number === '6.2.007' ||
            $request->report_number === '6.2.017'
        ) {
            $raw->where('sources.name', $label);
            //$total->where('sources.name', $label);

            if ($request->report_number === '4.2.017') {
                if ($Llabel === 'Comment') {
                    $raw->where('messages.number_of_comments', '>', 0);
                    //$total->where('messages.number_of_comments', '>', 0);
                }

                if ($Llabel === 'Reaction') {
                    $raw->where('messages.number_of_reactions', '>', 0);
                    //$total->where('messages.number_of_reactions', '>', 0);
                }

                if ($Llabel === 'Share') {
                    $raw->where('messages.number_of_shares', '>', 0);
                    //$total->where('messages.number_of_shares', '>', 0);
                }
            }
        }

        if ($request->report_number === '4.2.008') {
            if ($request->label === "Share of Voice") {
                $raw->where('messages.number_of_shares', '>', 0);
                //$total->where('messages.number_of_shares', '>', 0);
            }

            if ($request->label === "Comments") {
                $raw->where('messages.number_of_comments', '>', 0);
                //$total->where('messages.number_of_comments', '>', 0);
            }

            if ($request->label === "Reaction") {
                $raw->where('messages.number_of_reactions', '>', 0);
                //$total->where('messages.number_of_reactions', '>', 0);
            }

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

            //$total->where('classifications.name', '=', $label);
            $raw->where('classifications.name', '=', $label);
        }

        if ($request->report_number === '3.2.008' ||
            $request->report_number === '3.2.007' ||
            $request->report_number === '3.2.009'

        ) {

            if ($label === 'Hate Speech') {
                $label = 'HateSpeech';
            } else if ($label === 'No Bully') {
                $label = 'NoBully';
            } else if ($label === 'Violence') {
                $label = 'Violence';
            }

            $raw = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date, [$label]);
            $total = $this->raw_message_classification($request, $this->campaign_id, $this->start_date, $this->end_date, [$label]);

        }

        if ($request->report_number === '3.2.002' ||
            $request->report_number === '3.2.003' ||
            $request->report_number === '3.2.004' ||
            $request->report_number === '3.2.005' ||
            $request->report_number === '3.2.006' ||
            $request->report_number === '3.2.007' ||
            $request->report_number === '3.2.008' ||
            $request->report_number === '3.2.009' 

        ) {
            // dd($raw->get());
            $raw->where('sources.name', $Llabel);
            //$total->where('sources.name', $Llabel);
        }

        if ($request->report_number === '3.2.013' ||
            $request->report_number === '3.2.014'
        ) {

            if ($request->select_period === 'previous') {
                $start_date = $this->start_date_previous;
                $end_date = $this->end_date_previous;
            } else {
                $start_date = $this->start_date;
                $end_date = $this->end_date;
            }

            $raw = $this->raw_message_classification($request, $this->campaign_id, $start_date, $end_date);

            // if ($request->report_number === '3.2.013') {
            //     $raw->addSelect([DB::raw('number_of_reactions + number_of_comments + number_of_reactions as total_engagement')])
            //         ->havingRaw('total_engagement > ?', [0]);
            // }

            $raw->where('sources.name', $label);

        }
        
        // Last
        
        if ($request->report_number !== "3.2.002" &&
            $request->report_number !== "4.2.008" &&
            $request->report_number !== '4.2.012' &&
            $request->report_number !== '4.2.013' &&
            $request->report_number !== '4.2.014' &&
            $request->report_number !== '4.2.015' &&
            $request->report_number !== "4.2.016" &&
            $request->report_number !== "4.2.017" &&
            $request->report_number !== '5.2.003' &&
            $request->report_number !== '5.2.004' &&
            $request->report_number !== '5.2.005' &&
            $request->report_number !== '5.2.006' &&
            $request->report_number !== '5.2.007' &&
            $request->report_number !== '5.2.008' 
        ) {
            if (isset($request->keyword_id)) {
                $raw->where('keyword_id', $request->keyword_id);
                //$total->where('keyword_id', $request->keyword_id);
            }
        }

        if (isset($request->meesage_id)) {
            $raw->where('messages.message_id', $request->meesage_id);
            //$total->where('message_id', $request->meesage_id);
        }

        $total = $raw->count();
        $items = $raw->offset($start)->limit($limit)->get();
        // $items = $raw->get();

        // $parents = [];
        // foreach ($items as $item) {
        //     if ($item->reference_message_id) {
        //         if (array_search($item->reference_message_id, $parents) === false) {
        //             $parents[] = $item->reference_message_id;
        //         }
        //     }
        // }


        foreach ($items as $ke => $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;

            // if (array_search($item->message_id, $parents) !== false) {
            //     $parent = $item->message_id;
            // }
            if (!$item->reference_message_id || $item->reference_message_id === null || $item->reference_message_id === '') {
                $parent = $item->message_id;
            }


            $data_push = [
                "id" => $item->id,
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
        //     $total = count($data['message']) ?? 0;
        //     usort($data['message'], function ($a, $b) {
        //         return $b['engagement'] - $a['engagement'];
        //     });

        //     $data['total'] = $total;
        //     $offset = 9 + 1;

        //     $data['message'] = array_slice($data['message'], $start, $offset);
        }

        $data['total'] = $total;
        // $data['total'] = $total;
        return parent::handleRespond($data);
    }

    private function raw_message_classification(Request $request, $campaign_id, $start_date, $end_date, $classification_name = null)
    {
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $Llabel = str_replace("+", " ", $request->Llabel);
        $label = str_replace("+", " ", $request->label);

        $data = DB::table('messages')
            ->select([
                'messages.id AS id',
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
                'classifications.name AS classification_name',
                DB::raw('COALESCE(tbl_messages.number_of_comments, 0) +
                    COALESCE(tbl_messages.number_of_shares, 0) +
                    COALESCE(tbl_messages.number_of_reactions, 0) AS total_engagement')
                
            ])
            ->join('keywords', 'messages.keyword_id', '=', 'keywords.id')
            ->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
            ->join('sources', 'messages.source_id', '=', 'sources.id')
            ->join('message_results', 'message_results.message_id', '=', 'messages.id')
            ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->whereIn('keyword_id', $keywordIds)
            ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"]);
            // ->orderByDesc('total_engagement');

        if ($request->sort && $request->field) {
            $field = $request->field;
            switch( $field ) {
                case 'message_type' : $field_table = "messages.message_type"; break;
                case 'author' : $field_table = "messages.author"; break;
                case 'date' : $field_table = "messages.message_datetime"; break;
                case 'device' : $field_table = "messages.device"; break;
                case 'source' : $field_table = "sources.name"; break;
                case 'engagement' : $field_table = "total_engagement"; break;
                case 'bully_type' : $field_table = "classifications.name"; break;
                case 'sentiment' : $field_table = "classifications.name"; break;
                case 'engagement' : $field_table = "classifications.name"; break;
                default : $field_table= "total_engagement"; break;
                 
            }
            
            $data->orderBy($field_table, $request->sort);

        } else {
            $data->orderByDesc('total_engagement');
        }

        
        if ($classification_name) {
            $data->whereIn('classifications.name', $classification_name);
        }

        // if ($this->source_id) {
        //     $data->where('source_id', $this->source_id);
        // }

        if ($request->report_number !== '3.2.013' &&
            $request->report_number !== '3.2.014' &&
            $request->report_number !== '5.2.002' &&
            $request->report_number !== '5.2.003' &&
            $request->report_number !== '5.2.004' &&
            $request->report_number !== '5.2.005' &&
            $request->report_number !== '5.2.006' &&
            $request->report_number !== '5.2.007' &&
            $request->report_number !== '5.2.008' &&
            $request->report_number !== '5.2.009' &&
            $request->report_number !== '6.2.002' &&
            $request->report_number !== '6.2.003' &&
            $request->report_number !== '6.2.004' &&
            $request->report_number !== '6.2.005' &&
            $request->report_number !== '6.2.006' &&
            $request->report_number !== '6.2.007' &&
            $request->report_number !== '6.2.008' &&
            $request->report_number !== '6.2.012' &&
            $request->report_number !== '6.2.013' &&
            $request->report_number !== '6.2.014' &&
            $request->report_number !== '6.2.015' &&
            $request->report_number !== '6.2.016' &&
            $request->report_number !== '6.2.017'
        ) {
            if ($request->label === 'Positive' ||
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
                
                if ($label === 'Hate Speech') {
                    $label = 'HateSpeech';
                } else if ($label === 'No Bully') {
                    $label = 'NoBully';
                } else if ($label === 'Violence') {
                    $label = 'Violence';
                }

                $data->where('classifications.name', $label);
            } else {
                $data->whereIn('classifications.name', ['Neutral', 'Positive', 'Negative']);
            }
        }

        if ($request->report_number === '5.2.002' ||
            $request->report_number === '5.2.003' ||
            $request->report_number === '5.2.004' ||
            $request->report_number === '5.2.005' ||
            $request->report_number === '5.2.006' ||
            $request->report_number === '5.2.007' ||
            $request->report_number === '6.2.002' ||
            $request->report_number === '6.2.003' ||
            $request->report_number === '6.2.004' ||
            $request->report_number === '6.2.005' ||
            $request->report_number === '6.2.006' ||
            $request->report_number === '6.2.007' ||
            $request->report_number === '6.2.008' ||
            $request->report_number === '6.2.012' ||
            $request->report_number === '6.2.013' ||
            $request->report_number === '6.2.014' ||
            $request->report_number === '6.2.015' ||
            $request->report_number === '6.2.016' ||
            $request->report_number === '6.2.017'
            // $request->report_number === '5.2.008' ||
            // $request->report_number === '5.2.009' 
        ) {
            if ($Llabel === 'Hate Speech') {
                $Llabel = 'HateSpeech';
            } else if ($Llabel === 'No Bully') {
                $Llabel = 'NoBully';
            } else if ($Llabel === 'Violence') {
                $Llabel = 'Violence';
            }

            $data->where('classifications.name', '=', $Llabel);
        }

        // if ($request->report_number === '6.2.002'
        //     // $request->report_number === '5.2.003' ||
        //     // $request->report_number === '5.2.004' ||
        //     // $request->report_number === '5.2.005' ||
        //     // $request->report_number === '5.2.006' ||
        //     // $request->report_number === '5.2.007' 
        //     // $request->report_number === '5.2.008' ||
        //     // $request->report_number === '5.2.009' 
        // ) {
        //     $data->where('classifications.name', $Llabel);
        //     // $data->where('classification_name', $Llabel);
        //     dd($data->get());
        // }

        // if ($request->report_number === '5.2.008' ||
        //     $request->report_number === '5.2.009' 
        // ) {
        //     $data->whereIn('classifications.classification_type_id', [1 ,2, 3]);

        //     return $this->classifacation_multiple($request, $data);
        // }

        return $data;
    }

    private function getClassificationName($message_id)
    {
        return DB::table('messages')
            ->select([
                'classifications.name AS classification_name',
                'classifications.classification_type_id AS classification_type_id'
            ])
            ->where('messages.message_id', $message_id)
            ->join('message_results', 'message_results.message_id', '=', 'messages.id')
            ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
            ->limit(3)
            ->get(['classifications.classification_type_id', 'classification_name']);
    }

    private function raw_account(Request $request, $campaign_id, $start_date, $end_date, $report_number)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;
        $keyword = Keyword::where('campaign_id', $campaign_id);

        if ($this->keyword_id) {
            $keyword = $keyword->whereIn('id', $this->keyword_id);
        }

        $keyword = $keyword->get();
        $keywordIds = $keyword->pluck('id')->all();

        $Llabel = str_replace("+", " ", $request->Llabel);
        $label = str_replace("+", " ", $request->label);

        $subquery = DB::table('messages')
                    ->select([
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
                        'keywords.campaign_id AS campaign_id',
                        'campaigns.name AS campaign_name',
                        'keywords.name AS keyword_name',
                        'classifications.classification_type_id',
                        'message_results.classification_id',
                        'classifications.color AS classification_color',
                        'sources.name AS source_name',
                        'messages.created_at AS created_at',
                        'classifications.name AS classification_name',
                        DB::raw('COALESCE(number_of_comments, 0) +
                            COALESCE(number_of_shares, 0) +
                            COALESCE(number_of_reactions, 0) AS total_engagement')
                    ])
                    ->join('keywords', 'messages.keyword_id', '=', 'keywords.id')
                    ->join('campaigns', 'keywords.campaign_id', '=', 'campaigns.id')
                    ->join('sources', 'messages.source_id', '=', 'sources.id')
                    ->join('message_results', 'message_results.message_id', '=', 'messages.id')
                    ->join('classifications', 'message_results.classification_id', '=', 'classifications.id')
                    ->whereIn('keyword_id', $keywordIds)
                    ->whereBetween('message_datetime', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                    ->groupBy('messages.author')
                    ->havingRaw('total_engagement > 0'); // Exclude rows with total_engagement = 0 if desired
                    // ->orderByDesc('total_engagement');
                    // ->get();
                
                // $count = $results->count();

                    // dd($subquery->get()->count());
        if ($request->sort && $request->field) {
            $field = $request->field;
            switch( $field ) {
                case 'message_type' : $field_table = "messages.message_type"; break;
                case 'author' : $field_table = "messages.author"; break;
                case 'date' : $field_table = "messages.message_datetime"; break;
                case 'device' : $field_table = "messages.device"; break;
                case 'source' : $field_table = "sources.name"; break;
                case 'engagement' : $field_table = "total_engagement"; break;
                case 'bully_type' : $field_table = "classifications.name"; break;
                case 'sentiment' : $field_table = "classifications.name"; break;
                case 'engagement' : $field_table = "classifications.name"; break;
                default : $field_table= "total_engagement"; break;
                 
            }
            
            $subquery->orderBy($field_table, $request->sort);

        } else {
            $subquery->orderByDesc('total_engagement');
        }
        $total = $subquery->count() ?? 0;
        $raw = $subquery->offset($start)->limit($limit)->get();

        foreach ($raw as $ke => $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');
            $types = $this->getClassificationName($item->message_id);
            $parent = null;
            if (!$item->reference_message_id || $item->reference_message_id === null || $item->reference_message_id === '') {
                $parent = $item->message_id;
            }


            $data_push = [
                "id" => $item->id,
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
        }

        $data['total'] = $total;
        return parent::handleRespond($data);
    }

    private function classifacation_multiple(Request $request, $campaign_id, $start_date, $end_date, $report_number) 
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);

        $raw = DB::select(DB::raw("SELECT
            tbl_messages.id as id,
            tbl_messages.message_id as message_id,
            tbl_messages.reference_message_id as reference_message_id,
            tbl_messages.keyword_id as keyword_id,
            tbl_messages.link_message as link_message,
            tbl_messages.message_datetime as date_m,
            tbl_messages.author as author,
            tbl_messages.source_id as source_id,
            tbl_messages.full_message as full_message,
            tbl_messages.message_type,
            tbl_messages.device as device,
            tbl_messages.number_of_views as number_of_views,
            tbl_messages.number_of_comments as number_of_comments,
            tbl_messages.number_of_shares as number_of_shares,
            tbl_messages.number_of_reactions as number_of_reactions,
            tbl_keywords.campaign_id AS campaign_id,
            tbl_campaigns.name AS campaign_name,
            tbl_keywords.name as keyword_name,
            tbl_message_results.classification_type_id,
            tbl_message_results.classification_id,
            tbl_classifications.name as classification_name,
            tbl_classifications.color as classification_color,
            tbl_messages.created_at as created_at,
            tbl_sources.name as source_name
        FROM
            tbl_messages
            JOIN tbl_keywords ON tbl_messages.keyword_id = tbl_keywords.id
            JOIN tbl_sources ON tbl_messages.source_id = tbl_sources.id
            JOIN tbl_campaigns ON tbl_keywords.campaign_id = tbl_campaigns.id
            JOIN tbl_message_results ON tbl_message_results.message_id = tbl_messages.id
            JOIN tbl_classifications ON tbl_message_results.classification_id = tbl_classifications.id
        WHERE 
            tbl_keywords.campaign_id = $campaign_id AND tbl_messages.message_datetime BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'"
        ));

        $parents = [];
        foreach ($raw as $item) {
            if ($item->reference_message_id) {
                if (array_search($item->reference_message_id, $parents) === false) {
                    $parents[] = $item->reference_message_id;
                }
            }
        }
        //todo
        $anylsys = [];

        foreach ($raw as $item) {
            // dd($item);
            $date_d = Carbon::parse($item->date_m)->format('D');
            $parent = null;
            if (array_search($item->message_id, $parents) !== false) {
                $parent = $item->message_id;
            }

            $anylsys[$item->message_id][$item->classification_type_id] = $item->classification_name;
            $anylsys[$item->message_id]["id"] = $item->id;
            $anylsys[$item->message_id]["message_id"] = $item->message_id;
            $anylsys[$item->message_id]["message_type"] = $item->message_type;
            $anylsys[$item->message_id]["message_detail"] = $item->full_message;
            $anylsys[$item->message_id]["account_name"] = $item->author;
            $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
            $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('H:i');
            $anylsys[$item->message_id]["day"] = $date_d;
            $anylsys[$item->message_id]["device"] = $item->device;
            $anylsys[$item->message_id]["source_id"] = $item->source_id;
            // $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
            // $anylsys[$item->message_id]["bully_type"] = $item->classification_id;
            $anylsys[$item->message_id]["channel"] = $item->source_name;
            $anylsys[$item->message_id]["link_message"] = $item->link_message;
            $anylsys[$item->message_id]["engagement"] = $item->number_of_comments + $item->number_of_shares + $item->number_of_reactions;
            $anylsys[$item->message_id]["parent"] = $parent;


        }

        foreach ($anylsys as $anylsy) {
            if ($report_number === '5.2.008') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];
    
                if ($anylsy[1] == $Llabel && $anylsy[3] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '5.2.009') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];
    
                if ($anylsy[1] == $Llabel && $anylsy[2] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '6.2.008') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];
    
                if ($anylsy[3] == $Llabel && $anylsy[1] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

            if ($report_number === '6.2.018') {
                $anylsy["bully_level"] = $anylsy[3];
                $anylsy["bully_type"] = $anylsy[2];
                $anylsy["sentiment"] = $anylsy[1];
    
                if ($anylsy[2] == $Llabel && $anylsy[1] == $label) {
                    $data['message'][] = $anylsy;
                }
            }

        }

        if ($data) {
            $data['total'] = count($data['message']);
            $offset = 9 + 1;

            $data['message'] = array_slice($data['message'], $start, $offset);
        }

       return parent::handleRespond($data);
    }

    public function deleteMessage(Request $request)
    {
        if ($request->id) {
            $originalMessage = Message::find($request->id);

            if ($originalMessage) {
                $newMessage = new MessageDeleteLog();
                $newMessage->id = $originalMessage->id;
                $newMessage->message_id = $originalMessage->message_id;
                $newMessage->reference_message_id = $originalMessage->reference_message_id;
                $newMessage->keyword_id = $originalMessage->keyword_id;
                $newMessage->message_datetime = $originalMessage->message_datetime;
                $newMessage->author = $originalMessage->author;
                $newMessage->source_id = $originalMessage->source_id;
                $newMessage->full_message = $originalMessage->full_message;
                $newMessage->link_message = $originalMessage->link_message;
                $newMessage->message_type = $originalMessage->message_type;
                $newMessage->device = $originalMessage->device;
                $newMessage->number_of_shares = $originalMessage->number_of_shares;
                $newMessage->number_of_comments = $originalMessage->number_of_comments;
                $newMessage->number_of_reactions = $originalMessage->number_of_reactions;
                $newMessage->number_of_views = $originalMessage->number_of_views;
    
                $newMessage->save();
                // remove data old table
                Message::destroy($originalMessage->id);

                return parent::handleRespond($newMessage);
            }

            return parent::handleRespond(null, null, 404, 'Message id not found');
        }

        return parent::handleRespond(null, null, 404, 'Plase send id of message');
    }
}
