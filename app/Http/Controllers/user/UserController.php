<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }


    public function index() {

    }

    public function update(Request $request) {
        dd($request->all());
        if (!$request->id) {
            return parent::handleNotFound($request->id);
        }
        $user = User::where('id', $request->id)->first();

        if ($user) {
            $user->update([BaseModel::STATUS => 1]);
            return parent::handleRespond($user);
        }

        return parent::handleNotFound($request->id);
    }

    public function data(Request $request) {
       $users = User::where(BaseModel::STATUS, 2)->get();
       return parent::handleRespond($users);
    }



    public function create() {

    }

    public function delete() {

    }

    public function info() {

        $user = auth('api')->user();

        if ($user) {
            $data['info'] = $user;
            $data['role'] = $user->is_admin ?? null;
            $data['permission'] = $user;
            $data['menu']= ['all'];
            return parent::handleRespond($data);
        }

        return parent::handleNotFound($user);
    }
}
