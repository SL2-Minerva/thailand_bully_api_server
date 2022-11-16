<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function index()
    {

    }

    public function update(User $user, Request $request)
    {
        $data = $request->all();
        $data[BaseModel::UPDATED_BY] =  auth('api')->id() ?? 1;
        $user->update($data);
        return parent::handleRespond($request->id);
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
            ->where('users.is_admin', '!=', 1)
            ->orWhere('users.status', 0)
            ->where('organizations.status', 1)
            ->select('users.*', 'organizations.name as organization', 'user_organization_groups.organization_group_name as group')
            ->get();
//       $users = User::where(BaseModel::STATUS, 1)->get();
        return parent::handleRespond($users);
    }


    public function create(Request $request)
    {
        $data = $request->all();
        $data['password'] = Hash::make('welcome');
        $data[BaseModel::CREATED_BY] =  auth('api')->id() ?? 1;
        $data[BaseModel::UPDATED_BY] =  auth('api')->id() ?? 1;
        $data['is_admin'] = 0;
        $user = User::create($data);
        return parent::handleRespond($user);
    }

    public function delete(User $user, Request $request)
    {
        $user->update([BaseModel::STATUS => 0]);
        return parent::handleRespond($user);
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
