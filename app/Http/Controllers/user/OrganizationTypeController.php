<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\UserOrganizationGroup;
use App\Models\UserOrganizationType;
use Illuminate\Http\Request;
use PHPUnit\Exception;

class OrganizationTypeController extends Controller
{


    public function store(Request $request)
    {
        try {
            $organization_group = UserOrganizationType::create($request->all());
            return parent::handleRespond($organization_group);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    private function find($id): array
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $organizationGroup = UserOrganizationType::find($id)) {
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
}
