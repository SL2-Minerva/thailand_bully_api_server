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

    public function index()
    {

    }

    public function update(Request $request)
    {
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

    public function data(Request $request)
    {
        $users = User::where(BaseModel::STATUS, 2)->get();
        return parent::handleRespond($users);
    }

    public function list_active(Request $request)
    {
        $users = User::join('organizations', 'users.organization_id', '=', 'organizations.id')
            ->join('user_organization_groups', 'organizations.organization_group_id', '=', 'user_organization_groups.id')
            ->where('users.status', 1)
            ->orWhere('users.status', 0)
            ->where('organizations.status', 1)
            ->select('users.*', 'organizations.name as organization', 'user_organization_groups.organization_group_name as group')
            ->get();
//       $users = User::where(BaseModel::STATUS, 1)->get();
        return parent::handleRespond($users);
    }


    public function create()
    {

    }

    public function delete()
    {

    }

    public function info()
    {

        $user = auth('api')->user();

        if ($user) {
            $data['info'] = $user;
            $data['role'] = $user->is_admin ?? null;
            $data['permission'] = $user;
            $data['menu'] = ['all'];
            return parent::handleRespond($data);
        }

        return parent::handleNotFound($user);
    }
}
