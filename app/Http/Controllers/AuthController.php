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
    
    public function index()
    {   
        $pdo = DB::connection()->getPdo();
        dd($pdo);
        return json_encode($pdo);
    }

    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user' => 'required|email',
            'password' => 'required|string'
        ]);

        if($validator->fails()){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->input('user');
        $password = $request->input('password');

        $exists = User::where('email', $user)
        ->select('name', 'email', 'password')    
        ->first();

        if(!$exists){
            return response()->json([
                'message' => 'No existe el usuario'
            ], 401);
        }
        
        $authenticated = Hash::check($password, $exists->password);

        if(!$authenticated){
            return response()->json([
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
                'user' => $exists,
                'access_token' => $access_token
            ]
        ], 200);
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