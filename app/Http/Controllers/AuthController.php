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

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->input('email');
            $password = $request->input('password');

            $exists = User::where('users.email', $user)
            ->select('users.name', 'users.email', 'users.password', 'users.carnet')
            ->first();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe el usuario'
                ], 401);
            }

            $exists->role = DB::table('model_has_roles as mhr')
                ->join('roles', 'mhr.role_id', '=', 'roles.id')
                ->join('users', 'mhr.model_id', '=', 'users.id')
                ->where('users.email', $exists->email)
                ->pluck('roles.name');

            $authenticated = Hash::check($password, $exists->password);

            if (!$authenticated) {
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
        } catch(\Exception $e) {
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

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'carnet' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        try {
            $user = User::where('email', $request->input('email'))->first();
            if ($user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante ya existe'
                ], 409);
            }

            $user = User::where('carnet', $request->input('carnet'))->first();
            if ($user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante ya existe'
                ], 409);
            }

            DB::beginTransaction();

            $carnet = strtoupper($request->input('carnet'));
            $email = strtolower($request->input('email'));
            DB::table('solicitud_registro')
            ->insert([
                'carnet' => $carnet,
                'nombres' => $request->input('nombres'),
                'apellidos' => $request->input('apellidos'),
                'email' => $email,
                'password' => Hash::make($request->input('password')),
                'estado' => 'PENDIENTE', // 'Aprobado', 'Rechazado', 'Pendiente'
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_user' => $request->input('email'),
                'updated_user' => $request->input('email')
            ]);

            DB::commit();

            $coordinadores = User::whereHas(
                'roles',
                function ($q) {
                    $q->where('name', 'coordinador');
                }
            )->pluck("email");

            $send = $this->sendEmail(
                $coordinadores,
                'Solicitud de registro',
                'mails.solicitud_registro',
                [
                    'carnet' => $carnet,
                    'nombres' => $request->input('nombres'),
                    'apellidos' => $request->input('apellidos'),
                    'email' => $email
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de registro enviada' . ($send ? ' y notificada' : ''),
                'data' => $user
            ], 201);
        } catch(\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function aprobarRegistro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'aprobado' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $solicitud = DB::table('solicitud_registro')
                ->where('id_solregistro', $request->input('id'))
                ->first();

            if (!$solicitud) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solicitud no existe'
                ], 404);
            }

            DB::beginTransaction();


            DB::table('solicitud_registro')
                ->where('id_solregistro', $request->input('id'))
                ->update([
                    'estado' => $request->input('aprobado') ? 'APROBADO' : 'RECHAZADO',
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_user' => $request->input('email')
                ]);

            if ($request->input('aprobado')) {
                $user = new User();
                $user->carnet = $solicitud->carnet;
                $user->name = $solicitud->nombres . ' ' . $solicitud->apellidos;
                $user->email = $solicitud->email;
                $user->password = $solicitud->password;
                $user->save();

                $rol = DB::table('roles')->where('name', 'estudiante')->first();
                DB::table('model_has_roles')
                ->insert([
                    'role_id' => $rol->id,
                    'model_type' => 'App\User',
                    'model_id' => $user->id
                ]);
            }

            $send = $this->sendEmail(
                $solicitud->email,
                'Solicitud de registro',
                'mails.solicitud_registro_aprobada',
                [
                    'aprobado' => $request->input('aprobado')
                ]
            );

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de registro ' . ($request->input('aprobado') ? 'aprobada' : 'rechazada') . ($send ? ' y notificada' : ''),
                'data' => $solicitud
            ], 200);
        } catch(\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function solicitudesRegistro()
    {
        try {
            $solicitudes = DB::table('solicitud_registro')
                ->select('id_solregistro as id','carnet', 'nombres', 'apellidos', 'email', 'estado')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes de registro',
                'data' => $solicitudes
            ], 200);
        } catch(\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
