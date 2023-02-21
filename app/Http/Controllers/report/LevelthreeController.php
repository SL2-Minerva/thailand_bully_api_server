<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LevelthreeController extends Controller
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

    public function dailyMessageLevelThree(Request $request)
    {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = null;

        $label = str_replace("+", " ", $request->label);
        $Llabel = str_replace("+", " ", $request->Llabel);
//        dd($this->start_date, $this->end_date, $this->keyword_id, $this->source_id);

        if ($request->report_number === '6.2.003' ||
            $request->report_number === '6.2.004' ||
            $request->report_number === '6.2.005' ||
            $request->report_number === '6.2.006' ||
            $request->report_number === '6.2.007'
        ) {
            $total = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [3]);

            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [3])
                ->offset($start)->limit($limit);
        } else {

            $total = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [1]);

            $raw = DB::table('message_result_full_data')
                ->where('campaign_id', $this->campaign_id)
                ->whereBetween('date_m', [$this->start_date, $this->end_date])
                ->whereIn('classification_type_id', [1])
                ->offset($start)->limit($limit);
        }


        if ($request->report_number) {
            //fillter by Day name
            if ($request->report_number === '2.2.003' ||
                $request->report_number === '3.2.003' ||
                $request->report_number === '4.2.003' ||
                $request->report_number === '4.2.013' ||
                $request->report_number === '6.2.003' ||
                $request->report_number === '5.2.003'
            ) {

                $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);
                $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);

                if (isset($request->Llabel)) {
                    if ($request->report_number === '5.2.003' || $request->report_number === '6.2.003') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '3.2.003') {
                        $raw->where('source_name', $request->Llabel);
                        $total->where('source_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.013') {
                        if ($request->Llabel === "share") {
                            $raw->where('number_of_shares', '>', 0);
                            $total->where('number_of_shares', '>', 0);
                        }

                        if ($request->Llabel === "comment") {
                            $raw->where('number_of_comments', '>', 0);
                            $total->where('number_of_comments', '>', 0);
                        }

                        if ($request->Llabel === "reactions") {
                            $raw->where('number_of_reactions', '>', 0);
                            $total->where('number_of_reactions', '>', 0);
                        }
                    }
                }
                //$raw->where(DB::raw("DATE_FORMAT(date_m, '%a') = '$request->label'"));
            }

            // fillter by date
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

