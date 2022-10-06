<?php

namespace App\Http\Controllers\permission;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Exception;

class RoleController extends Controller
{

    public function index(Request $request)
    {
        $data = parent::list($request, UserRole::class);
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
        $data = [
            BaseModel::ROLE_NAME => $request->name,
            BaseModel::ROLE_DESCRIPTION => $request->description,
            BaseModel::CREATED_BY => Auth::id() ?? 1,
            BaseModel::UPDATED_BY => Auth::id() ?? 1,
            BaseModel::AUTHORIZED_MENU => ['all']
        ];

        try {
            $role = UserRole::create($data);
            return parent::handleRespond($role);

        } catch (Exception $exception) {
            parent::handleErrorRespond($exception, $exception->getCode());
        }
    }

    public function destroy(Request $request) {

        
        return $this->update($request, BaseModel::UPDATE_TEXT);
    }
}
