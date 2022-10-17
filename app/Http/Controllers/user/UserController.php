<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function index() {

    }

    public function create() {

    }

    public function update() {

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