//                if ($request->report_number === '3.2.002') {
//                    $date_request = Carbon::parse($request->label)->format('Y-d-m');
//                }


                if ($request->report_number === '6.2.002' || $request->report_number === '6.2.012') {
                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                        ->whereIn('classification_type_id', [3])
                        ->offset($start)->limit($limit);


                    $total = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                        ->whereIn('classification_type_id', [3]);
                } else {

                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                        ->whereIn('classification_type_id', [1])
                        ->offset($start)->limit($limit);


                    $total = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$date_request . " 00:00:00", $date_request . " 23:59:59"])
                        ->whereIn('classification_type_id', [1]);
                }


                if ($request->report_number === '2.2.013') {
                    $total->where('message_type', 'Post');
                    $raw->where('message_type', 'Post');
                }

                if (isset($Llabel)) {
                    if ($request->report_number === '5.2.002') {
                        $raw->where('classification_name', $Llabel);
                        $total->where('classification_name', $Llabel);
                    }
                }


            }


            // fillter by time before ...
            if ($request->report_number === '2.2.004' ||
                $request->report_number === '3.2.004' ||
                $request->report_number === '4.2.004' ||
                $request->report_number === '4.2.014' ||
                $request->report_number === '5.2.004' ||
                $request->report_number === '6.2.004'
            ) {

                if ($label === 'Before 6 AM') {

                    $raw->whereRaw('HOUR(date_m) < ?', [6]);
                    $total->whereRaw('HOUR(date_m) < ?', [6]);

                }

                if ($label === '6 AM-12 PM') {
                    $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
                    $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
                }

                if ($label === '12 PM-6 PM') {
                    $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
                    $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
                }

                if ($label === 'After 6 PM') {
                    $raw->whereRaw('HOUR(date_m) >= ?', [18]);
                    $total->whereRaw('HOUR(date_m) >= ?', [18]);
                }

                if (isset($request->Llabel)) {
                    if ($request->report_number === '5.2.004') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '6.2.004') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '3.2.004') {
                        $raw->where('source_name', $request->Llabel);
                        $total->where('source_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.014') {
                        if ($request->Llabel === "share") {
                            $raw->where('number_of_shares', '>', 0);
                            $total->where('number_of_shares', '>', 0);
                        }

                        if ($request->Llabel === "comment") {
                            $raw->where('number_of_comments', '>', 0);
                            $total->where('number_of_comments', '>', 0);
                        }

                        if ($request->Llabel === "reactions") {
                            $raw->where('number_of_reactions', '>', 0);
                            $total->where('number_of_reactions', '>', 0);
                        }
                    }
                }
            }

            if ($request->report_number === '2.2.005' ||
                $request->report_number === '3.2.005' ||
                $request->report_number === '4.2.005' ||
                $request->report_number === '4.2.015' ||
                $request->report_number === '5.2.005' ||
                $request->report_number === '6.2.005'

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

                if (isset($request->Llabel)) {
                    if ($request->report_number === '5.2.005' || $request->report_number === '6.2.005') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '3.2.005') {
                        $raw->where('source_name', $request->Llabel);
                        $total->where('source_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.015') {

                        if ($request->Llabel === "share") {
                            $raw->where('number_of_shares', '>', 0);
                            $total->where('number_of_shares', '>', 0);
                        }

                        if ($request->Llabel === "comment") {
                            $raw->where('number_of_comments', '>', 0);
                            $total->where('number_of_comments', '>', 0);
                        }

                        if ($request->Llabel === "reactions") {
                            $raw->where('number_of_reactions', '>', 0);
                            $total->where('number_of_reactions', '>', 0);
                        }

                    }

                }

                $raw->where('device', $target);
                $total->where('device', $target);

            }

            // post owner / follower
            if ($request->report_number === '2.2.006' ||
                $request->report_number === '4.2.006' ||
                $request->report_number === '3.2.006' ||
                $request->report_number === '4.2.016' ||
                $request->report_number === '5.2.006' ||
                $request->report_number === '6.2.006'
            ) {

                if ($request->report_number === '2.2.006') {


                    if ($request->label === 'Post Owner') {
                        $raw->where('reference_message_id', '');
                        $total->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', '');
                        $total->where('reference_message_id', '!=', '');
                    }

                }

                if ($request->report_number === '3.2.006') {
                    if ($label === 'Influencer') {
                        $raw->where('reference_message_id', '')
                            ->orWhere('reference_message_id', null);

                        $total->where('reference_message_id', '')
                            ->orWhere('reference_message_id', null);
                    } else {
                        $raw->where('reference_message_id', '!=', null);
                        $total->where('reference_message_id', '!=', null);
                    }
                }

                if ($request->report_number === '4.2.006') {
                    if ($label === 'Influencer') {
                        $raw->where('reference_message_id', '');

                        $total->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', '');
                        $total->where('reference_message_id', '!=', '');
                    }
                }

                if ($request->report_number === '5.2.006') {
                    if ($label === 'Influencer') {
                        $raw->where('reference_message_id', '');

                        $total->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', null);
                        $total->where('reference_message_id', '!=', null);
                    }
                }

                if ($request->report_number === '6.2.006') {
                    if ($label === 'Influencer') {
                        $raw->where('reference_message_id', '');
                        $total->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', null);
                        $total->where('reference_message_id', '!=', null);;
                    }
                }


                if (isset($request->Llabel)) {

                    if ($request->report_number === '5.2.006') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '6.2.006') {
                        $raw->where('classification_name', '=', $request->Llabel);
                        $total->where('classification_name', '=', $request->Llabel);
                    }


                    if ($request->report_number === '3.2.006') {
                        $raw->where('source_name', $request->Llabel);
                        $total->where('source_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.006') {
                        $raw->where('keyword_name', $request->Llabel);
                        $total->where('keyword_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.016') {

                        if ($request->Llabel === "Share") {
                            $raw->where('number_of_shares', '>', 0);
                            $total->where('number_of_shares', '>', 0);
                        }

                        if ($request->Llabel === "Comment") {
                            $raw->where('number_of_comments', '>', 0);
                            $total->where('number_of_comments', '>', 0);
                        }

                        if ($request->Llabel === "Reaction") {
                            $raw->where('number_of_reactions', '>', 0);
                            $total->where('number_of_reactions', '>', 0);
                        }

                    }
                }


            }

            // source name
            if ($request->report_number === '2.2.007' ||
                $request->report_number === '4.2.007' ||
                $request->report_number === '4.2.017' ||
                $request->report_number === '5.2.007' ||
                $request->report_number === '6.2.007'
            ) {
                $raw->where('source_name', $request->label);
                $total->where('source_name', $request->label);

                if (isset($request->Llabel)) {

                    if ($request->report_number === '5.2.007' || $request->report_number === '6.2.007') {
                        $raw->where('classification_name', $request->Llabel);
                        $total->where('classification_name', $request->Llabel);
                    }

                    if ($request->report_number === '4.2.017') {

                        if ($request->Llabel === "Share") {
                            $raw->where('number_of_shares', '>', 0);
                            $total->where('number_of_shares', '>', 0);
                        }

                        if ($request->Llabel === "Comment") {
                            $raw->where('number_of_comments', '>', 0);
                            $total->where('number_of_comments', '>', 0);
                        }

                        if ($request->Llabel === "Reaction") {
                            $raw->where('number_of_reactions', '>', 0);
                            $total->where('number_of_reactions', '>', 0);
                        }

                    }
                }
            }

            // Reaction
            if ($request->report_number === '4.2.008') {
                if ($request->Llabel === "Share") {
                    $raw->where('number_of_shares', '>', 0);
                    $total->where('number_of_shares', '>', 0);
                }

                if ($request->Llabel === "Comment") {
                    $raw->where('number_of_comments', '>', 0);
                    $total->where('number_of_comments', '>', 0);
                }

                if ($request->Llabel === "Reaction") {
                    $raw->where('number_of_reactions', '>', 0);
                    $total->where('number_of_reactions', '>', 0);
                }
                // $total->where('source_name', $request->label);
            }

            // position
            if ($request->report_number === '2.2.008') {
                $total->where('classification_name', $request->label);
                $raw->where('classification_name', $request->label);
            }

            // level 2
            if ($request->report_number === '3.2.007') {
                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [1])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [1]);
            }

            // level 3
            if ($request->report_number === '2.2.009') {
                // $label = str_replace("+", " ", $request->label);

                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [3])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [3]);

            }


            if ($request->report_number === '2.2.010' || $request->report_number === '3.2.008') {

                if ($label === 'Hate Speech') {
                    $label = 'HateSpeech';
                } else if ($label === 'No Bully') {
                    $label = 'NoBully';
                } else if ($label === 'Trolling/Flaming') {
                    $label = 'Trolling';
                }

                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [2])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [2]);

                if ($request->report_number === '3.2.008') {
                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->where('classification_name', $label)
                        ->whereIn('classification_type_id', [3])
                        ->offset($start)->limit($limit);

                    $total = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->where('classification_name', $label)
                        ->whereIn('classification_type_id', [3]);
                }


            }

            if ($request->report_number === '1.2.02') {
                $author_name = urldecode($request->author_name);

                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->where('author', $author_name)
//                    ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                    ->whereIn('classification_type_id', [1])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->where('author', $author_name)
//                    ->whereBetween('date_m', [$this->start_date . " 00:00:00", $this->end_date . " 23:59:59"])
                    ->whereIn('classification_type_id', [1]);

            }

            // day-and-time
            if ($request->report_number === '2.2.016') {

                $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->ylabel]);
                $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->ylabel]);

                $raw->whereRaw('HOUR(date_m) = ?', [$request->label]);
                $total->whereRaw('HOUR(date_m) = ?', [$request->label]);

            }

            if ($request->report_number === '2.2.017') {

                $total->where('classification_name', $request->ylabel);
                $raw->where('classification_name', $request->ylabel);

                if (parent::checkLabel($request->label)) {

                    $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);
                    $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);
                } else {

                    $raw->whereRaw('HOUR(date_m) = ?', [$request->label]);
                    $total->whereRaw('HOUR(date_m) = ?', [$request->label]);
                }
            }

            if ($request->report_number === '2.2.018') {

                $label = str_replace("%20", " ", $request->ylabel);
                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [3])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [3]);

                if (parent::checkLabel($request->label) || parent::checkLabel($request->label) === 0) {

                    $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);
                    $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);

                } else {

                    $raw->whereRaw('HOUR(date_m) = ?', [$request->label]);
                    $total->whereRaw('HOUR(date_m) = ?', [$request->label]);

                }

            }

            if ($request->report_number === '2.2.019' || $request->report_number === '3.2.009') {
                $label = $request->ylabel;
                if ($label === 'Hate Speech') {
                    $label = 'HateSpeech';
                } else if ($label === 'No Bully') {
                    $label = 'NoBully';
                } else if ($label === 'Trolling/Flaming') {
                    $label = 'Trolling';
                }

                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [2])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->where('classification_name', $label)
                    ->whereIn('classification_type_id', [2]);

                // if (parent::checkLabel($label) || parent::checkLabel($label) === 0) {

                //     $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$label]);
                //     $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$label]);

                // } else {

                //     $raw->whereRaw('HOUR(date_m) = ?', [$label]);
                //     $total->whereRaw('HOUR(date_m) = ?', [$label]);

                // }

            }

            if ($request->report_number === '3.2.014') {
                if ($request->select_period === 'previous') {
                    $start_date = $this->start_date_previous;
                    $end_date = $this->end_date_previous;
                } else {
                    $start_date = $this->start_date;
                    $end_date = $this->end_date;
                }
                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$start_date, $end_date])
                    ->where('source_name', $request->label)
                    ->whereIn('classification_type_id', [1])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$start_date, $end_date])
                    ->where('source_name', $request->label)
                    ->whereIn('classification_type_id', [1]);

            }

            if ($request->report_number === '5.2.008' ||
                $request->report_number === '5.2.009' ||
                $request->report_number === '6.2.008' ||
                $request->report_number === '6.2.018'
            ) {
                if ($request->report_number === '5.2.008') {

                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->whereIn('classification_type_id', [1, 3]);

                    $items = $raw->get();


                    $anylsys = [];

                    foreach ($items as $item) {
                        $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
                        $anylsys[$item->message_id]["message_id"] = $item->message_id;
                        $anylsys[$item->message_id]["message_detail"] = $item->full_message;
                        $anylsys[$item->message_id]["account_name"] = $item->author;
                        $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
                        $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('h:i');
                        $anylsys[$item->message_id]["day"] = Carbon::parse($item->date_m)->diffInDays(Carbon::now());
                        $anylsys[$item->message_id]["device"] = $item->device;
                        $anylsys[$item->message_id]["source_id"] = $item->source_id;
                        $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
                        $anylsys[$item->message_id]["bully_type"] = $item->classification_type_name;
                    }


                    foreach ($anylsys as $anylsy) {
                        if ($anylsy['Bully Level'] === $label) {
                            if ($anylsy['Sentiment'] === $Llabel) {
                                $data['message'][] = $anylsy;
                            }
                        }

                    }

                    if ($data) {
                        $data['total'] = count($data['message']);

                        $page = $page < 1 ? 1 : $page;
                        $start = ($page - 1) * (9 + 1);
                        $offset = 9 + 1;

                        $data['message'] = array_slice($data['message'], $start, $offset);
                        return parent::handleRespond($data);
                    }


                    return $data;
                }

                if ($request->report_number === '5.2.009') {

                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->whereIn('classification_type_id', [1, 2]);

                    $items = $raw->get();


                    $anylsys = [];

                    foreach ($items as $item) {

                        $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
                        $anylsys[$item->message_id]["message_id"] = $item->message_id;
                        $anylsys[$item->message_id]["message_detail"] = $item->full_message;
                        $anylsys[$item->message_id]["account_name"] = $item->author;
                        $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
                        $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('h:i');
                        $anylsys[$item->message_id]["day"] = Carbon::parse($item->date_m)->diffInDays(Carbon::now());
                        $anylsys[$item->message_id]["device"] = $item->device;
                        $anylsys[$item->message_id]["source_id"] = $item->source_id;
                        $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
                        $anylsys[$item->message_id]["bully_type"] = $item->classification_type_name;
                    }


                    foreach ($anylsys as $anylsy) {
                        if ($label === 'Hate Speech') {
                            $label = 'HateSpeech';
                        } else if ($label === 'No Bully') {
                            $label = 'NoBully';
                        } else if ($label === 'Trolling/Flaming') {
                            $label = 'Trolling';
                        }
                        if ($anylsy['Bully Type'] === $label) {
                            if ($anylsy['Sentiment'] === $Llabel) {
                                $data['message'][] = $anylsy;
                            }
                        }

                    }

                    if ($data) {
                        $data['total'] = count($data['message']);

                        $page = $page < 1 ? 1 : $page;
                        $start = ($page - 1) * (9 + 1);
                        $offset = 9 + 1;

                        $data['message'] = array_slice($data['message'], $start, $offset);
                        return parent::handleRespond($data);
                    }


                    return $data;
                }

                if ($request->report_number === '6.2.008') {

                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->whereIn('classification_type_id', [1, 3]);

                    $items = $raw->get();


                    $anylsys = [];

                    foreach ($items as $item) {

                        $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
                        $anylsys[$item->message_id]["message_id"] = $item->message_id;
                        $anylsys[$item->message_id]["message_detail"] = $item->full_message;
                        $anylsys[$item->message_id]["account_name"] = $item->author;
                        $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
                        $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('h:i');
                        $anylsys[$item->message_id]["day"] = Carbon::parse($item->date_m)->diffInDays(Carbon::now());
                        $anylsys[$item->message_id]["device"] = $item->device;
                        $anylsys[$item->message_id]["source_id"] = $item->source_id;
                        $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
                        $anylsys[$item->message_id]["bully_type"] = $item->classification_type_name;
                    }


                    foreach ($anylsys as $anylsy) {
                        if ($anylsy['Bully Level'] === $Llabel) {
                            if ($anylsy['Sentiment'] === $label) {
                                $data['message'][] = $anylsy;
                            }
                        }

                    }

                    if ($data) {
                        $data['total'] = count($data['message']);

                        $page = $page < 1 ? 1 : $page;
                        $start = ($page - 1) * (9 + 1);
                        $offset = 9 + 1;

                        $data['message'] = array_slice($data['message'], $start, $offset);
                        return parent::handleRespond($data);
                    }


                    return $data;
                }

                if ($request->report_number === '6.2.018') {

                    $raw = DB::table('message_result_full_data')
                        ->where('campaign_id', $this->campaign_id)
                        ->whereBetween('date_m', [$this->start_date, $this->end_date])
                        ->whereIn('classification_type_id', [1, 2]);

                    $items = $raw->get();


                    $anylsys = [];

                    foreach ($items as $item) {

                        $anylsys[$item->message_id][$item->classification_type_name] = $item->classification_name;
                        $anylsys[$item->message_id]["message_id"] = $item->message_id;
                        $anylsys[$item->message_id]["message_detail"] = $item->full_message;
                        $anylsys[$item->message_id]["account_name"] = $item->author;
                        $anylsys[$item->message_id]["post_date"] = Carbon::parse($item->date_m)->format('Y/m/d');
                        $anylsys[$item->message_id]["post_time"] = Carbon::parse($item->date_m)->format('h:i');
                        $anylsys[$item->message_id]["day"] = Carbon::parse($item->date_m)->diffInDays(Carbon::now());
                        $anylsys[$item->message_id]["device"] = $item->device;
                        $anylsys[$item->message_id]["source_id"] = $item->source_id;
                        $anylsys[$item->message_id]["bully_level"] = $item->classification_name;
                        $anylsys[$item->message_id]["bully_type"] = $item->classification_type_name;
                    }


                    foreach ($anylsys as $anylsy) {
                        if ($Llabel === 'Hate Speech') {
                            $Llabel = 'HateSpeech';
                        } else if ($Llabel === 'No Bully') {
                            $Llabel = 'NoBully';
                        } else if ($Llabel === 'Trolling/Flaming') {
                            $Llabel = 'Trolling';
                        }
                        if ($anylsy['Bully Type'] === $Llabel) {
                            if ($anylsy['Sentiment'] === $label) {
                                $data['message'][] = $anylsy;
                            }
                        }

                    }

                    if ($data) {
                        $data['total'] = count($data['message']);

                        $page = $page < 1 ? 1 : $page;
                        $start = ($page - 1) * (9 + 1);
                        $offset = 9 + 1;

                        $data['message'] = array_slice($data['message'], $start, $offset);
                        return parent::handleRespond($data);
                    }


                    return $data;
                }

            }

            if ($request->report_number === '6.2.013' ||
                $request->report_number === '6.2.014' ||
                $request->report_number === '6.2.015' ||
                $request->report_number === '6.2.016' ||
                $request->report_number === '6.2.017' ||
                $request->report_number === '6.2.018'
            ) {

                $raw = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->whereIn('classification_type_id', [2])
                    ->offset($start)->limit($limit);

                $total = DB::table('message_result_full_data')
                    ->where('campaign_id', $this->campaign_id)
                    ->whereBetween('date_m', [$this->start_date, $this->end_date])
                    ->whereIn('classification_type_id', [2]);

                if ($Llabel === 'Hate Speech') {
                    $Llabel = 'HateSpeech';
                } else if ($Llabel === 'No Bully') {
                    $Llabel = 'NoBully';
                } else if ($Llabel === 'Trolling/Flaming') {
                    $Llabel = 'Trolling';
                }

                if ($request->report_number === '6.2.013') {

                    $raw->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);
                    $total->whereRaw('DATE_FORMAT(date_m, "%a") = ?', [$request->label]);

                } else if ($request->report_number === '6.2.014') {
                    if ($label === 'Before 6 AM') {

                        $raw->whereRaw('HOUR(date_m) < ?', [6]);
                        $total->whereRaw('HOUR(date_m) < ?', [6]);

                    }

                    if ($label === '6 AM-12 PM') {
                        $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
                        $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [6, 12]);
                    }

                    if ($label === '12 PM-6 PM') {
                        $raw->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
                        $total->whereRaw('HOUR(date_m) >= ? AND HOUR(date_m) < ?', [12, 18]);
                    }

                    if ($label === 'After 6 PM') {
                        $raw->whereRaw('HOUR(date_m) >= ?', [18]);
                        $total->whereRaw('HOUR(date_m) >= ?', [18]);
                    }
                } else if ($request->report_number === '6.2.015') {
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
                    $total->where('device', $target);
                } else if ($request->report_number === '6.2.016') {
                    if ($label === 'Influencer') {
                        $raw->where('reference_message_id', '');
                        $total->where('reference_message_id', '');
                    } else {
                        $raw->where('reference_message_id', '!=', null);
                        $total->where('reference_message_id', '!=', null);;
                    }
                } else if ($request->report_number === '6.2.017') {
                    $raw->where('source_name', $request->label);
                    $total->where('source_name', $request->label);
                }

                $raw->where('classification_name', $Llabel);
                $total->where('classification_name', $Llabel);

            }

        }

        if ($this->source_id) {
            $raw->where('source_id', $this->source_id);
            $total->where('source_id', $this->source_id);
        }

        if ($this->keyword_id) {
            $raw->whereIn('keyword_id', $this->keyword_id);
            $total->whereIn('keyword_id', $this->keyword_id);
        }

        if (isset($request->keyword_id)) {
            if ($request->report_number === '1.2.002' ||
                $request->report_number === '2.2.002' ||
                $request->report_number === '2.2.003' ||
                $request->report_number === '2.2.004' ||
                $request->report_number === '2.2.005' ||
                $request->report_number === '2.2.006' ||
                $request->report_number === '2.2.007' ||
                $request->report_number === '2.2.008' ||
                $request->report_number === '2.2.009' ||
                $request->report_number === '2.2.010' ||
                $request->report_number === '2.2.013'
            ) {
                $raw->where('keyword_id', $request->keyword_id);
                $total->where('keyword_id', $request->keyword_id);
            }
        }

//        dd($raw->toSql());

        $items = $raw->get();

        foreach ($items as $item) {
            $date_d = Carbon::parse($item->date_m)->format('D');


            $data_push = [
                "message_id" => $item->message_id,
                "message_detail" => $item->full_message,
                "account_name" => $item->author,
                "post_date" => Carbon::parse($item->date_m)->format('Y/m/d'),
                "post_time" => Carbon::parse($item->date_m)->format('h:i'),
                "day" => Carbon::parse($item->date_m)->diffInDays(Carbon::now()),
                "device" => $item->device,
                "channel" => $item->source_id,
                "bully_level" => $item->classification_name,
                "bully_type" => $item->message_type,
                "parent" => $item->reference_message_id ?? ''
            ];

            $data['message'][] = $data_push;
        }

        $data['total'] = $total->get()->count();

        return parent::handleRespond($data);
    }
}
