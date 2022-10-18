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
//        Cam
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
            BaseModel::CREATED_BY => auth('api')->id() ?? 1,
            BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
        ];

        $campaign = Campaign::create($data_submit);

//        $dump = [
        
//            'keywords' => [
//                {
//                  'label' : 'asdada',
//                  'KEYWORD_OR' : 'dasdasdas' ,
//                  ....
//                },
//                 {
//             'label' : 'asdada',
//                  'KEYWORD_OR' : 'dasdasdas' ,
//                  ....
//                },
//            ]
//        ]


        if ($request->keywords) {
            foreach ($request->keywords as $keyword) {
                Keyword::create([
                    Keyword::CAMPAIGN_ID => $campaign->id,
                    Keyword::LABEL => $keyword[Keyword::LABEL] ?? '',
                    Keyword::KEYWORD_OR => $keyword[Keyword::KEYWORD_OR] ?? [],
                    Keyword::KEYWORD_AND => $keyword[Keyword::KEYWORD_AND] ?? [],
                    Keyword::KEYWORD_EXCLUDE => $keyword[Keyword::KEYWORD_EXCLUDE]?? [],
                    BaseModel::STATUS => 1,
                    BaseModel::CREATED_BY => auth('api')->id() ?? 1,
                    BaseModel::UPDATED_BY => auth('api')->id() ?? 1,
                ]);
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
