<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use App\Models\Docente;
use Illuminate\Http\Request;

class EvaluacionController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'id_tipo' => 'required|integer',
            'id_materia' => 'required|integer',
            'id_eva_padre' => 'sometimes|required|integer',
            'nombre' => 'required|string',
            'fecha_realizacion' => 'required|date',
            'lugar' => 'required|string',
            'es_diferido' => 'sometimes|required|boolean',
            'es_repetido' => 'sometimes|required|boolean',
            'documento' => 'sometimes|required|file'
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validator->errors()
            ], 422);
        }

        if(!$this->checkIntMateria($request->id_materia)){
            return response()->json([
                'success' => false,
                'message' => 'La materia no existe'
            ], 422);
        }

        $hoy = Carbon::now()->format('Y-m-d');
        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();
        $id_ciclo = DB::table('ciclo')
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->first()->id_ciclo;
        
        if(!$id_ciclo){
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un ciclo activo'
            ], 422);
        }

        $docenteMateriaCiclo = DB::table('docente_materia_ciclo')
            ->where('id_docente', $docente->id_docente)
            ->where('id_materia', $request->id_materia)
            ->where('id_ciclo', $id_ciclo)
            ->first();  

        if(!$docenteMateriaCiclo){
            return response()->json([
                'success' => false,
                'message' => 'El docente no imparte la materia en el ciclo actual'
            ], 422);
        }
        
        DB::beginTransaction();

        try{
            $id_evaluacion = DB::table('evaluacion')
            ->insertGetId([
                'id_tipo' => $request->id_tipo,
                'id_doc_materia' => $docenteMateriaCiclo->id_doc_materia,
                'id_eva_padre' => $request->id_eva_padre,
                'id_materia' => $request->id_materia,
                'nombre' => $request->nombre,
                'fecha_realizacion' => $request->fecha_realizacion,
                'fecha_publicacion' => $hoy,
                'documento' => $request->documento,
                'lugar' => $request->lugar,
                'es_diferido' => $request->es_diferido,
                'es_repetido' => $request->es_repetido,
                'created_at' => $hoy,
                'created_user' => $user->carnet,
                'updated_at' => $hoy,
                'updated_user' => $user->carnet
            ], 'id_evaluacion');

            $estudiantes_materia = DB::table('cursa')
            ->where('id_materia', $request->id_materia)
            ->where('id_ciclo', $id_ciclo)
            ->pluck('carnet');

            foreach($estudiantes_materia as $estudiante){
                DB::table('evaluacion_estudiante')
                ->insert([
                    'id_evaluacion' => $id_evaluacion,
                    'carnet' => $estudiante,
                    'created_at' => $hoy,
                    'created_user' => $user->carnet,
                    'updated_at' => $hoy,
                    'updated_user' => $user->carnet
                ]);
            }
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Evaluación creada correctamente'
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la evaluación',
                'errors' => $e->getMessage()
            ], 500);
        }
        
    }

    public function list($id_ciclo, $id_materia)
    {
        $validators = Validator::make([
            'id_ciclo' => $id_ciclo,
            'id_materia' => $id_materia
        ],
        [
            'id_materia' => 'required|integer',
            'id_ciclo' => 'required|integer'
        ]);

        if($validators->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();

        $request = Docente::getEvaluacionEstudiantes($docente->id_docente,$id_ciclo, $id_materia);

        return response()->json([
            'success' => true,
            'message' => 'Evaluaciones obtenidas correctamente',
            'data' => $request
        ], 200);
    }

    public function marcarAsistencia(Request $request)
    {
        $validators = Validator::make($request->all(), [
            'id_evaluacion' => 'required|integer',
            'carnet' => 'required|string',
            'asistio' => 'required|boolean'
        ]);

        if($validators->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();

        if(!$this->checkIntEValuacion($request->id_evaluacion)){
            return response()->json([
                'success' => false,
                'message' => 'La evaluación no existe'
            ], 422);
        }

        if(!$this->checkEstudiante($request->carnet)){
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no existe'
            ], 422);
        }

        DB::beginTransaction();
        try {
            DB::table('evaluacion_estudiante')
            ->where('id_evaluacion', $request->id_evaluacion)
            ->where('carnet', $request->carnet)
            ->update([
                'asistencia' => $request->asistio,
                'updated_at' => Carbon::now()->format('Y-m-d'),
                'updated_user' => $user->carnet
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asistencia marcada correctamente para el estudiante '.$request->carnet.' en la evaluación.'
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la asistencia',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function registrarNota(Request $request)
    {
        $validators = Validator::make($request->all(), [
            'id_evaluacion' => 'required|integer',
            'carnet' => 'required|string',
            'nota' => 'required|numeric'
        ]);

        if($validators->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();

        if(!$this->checkIntEValuacion($request->id_evaluacion)){
            return response()->json([
                'success' => false,
                'message' => 'La evaluación no existe'
            ], 422);
        }

        if(!$this->checkEstudiante($request->carnet)){
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no existe'
            ], 422);
        }

        DB::beginTransaction();
        try{
            DB::table('evaluacion_estudiante')
            ->where('id_evaluacion', $request->id_evaluacion)
            ->where('carnet', $request->carnet)
            ->update([
                'nota' => $request->nota,
                'updated_at' => Carbon::now()->format('Y-m-d'),
                'updated_user' => $user->carnet
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Nota registrada correctamente para el estudiante '.$request->carnet.' en la evaluación.'
            ], 200);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la nota',
                'errors' => $e->getMessage()
            ], 500);
        }

    }



}
