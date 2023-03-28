<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageResult;
use App\Models\Organization;
use App\Models\UserOrganizationGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\OverAllExport;
use Maatwebsite\Excel\Facades\Excel;

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

    private function messageFullData($classification_type_id, $start_date, $end_date, $campaign_id)
    {
        $data = DB::table('message_result_full_data')
            ->where('campaign_id', $campaign_id)
            ->whereBetween('date_m', [$start_date. " 00:00:00", $end_date. " 23:59:59"])
            ->whereIn('classification_type_id', [$classification_type_id])
            ->orderBy('date_m', 'ASC')
            ->limit(2000);

        return $data;
    }

    public function exportOverAll()
    {
        $source = parent::listSource();
        $report = $this->messageFullData(1 ,$this->start_date, $this->end_date, $this->campaign_id);
        return Excel::download(new OverAllExport($report), 'Overall-'. Carbon::now() .'.xlsx');

    }


}


