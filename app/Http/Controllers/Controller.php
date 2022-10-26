<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BaseModel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Exception;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

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
}
