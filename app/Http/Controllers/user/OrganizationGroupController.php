<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Http\Requests\organization\OrganizationGroupRequest;
use App\Models\BaseModel;
use App\Models\UserOrganizationGroup;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationGroupController extends Controller
{


    public function store(OrganizationGroupRequest $request)
    {
        try {
            $organization_group = UserOrganizationGroup::create($request->all());
            return parent::handleRespond($organization_group);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function update(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        $organizationGroup = $res[BaseModel::DATA_TEXT];

        if ($organizationGroup) {
            //todo dd check update
            $organizationGroup->update($request->all());
        }
    }

    private function find($id): array
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $organizationGroup = UserOrganizationGroup::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $organizationGroup;
            return $res;
        }
        return $res;
    }

    public function show(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        return parent::handleRespond($res);
    }

    public function destroy(Request $request)
    {
        return $this->update($request);
    }

}
