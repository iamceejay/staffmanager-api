<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Auth;

class AuthController extends Controller
{
    public function login(Request $request) {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'email'         => 'required|email',
                    'password'      => 'required|string'
                ]
            );

            if($validator->fails()) {
                return response()->json([
                    'status'    => 'error',
                    'message'    => $validator->errors()->all()
                ], 422);
            }

            $authenticate = Auth::attempt([
                'email'     => $request->email,
                'password'  => $request->password,
            ]);

            if(!$authenticate) {
                return response()->json([
                    'status'    => 'error',
                    'message'   => 'Email and password combination not found'
                ], 404);
            }

            $user = Auth::user();

            $token = $user->createToken('Staffmanager Password Grant Client')->accessToken;
            $user->token = $token;

            return response()->json([
                'status'    => 'success',
                'user'      => $user,
                'token'     => $token,
                'role'      => $user->roles->pluck('name')->first()
            ], 200);
        } catch(Throwable $e) {
            return response()->json([
                'status'    => 'error',
                'message'   => $e
            ], 500);
        }
    }
}
