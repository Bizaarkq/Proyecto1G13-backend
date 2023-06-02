<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string'
            ]);
    
            if($validator->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
    
            $user = $request->input('email');
            $password = $request->input('password');
    
            $exists = User::join('model_has_roles as mhr', 'mhr.model_id', '=', 'users.id')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('users.email', $user)
            ->select('users.name', 'users.email', 'users.password', 'users.carnet', 'r.name as role')    
            ->first();
    
            if(!$exists){
                return response()->json([
                    'success' => false,
                    'message' => 'No existe el usuario'
                ], 401);
            }
            
            $authenticated = Hash::check($password, $exists->password);
    
            if(!$authenticated){
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $access_token = Str::random(32);
            
            DB::beginTransaction();
            DB::table('users')
                ->where('email', $user) 
                ->update([
                    'access_token' => $access_token,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);
            DB::commit();
            
            return response()->json([
                'message' => 'Login successful',
                'data' => [
                    'success' => true,
                    'user' => $exists,
                    'access_token' => $access_token
                ]
            ], 200);
        }catch(\Exception $e){
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $e->getMessage()
            ], 500);
        }        
    }

    public function me()
    {
        $user = Auth::guard('api')->user();
        return response()->json([
            'message' => 'User data',
            'data' => [
                'carnet' => $user->carnet,
                'user' => $user->name,
                'email' => $user->email
            ]
        ], 200);
    }

    public function logout()
    {
        $user = Auth::guard('api')->user();
        DB::beginTransaction();
        DB::table('users')
            ->where('email', $user->email) 
            ->update([
                'access_token' => null,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);
        DB::commit();
        return response()->json([
            'message' => 'Logout successful'
        ], 200);
    }

}