<?php

namespace App\Http\Controllers\main;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\Sources;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index() {
       $sources = Sources::all();
       return parent::handleRespond($sources);
    }

    public function data(Request $request) {
        $sources = Sources::all();
        return parent::handleRespond($sources);
    }

    public function update(Request $request)
    {
        $res = $this->find($request->id);

        if ($res['status'] !== 200) {
            return parent::handleNotFound($res, $res[BaseModel::STATUS]);
        }

        $organizationGroup = $res[BaseModel::DATA_TEXT];

        if ($request->image) {
            $file = $request->image;
            $path = parent::store($file, 'source');
            $data['image'] = $path;
        }

        if ($organizationGroup) {
            $data = $request->all();
            $organizationGroup->update($data);

            return parent::handleRespond($res);
        }



    }

    private function find($id): array
    {
        $res = [
            BaseModel::STATUS => 404,
            BaseModel::MSG_TEXT => BaseModel::NOT_FOUND_TEXT
        ];

        if ($id && $sources = Sources::find($id)) {
            $res[BaseModel::STATUS] = 200;
            $res[BaseModel::DATA_TEXT] = $sources;
            return $res;
        }
        return $res;
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if ($request->image) {
            $file = $request->image;
            $path = parent::store($file, 'source');
            $data['image'] = $path;
        }

        $sources = Sources::create($data);
        return parent::handleRespond($sources);
    }
}
