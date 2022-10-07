<?php

namespace App\Http\Controllers\permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\role_permission\PermissionCreateRequest;
use App\Http\Requests\role_permission\PermissionDeleteRequest;
use App\Http\Requests\role_permission\PermissionUpdateRequest;
use App\Models\BaseModel;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Mockery\Exception;

class PermissionController extends Controller
{

    public function store(PermissionCreateRequest $request)
    {


        $role_id = $request->role_id;
        if (!parent::is_existed($role_id, UserRole::class)) return parent::handleNotFound();

        $data = [
            BaseModel::ROLE_ID => $request->role_id,
            UserPermission::AUTHORIZED_CREATE => (boolean)$request->can_create,
            UserPermission::AUTHORIZED_EDIT => (boolean)$request->can_edit,
            UserPermission::AUTHORIZED_DELETE => (boolean)$request->can_delete,
            UserPermission::AUTHORIZED_VIEW => (boolean)$request->can_view,
            UserPermission::AUTHORIZED_EXPORT => (boolean)$request->can_export,
        ];


        try {
            $permission = UserPermission::create($data);
            return parent::handleRespond($permission);

        } catch (Exception $exception) {
            return parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function update(PermissionUpdateRequest $request, $action = null)
    {
        $permission_id = $request->id;
        try {
            $res = $this->find($permission_id);
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

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $permision = UserPermission::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $permision;
            return $res;
        }
        return $res;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $permission_id = $request->id;

        $res = $this->find($permission_id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function destroy(PermissionDeleteRequest $request)
    {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }
}
