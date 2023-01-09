<?php

namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserRole;
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
        $permissions = null;

        if ($user->role_id) {

            $row_permissions = UserPermission::where('role_id', $user->is_admin ? 0 : $user->role_id)->get([
                'authorized_create', 'authorized_view', 'authorized_edit', 'authorized_delete', 'authorized_export', 'menu', 'id'
            ]);

            foreach ($row_permissions as $permission) {
                $permissions[$permission->menu] = [
                    'authorized_create' => $permission->authorized_create,
                    'authorized_view' => $permission->authorized_view,
                    'authorized_edit' => $permission->authorized_edit,
                    'authorized_delete' => $permission->authorized_delete,
                    'authorized_export' => $permission->authorized_export,
                    'id' => $permission->id
                ];
            }
        }



        if ($user) {
            $data['info'] = $user;
            $data['role_description'] = 'ssss';
            $data['role_name'] = $user->is_admin ?? null;
            $data['permission'] = $permissions;
            $data['menu'] = ['all'];
            $data['authorized_report'] = $this->permission_report($user);
            return parent::handleRespond($data);
        }

        return parent::handleNotFound($user);
    }


    private function permission_report($user)
    {

        if ($user->is_admin) {
            $permissions = null;
            for ($i = 1; $i <= 109; $i++) {
                $permissions[] = strval($i);
            }
            return $permissions;
        } else {
            $role_id = $user->role_id;
            $role = UserRole::where(BaseModel::ID, $role_id)->first();
            return $role->authorized_report ?? null;
        }

    }
}
