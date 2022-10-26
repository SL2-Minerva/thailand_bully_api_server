<?php

namespace App\Http\Controllers\permission;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Exception;

class RoleController extends Controller
{


    public function index(Request $request)
    {
        $data = UserRole::all();

        return parent::handleRespond($data);
    }

    public function update(Request $request, $action = null)
    {
        $id = $request->id;
        try {
            $res = $this->find($id);
            if ($res[BaseModel::STATUS] !== 200) {
                $data = $res[BaseModel::DATA_TEXT];

                if ($action === BaseModel::UPDATE_TEXT) {
                    $data->update($request->all());
                    return parent::handleRespond($data);
                }
                $data->update([BaseModel::STATUS => false]);
                return parent::handleRespond(null);
            }

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $data = UserRole::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $data;
            return $res;
        }
        return $res;
    }

    public function store(Request $request)
    {

        $user = auth('api')->user();
        $data = [
            BaseModel::ROLE_NAME => $request->role_name,
            BaseModel::ROLE_DESCRIPTION => $request->role_description,
            BaseModel::CREATED_BY => $user->id ?? 1,
            BaseModel::UPDATED_BY => $user->id ?? 1,
            BaseModel::AUTHORIZED_MENU => ['all']
        ];


        try {
            $role = UserRole::create($data);

            $permissions = $request->permission;
            if ($permissions) {
                foreach ($permissions as $key => $permission) {
                    $permission[BaseModel::STATUS] = 1;
                    $permission['role_id'] = $role->id ?? 2;
                    $permission[BaseModel::CREATED_BY] = $user->id;
                    $permission[BaseModel::UPDATED_BY] = $user->id;
                }
            }

            return parent::handleRespond($role);

        } catch (Exception $exception) {
            parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request)
    {


        return $this->update($request, BaseModel::UPDATE_TEXT);
    }
}
