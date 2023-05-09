<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->page ?? null;
        $limit = $request->limit ?? 10;
        $start = $page === null || $page === 1 ? null : $page * $limit;
        $start = $start === 1 ? null : $start;
        $data = [];

        $raw = ActivityLog::join('users', 'users.id', 'activity_log.request_by')
            ->select('activity_log.*', 'users.name as request_by_name')
            ->offset($start)->limit($limit)
            ->orderBy('activity_log.id', 'DESC');

        if ($request->search) {
            $raw->where('end_point', 'LIKE', "%$request->search%")
                ->orWhere('method', 'LIKE', "%$request->search%")
                ->orWhere('feature', 'LIKE', "%$request->search%")
                ->orWhere('users.name', 'LIKE', "%$request->search%");
        }

        if ($request->status_code) {
            $raw->where('status_code', $request->status_code);
        }

        $raw_total = $raw->count();
        
        $data['total'] = $raw_total;
        $data['activity_log'] = $raw->get();

        return parent::handleRespond($data);
        
    }
}
