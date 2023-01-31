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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public static function uploadImage($file, $path = '')
    {
        if ($file) {


            return $file->store($path ??  "organization-content", 'public');
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
            case "last7days":
                $date = Carbon::parse($date)->subDays(7)->format('Y-m-d');
                break;
            case "last30days":
                $date = Carbon::parse($date)->subDays(30)->format('Y-m-d');
                break;
            case "thismonth":
                $date = Carbon::parse($date)->subMonths(1)->format('Y-m-d');
                break;
            case "lastmonth":
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

    public static function listSource()
    {
        $sources = Sources::where('status', 1)->get();
        $data['labels'] = [];

        foreach ($sources as $source) {
            $data['labels'][] = $source->name;
        }

        return $data;
    }



}


