<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request) {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'email'         => 'required|email|unique:users,email',
                    'firstName'     => 'required|string|max:255',
                    'lastName'      => 'required|string|max:255',
                    'phone'         => 'required|regex:/^([0-9\s\-\+\(\)]*)$/',
                    'password'      => 'required|string|min:6|confirmed'
                ]
            );

            if($validator->fails()) {
                return response()->json([
                    'status'    => 'error',
                    'message'    => $validator->errors()->all()
                ], 422);
            }

            $user = User::create([
                'first_name'    => $request->firstName,
                'last_name'     => $request->lastName,
                'email'         => $request->email,
                'phone_number'  => preg_replace('/\D+/', '', $request->phone),
                'password'      => Hash::make($request->password)
            ]);

            $user->assignRole('staff');

            // $token = $user->createToken('Staffmanager Password Grant')->accessToken;

            return response()->json([
                'status'    => 'success',
                'user'      => $user,
                // 'token'     => $token   
            ], 200);
        } catch(Throwable $e) {
            return response()->json([
                'status'    => 'error',
                'message'   => $e
            ], 500);
        }
    }

    public function staffs(Request $request) {
        $users = User::role('staff');

        if($request->keyword) {
            $users = $users->where('first_name', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('phone_number', 'LIKE', '%' . $request->keyword . '%');
        }

        $users = $users->get();

        return response()->json([
            'message'   => 'List of users with role of staff.',
            'staff'     => $users
        ], 200);
    }

    public function delete($id) {
        try {
            User::where('id', $id)->delete();

            return response()->json([
                'status'    => 'success',
                'message'   => 'User has been deleted.'
            ]);
        } catch(Throwable $e) {
            return response()->json([
                'status'    => 'error',
                'message'   => $e
            ], 500);
        }
    }
}
