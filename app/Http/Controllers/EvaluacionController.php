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

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$this->checkIntMateria($request->id_materia)) {
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

        if (!$id_ciclo) {
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

        if (!$docenteMateriaCiclo) {
            return response()->json([
                'success' => false,
                'message' => 'El docente no imparte la materia en el ciclo actual'
            ], 422);
        }

        DB::beginTransaction();

        try {
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

            foreach ($estudiantes_materia as $estudiante) {
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
        } catch(\Exception $e) {
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
        $validators = Validator::make(
            [
            'id_ciclo' => $id_ciclo,
            'id_materia' => $id_materia
        ],
            [
                'id_materia' => 'required|integer',
                'id_ciclo' => 'required|integer'
            ]
        );

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();

        $request = Docente::getEvaluacionEstudiantes($docente->id_docente, $id_ciclo, $id_materia);

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

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();

        if (!$this->checkIntEValuacion($request->id_evaluacion)) {
            return response()->json([
                'success' => false,
                'message' => 'La evaluación no existe'
            ], 422);
        }

        if (!$this->checkEstudiante($request->carnet)) {
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
        } catch(\Exception $e) {
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

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();

        if (!$this->checkIntEValuacion($request->id_evaluacion)) {
            return response()->json([
                'success' => false,
                'message' => 'La evaluación no existe'
            ], 422);
        }

        if (!$this->checkEstudiante($request->carnet)) {
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
                'nota' => $request->nota,
                'updated_at' => Carbon::now()->format('Y-m-d'),
                'updated_user' => $user->carnet
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Nota registrada correctamente para el estudiante '.$request->carnet.' en la evaluación.'
            ], 200);
        } catch(\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la nota',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function getEvaluaciones(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $ciclo = $this->getCicloActivo();

            $config = DB::table('configuracion')
            ->where('codigo', 'PERIODO DIFERIDO')
            ->first();

            $limite_dif = $config->valor_fijo;

            $evaluaciones = DB::table('evaluacion_estudiante as ee')
            ->join('estudiante as est', 'est.carnet', 'ee.carnet')
            ->join('evaluacion as e', 'e.id_evaluacion', 'ee.id_evaluacion')
            ->join('tipo_evaluacion as te', 'te.id_tipo', 'e.id_tipo')
            ->join('materia as m', 'm.id_materia', 'e.id_materia')
            ->join('docente_materia_ciclo as dmc', 'dmc.id_doc_materia', 'e.id_doc_materia')
            ->join('ciclo as c', 'c.id_ciclo', 'dmc.id_ciclo')
            ->join('docente as d', 'd.id_docente', 'dmc.id_docente')
            ->where('ee.carnet', $user->carnet)
            ->where('c.id_ciclo', $ciclo->id_ciclo)
            ->select(
                'e.id_evaluacion',
                'e.nombre',
                'te.descripcion as tipo',
                'e.fecha_realizacion',
                'e.lugar',
                'ee.asistencia',
                'ee.nota',
                'e.es_diferido',
                'e.es_repetido',
                'm.codigo as materia',
                'c.codigo as ciclo',
            )
            ->orderBy('e.fecha_realizacion', 'desc')
            ->get();

            $evaluaciones = json_decode(json_encode($evaluaciones), true);
            $hoy = Carbon::now()->format('Y-m-d');
            foreach ($evaluaciones as $key => $evaluacion) {
                $evaluaciones[$key]['puede_diferir'] = 
                    $evaluacion['asistencia'] == 0 &&
                    $evaluacion['nota'] == null  &&
                    $evaluacion['es_diferido'] == 0 && 
                    $evaluacion['es_repetido'] == 0 && 
                    Carbon::parse($evaluacion['fecha_realizacion'])->lt($hoy) &&
                    Carbon::parse($evaluacion['fecha_realizacion'])->diffInDays($hoy) <= $limite_dif;

                if($evaluacion['asistencia']){
                    $nota_avg = DB::table('evaluacion_estudiante')
                    ->where('id_evaluacion', $evaluacion['id_evaluacion'])
                    ->avg('nota');

                    $evaluaciones[$key]['puede_repetir'] = 
                        $nota_avg < 5.1 &&
                        $evaluacion['es_diferido'] == 0 &&
                        $evaluacion['es_repetido'] == 0 &&
                        $evaluacion['asistencia'] == 1;
                }
                
            }

            return response()->json([
                'success' => true,
                'message' => 'Evaluaciones obtenidas correctamente',
                'data' => $evaluaciones
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las evaluaciones',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
