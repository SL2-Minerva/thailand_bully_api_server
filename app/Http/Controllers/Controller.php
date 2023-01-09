<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BaseModel;
use App\Models\Sources;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DailyMessage;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $organization_id = 1;

    public function __construct(Request $request)
    {
//        $this->request = $request;
    }


    /**
     * @param $data
     * @param $options
     * @param int $status
     * @param string $msg
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleRespond($data = null, $options = null, int $status = 200, string $msg = BaseModel::SUCCESS_TEXT)
    {
        $default = [
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ];

        return response()->json($options ? array_merge($options, $default) : $default, $status);
    }

    /**
     * function for return only notfound or something ele not excust
     * @param $data
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleNotFound($data, int $status = 404): \Illuminate\Http\JsonResponse
    {
        $default = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($data) {
            $default = $data;
        }
        return response()->json($default, $status);
    }

    /**
     * @param $exception
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function handleErrorRespond($exception, $status = 500): \Illuminate\Http\JsonResponse
    {
        $default = [
            'status' => $status,
            'msg' => $exception->getMessage(),
            'data' => $exception->getTraceAsString()
        ];

        return response()->json($default, $status);
    }

    /**
     * @param $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return array
     */
    protected static function validate($request, array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->passes()) {
            return $request->all();
        }

        return [
            'invalid' => 'invalid',
            'msg' => $validator->errors()->all()
        ];
    }

    protected static function audi_log(
        $request = null,
        $transaction = 'CREATE',
        $primary_key = null,
        $user_id = null,
        $source = null,
        $original = null,
        $changed = null
    )
    {
        $datasubmit = [
            AuditLog::TRANSACTION => $transaction,
            AuditLog::PRIMARY_KEY => $primary_key,
            BaseModel::USER_ID => $user_id,
            BaseModel::SOURCE => $source,
            AuditLog::ORIGINAL => $original,
            AuditLog::CHANGED => $changed
        ];
        AuditLog::create($datasubmit);


    }

    public static function is_existed($id, $mode): bool
    {
        return (boolean)$mode::find($id);
    }

    public static function list($request, $model, $condition = null)
    {

        $page = $request->page ?? null;
        $limit = $request->limit ?? 5;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start - 1;

        return $model::offset($start)->limit($limit)->orderBy('created', 'desc');
    }

    public static function uploadImage($file)
    {
        if ($file) {
            return $file->store("organization-content", 'public');
        }
    }

    public static function date_carbon($date)
    {
        return Carbon::parse($date)->format('Y-m-d');
    }

    public static function get_previous_date($date, $period)
    {
        switch ($period) {
            case "daily":
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
                break;
            case "yesterday":
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
                break;
            case "last7Days":
                $date = Carbon::parse($date)->subDays(7)->format('Y-m-d');
                break;
            case "last30Days":
                $date = Carbon::parse($date)->subDays(30)->format('Y-m-d');
                break;
            case "thisMonth":
                $date = Carbon::parse($date)->subMonths(1)->format('Y-m-d');
                break;
            case "lastMonth":
                $date = Carbon::parse($date)->subMonths(1)->format('Y-m-d');
                break;
            case "customrange":
                $date = Carbon::parse($date)->format('Y-m-d');
                break;
            default:
                $date = Carbon::parse($date)->subDays(1)->format('Y-m-d');
        }

        return $date;
    }

    public static function diff_date($start_date, $end_date)
    {
        $start_date = Carbon::createFromFormat('Y-m-d H:s:i', $start_date . ' 00:00:00');
        $end_date = Carbon::createFromFormat('Y-m-d H:s:i', $end_date . ' 23:59:59');
        $length = $start_date->diffInDays($end_date);
        return $length != 0 ? $length : 1;
    }

    public static function point_two_digits($number)
    {
        return $number !== null ? number_format($number, 2) : null;
    }

    public static function messagesTable($campaign_id, $start_date, $end_date, $keyword_id, $table = null, $colum = null)
    {
        return DB::table($table ?? 'percentage_of_messages')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum($colum ?? 'total_at_keyword');
    }

    public static function channelTable($campaign_id, $start_date, $end_date, $source_id)
    {
        return DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('source_id', $source_id)
            ->sum('total_at_date');
    }

    public static function engagementTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        return DB::table('total_engagement_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('engagement');
    }

    public static function accountTable($campaign_id, $start_date, $end_date, $keyword_id)
    {
        return DB::table('total_account_of_campaign')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_account');
    }

    public static function shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $keyword_id, $source_id)
    {
        $total_message = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->where('source_id', $source_id)
            ->sum('total_at_date');

        return $total_message;
    }

    public static function shareOfVoiceByNumber($campaign_id, $start_date, $end_date, $keyword_id)
    {
        $total_account = DailyMessage::where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date])
            ->where('keyword_id', $keyword_id)
            ->sum('total_at_date');

        return $total_account;
    }


    protected static function findPercentage($items, $column, $start_date, $end_date)
    {
        $message_keyword = [];
        $message_total = 0;

        foreach ($items as $object) {
            $item = (array)$object;

            if (isset($message_keyword[$item['keyword_id']])) {
                $message_keyword[$item['keyword_id']] += $item[$column];
            } else {
                $message_keyword[$item['keyword_id']] = $item[$column];
            }

            $message_total += $item[$column];
        }

        $data = null;

        foreach ($message_keyword as $keyword_id => $value) {
            $data[$keyword_id]['value'][] = [
                'date' => Carbon::createFromFormat('Y-m-d', $start_date)->format('d/m/Y') . ' - ' . Carbon::createFromFormat('Y-m-d', $end_date)->format('d/m/Y'),

                'percentage' => self::point_two_digits(($value / $message_total) * 100),
            ];
        }

        return $data;
    }

    protected static function getDataByCondition(
        $table,
        $campaign_id,
        $start_date,
        $end_date,
        $keyword_id = null,
        $source_id = null,
        $column = null,
        $type = null, $condition = null)
    {


        $items = DB::table($table)
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }

        if ($keyword_id) {
            $items->where('keyword_id', $keyword_id);
        }


        if ($source_id && $source_id !== 'all') {
            $items->where('source_id', $source_id);
        }


        return self::factorListData($items->get(), $type, $campaign_id, $start_date, $end_date, $keyword_id, $table, $column, $condition);
    }

    protected static function factorListData($items, $type, $campaign_id = null, $start_date = null, $end_date = null, $keyword_id = null, $table = null, $column = null, $condition = null)
    {

        $data = null;

        if ($type === 'percentage') {
            $data = self::findPercentage($items, $column, $start_date, $end_date);
        }

        foreach ($items as $item) {
            $keyword_id = $item->keyword_id;

            if ($type !== 'engagement') {
                $data[$keyword_id]['keyword_id'] = $item->keyword_id;
                $data[$keyword_id]['keyword_name'] = $item->keyword_name;
                $data[$keyword_id]['campaign_id'] = $item->campaign_id;
                $data[$keyword_id]['campaign_name'] = $item->campaign_name;
            }

            if ($type !== 'engagement' && isset($item->source_id) && $item->source_id) {
                $data[$keyword_id]['source_id'] = $item->source_id;
                $data[$keyword_id]['source_name'] = $item->source_name;
            }

            if ($type === 'source' || $type === 'daily_message') {
                $nestData = [
                    'source_id' => $item->source_id,
                    'source_name' => $item->source_name,
                    'date_m' => $item->date_m,
                    'total_at_date' => $item->total_at_date
                ];

                $data[$keyword_id]['value'][] = $nestData;
            }

            if ($type === 'engagement') {

                $nestData['keyword_id'] = $item->keyword_id;
                $nestData['keyword_name'] = $item->keyword_name;
                $nestData['campaign_id'] = $item->campaign_id;
                $nestData['campaign_name'] = $item->campaign_name;


                if (isset($nestData["value"][$item->source_id])) {
                    $nestData["value"][$item->source_id][] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                } else {
                    $nestData["value"][$item->source_id] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                }


                if (isset($data[$item->keyword_id])) {
                    $data[$item->keyword_id]['value'][] = [
                        'source_id' => $item->source_id,
                        'source_name' => $item->source_name,
                        'date_m' => $item->date_m,
                        'total_at_date' => (int)$item->engagement
                    ];
                } else {
                    $data[$item->keyword_id] = $nestData;
                }
//
                $data[$item->keyword_id]['value'] = array_values($data[$item->keyword_id]['value']);

//                $data[$item->keyword_id][] = $nestData;
            }

            if ($type === 'shareofvoice') {
                $message = self::shareOfVoiceByPlatform($campaign_id, $start_date, $end_date, $item->keyword_id, $item->source_id);
                $total_message = DB::table($table)->where('campaign_id', $campaign_id)
                    ->where('keyword_id', $item->keyword_id)
                    ->where('organization_id', $item->organization_id)
                    ->where('campaign_name', $item->campaign_name)
                    ->whereBetween('date_m', [$start_date, $end_date])
                    ->sum($column);

                $percentage = ($message / $total_message) * 100;

                $push_data = [
                    'channel' => $item->source_name,
                    'percentage' => self::point_two_digits($percentage),
                    'number_of_message' => $message,
                    // 'highlight' =>
                ];

                $data[$keyword_id]['value'][] = $push_data;
            }

            if ($type === '') {

            }

        }


        if ($data) {
            return array_values($data);
        }

        return $data;

    }

    protected static function listDataByType($type, $table, $campaign_id, $start_date, $end_date, $keyword_id = null, $source_id = null, $column = null, $condition = null)
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

        $items = DB::table($table)
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date, $end_date]);

        if (isset($condition['group_by'])) {

            foreach ($condition['group_by'] as $groupBy) {
                $items->groupBy($groupBy);
            }
        }



        if ($keyword_id) {
            $items->where('keyword_id', $keyword_id);
        }

        $data['value'] = null;

        if ($type === 'time') {
            $data['labels'] = [
                "Before 6 AM",
                "6 AM-12 PM",
                "12 PM-6 PM",
                "After 6 PM"
            ];
        }

        if ($type === 'device') {
            $data['labels'] = [
                "Android",
                "Iphone",
                "Web App",
            ];
        }

        if ($type === 'channel') {

            $sources = Sources::all();
            $data['labels'] = [];

            foreach ($sources as $source) {
                $data['labels'][] = $source->name;
            }

        }

        foreach ($items->get() as $item) {

            if ($type === 'dayname' || $type === 'dayname_engagement') {
                $day_name = Carbon::parse($item->date_m)->format('D');
                $index_label = array_search($day_name, $data['labels']);


                if ($type === 'dayname') {

                    if (isset($data['value'][$item->keyword_id])) {

                        $data['value'][$item->keyword_id]['data']['shares'] += 1;


                    } else {

                        $data['value'][$item->keyword_id] = [
                            'id' => $item->keyword_id,
                            'keyword_name' => $item->keyword_name,
                            'campaign_id' => $item->campaign_id,
                            'campaign_name' => $item->campaign_name,
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ];
                        $data['value'][$item->keyword_id]['data'][$index_label] += 1;

                    }

                }


                if ($type === 'dayname_engagement') {

                    if (isset($data['value'][0])) {
                        $data['value'][0]['data'][$index_label] += $item->number_of_shares;
                        $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                        $data['value'][2]['data'][$index_label] += $item->number_of_reactions;

                    } else {
                        $data['value'][0] = [
                            'id' => 1,
                            'keyword_name' => 'Share',
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ];

                        $data['value'][1] = [
                            'id' => 2,
                            'keyword_name' => 'Comment',
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ];


                        $data['value'][2] = [
                            'id' => 3,
                            'keyword_name' => 'reactions',
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ];

                        $data['value'][0]['data'][$index_label] += $item->number_of_shares;
                        $data['value'][1]['data'][$index_label] += $item->number_of_comments;
                        $data['value'][2]['data'][$index_label] += $item->number_of_reactions;
                    }
                }

            }

            if ($type === 'time') {

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

                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                        'data' => [0, 0, 0, 0]
                    ];

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }

            if ($type === 'device') {
                $index_label = 0;

                if ($item->device == 'iphone') {
                    $index_label = 1;
                }

                if ($item->device == 'webapp') {
                    $index_label = 2;
                }

                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                        'data' => [0, 0, 0]
                    ];

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }

            if ($type === 'channel') {
                $index_label = 0;
                $index_label = array_search($item->source_name, $data['labels']);

                if (isset($data['value'][$item->keyword_id])) {
                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                } else {
                    $data['value'][$item->keyword_id] = [
                        'id' => $item->keyword_id,
                        'name' => $item->keyword_name,
                        'keyword_name' => $item->keyword_name,
                        'campaign_id' => $item->campaign_id,
                        'campaign_name' => $item->campaign_name,
                    ];

                    for ($i = 0; $i <= count($data['labels']); $i++) {
                        $data['value'][$item->keyword_id]['data'][$i] = 0;
                    }

                    $data['value'][$item->keyword_id]['data'][$index_label] += 1;
                }
            }



        }

        $data['value'] = array_values($data['value']);
        return $data;

    }

}


