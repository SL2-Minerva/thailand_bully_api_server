<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Organization;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = Campaign::all();
        $data = [];
        foreach ($campaigns as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->get();

            if ($campaign->keyword) {
                foreach ($campaign->keyword as $item) {
                    $item->keyword_or = explode(",", $item->keyword_or);
                    $item->keyword_and = explode(",", $item->keyword_and);
                    $item->keyword_exclude = explode(",", $item->keyword_exclude);
                }
//                $item->keyword_or = explode(",",$item->keyword_or)();
//                $item->keyword_and = json_decode($item->keyword_and);
//                $item->keyword_exclude = json_decode($item->keyword_exclude);
            }
            $campaign->organization = Organization::find($campaign->organization_id)->name;
            $data[] = $campaigns;

        }
        return parent::handleRespond($campaigns);
    }

    public function show(Request $request)
    {
        $id = $request->id;

        $res = $this->find($id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function store(Request $request)
    {

        $data_submit = [
            BaseModel::NAME => $request->name ?? '',
            Campaign::DESCRIPTION => $request->description,
            BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
            Campaign::DOMAIN_ID => $request->domain_id ?? 1,
            BaseModel::STATUS => 1,
            Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
            Campaign::START_AT => $request->start_at,
            Campaign::END_AT => $request->end_at,
        ];

        $campaign = Campaign::create($data_submit);

        if ($request->keywords) {
            foreach ($request->keywords as $keyword) {


                $data_submit_keyword = [
                    Keyword::CAMPAIGN_ID => $campaign->id,
                    BaseModel::NAME => $keyword[BaseModel::NAME] ?? '',
                    Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                    Keyword::KEYWORD_AND => collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(','),
                    Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                    BaseModel::STATUS => 1,
                    BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                    BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                ];

                Keyword::create($data_submit_keyword);
            }
        }


        return parent::handleRespond($campaign);

    }

    public function update(Request $request)
    {
        $id = $request->id;

        try {
            $campaign= $this->find($id);

            if ($campaign[BaseModel::STATUS] !== 200) {


//                return
//                if ($action === BaseModel::UPDATE_TEXT) {
//                    $data->update($request->all());
//                    return parent::handleRespond($data);
//                }



                return parent::handleRespond($data_submit);
            }

            $data = $campaign[BaseModel::DATA_TEXT];

            $data_submit = [
                BaseModel::NAME => $request->name ?? '',
                Campaign::DESCRIPTION => $request->description,
                BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
                Campaign::DOMAIN_ID => $request->domain_id ?? 1,
                BaseModel::STATUS => 1,
                Campaign::EXCLUDE_CAMPAIGN => collect($request->exclude_campaign)->implode(','),
                Campaign::START_AT => $request->start_at,
                Campaign::END_AT => $request->end_at,
            ];

            $data->update($data_submit);


            if ($request->keywords) {
                foreach ($request->keywords as $keyword) {

                    $data_submit_keyword = [
                        Keyword::CAMPAIGN_ID => $data->id,
                        BaseModel::NAME => $keyword[BaseModel::NAME] ?? '',
                        Keyword::KEYWORD_OR => collect($keyword[Keyword::KEYWORD_OR] ?? [])->implode(','),
                        Keyword::KEYWORD_AND => collect($keyword[Keyword::KEYWORD_AND] ?? [])->implode(','),
                        Keyword::KEYWORD_EXCLUDE => collect($keyword[Keyword::KEYWORD_EXCLUDE] ?? [])->implode(','),
                        BaseModel::STATUS => 1,
                        BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                        BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                    ];

                    Keyword::updateOrCreate([ 'id' => $keyword['id'] ?? null], $data_submit_keyword);
                }
            }



            return parent::handleRespond($this->find($id));

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request)
    {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];
        $campaign = Campaign::find($id);
        if ($id && $campaign) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }
}
