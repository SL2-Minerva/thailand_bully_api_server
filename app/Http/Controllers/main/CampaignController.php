<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Keyword;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = Campaign::all();
        $data = [];
        foreach ($campaigns as $campaign) {
            $campaign->keyword = Keyword::where('campaign_id', $campaign->id)->get();
            $campaign->organization = 'test';
            $data[] = $campaigns;

        }
        return parent::handleRespond($data);
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

            BaseModel::ORGANIZATION_ID => $request->organization_id ?? 1,
            Campaign::DOMAIN_ID => $request->domain_id ?? 1,
            BaseModel::STATUS => 1,

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

               return  Keyword::create($data_submit_keyword);
            }
        }


        parent::handleRespond($campaign);

    }

    public function update(Request $request, $action = null)
    {
        $id = $request->id;
        try {
            $res = $this->find($id);
            if ($res[BaseModel::STATUS] !== 200) {
                $permission = $res[BaseModel::DATA_TEXT];

                if ($action === BaseModel::UPDATE_TEXT) {
                    $permission->update($request->all());
                    return parent::handleRespond($permission);
                }
                $permission->update([BaseModel::STATUS => false]);
                return parent::handleRespond(null);
            }

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

        if ($id && $campaign = Campaign::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }
}
