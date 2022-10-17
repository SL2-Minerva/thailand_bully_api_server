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
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;

        if (!$token = auth('api')->attempt(['email' => $username, 'password' => $password])) {
            return parent::handleRespond(null, [], 404, 'Unauthorized');
        }

        return $this->respondWithToken($token);
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

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {

        return response()->json(auth('api')->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return parent::handleRespond([], [], 200, 'Successfully logged out');
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {

        return parent::handleRespond([
            'accessToken' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ], null);
    }

}
