<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\DB;
use Throwable;

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
                'phone_number'  => $request->countryCode . $request->phone,
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
            DB::beginTransaction();

            SmoobuJob::unguard();
            
            SmoobuJob::where('staff_id', $id)
                ->where('status', 'taken')
                ->update([
                    'status' => 'available',
                    'staff_id' => null
                ]);

            SmoobuJob::reguard();
            
            User::where('id', $id)->delete();
            
            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'User has been deleted and their jobs have been updated.'
            ]);

        } catch(Throwable $e) {
            DB::rollBack();
            
            return response()->json([
                'status'    => 'error',
                'message'   => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword($id, Request $request) {
        try {
            $request->validate([
                'password' => 'required|min:6'
            ]);

            User::where('id', $id)->update([
                'password' => Hash::make($request->password)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset successfully.'
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
