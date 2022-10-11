<?php

namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Exception;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;

        $user = User::where('email', $username)->first();

        if ($user) {
            if ( !Hash::check($password, $user->password)) {
                return parent::handleRespond(null, [], 405, 'Username or Password not mach');
            }

            //todo return token for auth


            return parent::handleRespond($user);
        }

        return parent::handleRespond(null, [], 404, 'User not found');
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required',
            'company' => 'required',
            'email' => 'required',
            'phone' => 'required',
        ];

        $request_data = parent::validate($request, $rules);

        if (isset($request_data['invalid'])) {
            return parent::handleRespond($request_data, [], 500, 'Error');
        }

        try {
            $user = User::create($request_data);
            //todo userid
            $user_id = 1;
            parent::audi_log(
                $request,
                BaseModel::CREATE_TEXT,
                $user->id,
                $user_id,
                'USER',
                null,
                $request->all()
            );

        } catch (Exception $exception) {
            return parent::handleRespond($request->all(), [], 500, 'Error');
        }
    }

}
