<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Domain;
use Illuminate\Http\Request;
use PHPUnit\Util\Exception;

class DomainController extends Controller
{
    public function index (Request $request) {
//        Cam
    }

    public function show(Request $request) {
        $id = $request->id;

        $res = $this->find($id);
        if ($res[BaseModel::STATUS] !== 200) return parent::handleNotFound($res, $res[BaseModel::STATUS]);

        return parent::handleRespond($res);
    }

    public function store() {

    }

    public function update(Request $request, $action = null) {
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

    public function destroy(Request $request) {
        return $this->update($request, BaseModel::DELETE_TEXT);
    }

    private function find($id)
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $campaign = Domain::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $campaign;
            return $res;
        }
        return $res;
    }
}
