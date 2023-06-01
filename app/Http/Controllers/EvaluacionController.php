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

        $hoy = Carbon::now()->format('Y-m-d H:i:s');
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
            ->leftJoin('solicitud_diferido_repetido as sdr', function ($query) use ($user) {
                $query->on('sdr.id_evaluacion', 'e.id_evaluacion')
                ->where('sdr.carnet', $user->carnet);
            })
            ->where('est.carnet', $user->carnet)
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
                'sdr.id_solicitud as diferido_repetido',
                'sdr.aprobado'
            )
            ->orderBy('e.fecha_realizacion', 'desc')
            ->get();

            $evaluaciones = json_decode(json_encode($evaluaciones), true);

            $hoy = Carbon::now()->format('Y-m-d');
            foreach ($evaluaciones as $key => $evaluacion) {
                $evaluaciones[$key]['puede_diferir'] =
                    $evaluacion['diferido_repetido'] == null &&
                    $evaluacion['asistencia'] == 0 &&
                    $evaluacion['nota'] == null  &&
                    $evaluacion['es_diferido'] == 0 &&
                    $evaluacion['es_repetido'] == 0 &&
                    Carbon::parse($evaluacion['fecha_realizacion'])->lt($hoy) &&
                    Carbon::parse($evaluacion['fecha_realizacion'])->diffInDays($hoy) <= $limite_dif;

                if ($evaluacion['asistencia']) {
                    $nota_avg = DB::table('evaluacion_estudiante')
                    ->where('id_evaluacion', $evaluacion['id_evaluacion'])
                    ->avg('nota');

                    $evaluaciones[$key]['puede_repetir'] =
                        $evaluacion['diferido_repetido'] == null &&
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
            \Log::error('Error al obtener las evaluaciones: '.$e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las evaluaciones',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function solicitarDiferidoRepetido(Request $request)
    {
        try {
            $validators = Validator::make($request->all(), [
                'id_evaluacion' => 'required|integer',
                'tipo' => 'required|string|in:diferido,repetido'
            ]);

            if ($validators->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la validación de datos',
                    'errors' => $validators->errors()
                ], 422);
            }

            $user = Auth::guard('api')->user();
            $estudiante = DB::table('estudiante')->where('carnet', $user->carnet)->first();

            DB::table('solicitud_diferido_repetido')
            ->insert([
                'id_evaluacion' => $request->id_evaluacion,
                'carnet' => $estudiante->carnet,
                'es_diferido' => $request->tipo == 'diferido' ? 1 : 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_user' => $user->carnet,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $user->carnet
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de '.$request->tipo.' enviada correctamente'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al solicitar el diferido/repetido',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function aprobarDiferidoRepetido(Request $request)
    {
        try {
            $validators = Validator::make($request->all(), [
                'id_solicitud' => 'required|integer',
                'aprobado' => 'required|boolean'
            ]);

            if ($validators->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la validación de datos',
                    'errors' => $validators->errors()
                ], 422);
            }

            $user = Auth::guard('api')->user();

            DB::beginTransaction();
            DB::table('solicitud_diferido_repetido')
            ->where('id_solicitud', $request->id_solicitud)
            ->update([
                'aprobado' => $request->aprobado,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $user->carnet
            ]);
            if ($request->aprobado) {
                $eval = DB::table('solicitud_diferido_repetido')
                ->where('id_solicitud', $request->id_solicitud)
                ->select('id_evaluacion', 'es_diferido', 'carnet')
                ->first();

                $exam = DB::table('evaluacion')
                ->where('id_eva_padre', function ($query) use ($eval) {
                    $query->select('id_evaluacion')
                    ->from('evaluacion')
                    ->where('id_evaluacion', $eval->id_evaluacion)
                    ->whereNull('es_diferido')
                    ->first()->id_evaluacion;
                })
                ->first();

                if (!$exam) {
                    $eva = DB::table('evaluacion')
                    ->where('id_evaluacion', $eval->id_evaluacion)
                    ->first();
                    $hoy = Carbon::now();
                    $id = DB::table('evaluacion')
                    ->insertGetId([
                        'id_eva_padre' => $eval->id_evaluacion,
                        'id_tipo' => $eva->id_tipo,
                        'id_doc_materia' => $eva->id_doc_materia,
                        'id_materia' => $eva->id_materia,
                        'nombre' => $eva->nombre . ' ' . 'Diferido/Repetido',
                        'fecha_realizacion' => $hoy->addWeek()->format('Y-m-d'),
                        'fecha_publicacion' => $hoy->format('Y-m-d'),
                        'lugar' => $eva->lugar,
                        'es_diferido' => $eval->es_diferido,
                        'es_repetido' => !$eval->es_diferido,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_user' => $user->carnet,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'updated_user' => $user->carnet
                    ], 'id_evaluacion');

                    DB::table('evaluacion_estudiante')
                    ->insert([
                        'id_evaluacion' => $id,
                        'carnet' => $eval->carnet,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_user' => $user->email,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'updated_user' => $user->email
                    ]);
                } else {
                    DB::table('evaluacion_estudiante')
                    ->insert([
                        'id_evaluacion' => $exam->id_evaluacion,
                        'carnet' => $eval->carnet,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'created_user' => $user->email,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'updated_user' => $user->email
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Solicitud aprobada correctamente'
            ], 200);
        } catch(\Exception $e) {
            DB::rollback();
            \Log::warning('Error al aprobar la solicitud: '.$e);
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la solicitud',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function getSolicitudesDiferidoRepetido(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $docente = DB::table('docente')->where('codigo', $user->carnet)->first();
            $ciclo = $this->getCicloActivo();
            $materias = DB::table('docente_materia_ciclo as dmc')
            ->join('evaluacion', 'dmc.id_materia', '=', 'evaluacion.id_materia')
            ->where('id_docente', $docente->id_docente)
            ->where('id_ciclo', $ciclo->id_ciclo)
            ->pluck('evaluacion.id_materia');

            $solicitudes = DB::table('solicitud_diferido_repetido as s')
            ->join('evaluacion as e', 'e.id_evaluacion', '=', 's.id_evaluacion')
            ->join('estudiante as es', 'es.carnet', '=', 's.carnet')
            ->join('materia as m', 'm.id_materia', '=', 'e.id_materia')
            ->join('tipo_evaluacion as te', 'te.id_tipo', '=', 'e.id_tipo')
            ->whereIn('e.id_materia', $materias)
            ->select(
                's.id_solicitud',
                's.carnet',
                'es.nombres',
                'es.apellidos',
                'm.codigo as materia',
                'te.descripcion as tipo',
                'e.nombre as evaluacion',
                's.es_diferido',
                's.aprobado'
            )
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes obtenidas correctamente',
                'data' => $solicitudes
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function getEvaluacionesDocente(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $docente = DB::table('docente')->where('codigo', $user->carnet)->first();
            $ciclo = $this->getCicloActivo();

            $materias = DB::table('docente_materia_ciclo as dmc')
            ->join('evaluacion', 'dmc.id_materia', '=', 'evaluacion.id_materia')
            ->where('id_docente', $docente->id_docente)
            ->where('id_ciclo', $ciclo->id_ciclo)
            ->pluck('evaluacion.id_materia');

            $evaluaciones = DB::table('evaluacion as e')
            ->join('materia as m', 'm.id_materia', '=', 'e.id_materia')
            ->join('tipo_evaluacion as te', 'te.id_tipo', '=', 'e.id_tipo')
            ->leftJoin('impresion as imp', 'imp.id_evaluacion', 'e.id_evaluacion')
            ->leftJoin('error_impresion as ei', 'ei.cod_error_imp', 'imp.cod_error_imp')
            ->whereIn('e.id_materia', $materias)
            ->select(
                'e.id_evaluacion',
                'e.id_materia',
                'm.codigo as materia',
                'te.descripcion as tipo',
                'e.nombre as evaluacion',
                'e.fecha_realizacion',
                'e.fecha_publicacion',
                'e.lugar',
                'e.es_diferido',
                'e.es_repetido',
                'imp.id_impresion',
                'imp.aprobado',
                'imp.detalles_formato',
                'imp.hojas_anexas',
                'imp.cantidad',
                'ei.nombre as codigo_error',
                'ei.descripcion as error_impresion',
                'imp.observacion_error',
                'imp.impreso'
            )
            ->orderBy('e.fecha_realizacion', 'desc')
            ->get();

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

    public function solicitarImpresion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_evaluacion' => 'required',
                'detalles_formato' => 'required',
                'hojas_anexas' => 'required',
                'cantidad' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al solicitar la impresión',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = Auth::guard('api')->user();
            $docente = DB::table('docente')->where('codigo', $user->carnet)->first();

            $dmc = DB::table('docente_materia_ciclo as dmc')
            ->join('evaluacion as ev', 'ev.id_materia', '=', 'dmc.id_materia')
            ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
            ->where('dmc.id_docente', $docente->id_docente)
            ->where('ev.id_evaluacion', $request->id_evaluacion)
            ->first();

            if (!$dmc) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la evaluación',
                    'errors' => 'No se encontró la evaluación'
                ], 404);
            }

            DB::beginTransaction();
            DB::table('impresion')->insert([
                'id_doc_materia' => $dmc->id_doc_materia,
                'id_evaluacion' => $request->id_evaluacion,
                'detalles_formato' => $request->detalles_formato,
                'hojas_anexas' => $request->hojas_anexas,
                'cantidad' => $request->cantidad,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_user' => $user->email,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $user->email
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Impresión solicitada correctamente'
            ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al solicitar la impresión',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function getPendientesImpresion(Request $request)
    {
        try{
            $user = Auth::guard('api')->user();

            $pendientes = DB::table('impresion as imp')
            ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'imp.id_evaluacion')
            ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
            ->leftJoin('error_impresion as ei', 'ei.cod_error_imp', 'imp.cod_error_imp')
            ->select(
                'imp.id_impresion',
                'm.codigo as materia',
                'ev.nombre as evaluacion',
                'imp.detalles_formato',
                'imp.hojas_anexas',
                'imp.cantidad',
                'imp.aprobado',
                'imp.impreso',
                'ei.nombre as codigo_error',
                'ei.descripcion as error_impresion',
                'imp.observacion_error'
            )->get();

            return response()->json([
                'success' => true,
                'message' => 'Impresiones obtenidas correctamente',
                'data' => $pendientes
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las impresiones',
                'errors' => $e->getMessage()
            ], 500);
        }
    }   

    public function aprobarImpresion(Request $request)
    {
        try{
            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'id_impresion' => 'required',
                'aprobado' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al aprobar la impresión',
                    'errors' => $validator->errors()
                ], 400);
            }

            DB::table('impresion')
            ->where('id_impresion', $request->id_impresion)
            ->update([
                'aprobado' => $request->aprobado,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Impresión aprobada correctamente'
            ], 200);

        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la impresión',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function imprimirEvaluacion(Request $request)
    {
        try{
            $user = Auth::guard('api')->user();
            $validator = Validator::make($request->all(), [
                'id_impresion' => 'required',
                'impreso' => 'required',
                'codigo_error' => 'numeric|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al imprimir la evaluación',
                    'errors' => $validator->errors()
                ], 400);
            }

            
            $impresion = DB::table('impresion')
            ->where('id_impresion', $request->id_impresion);

            if($request->impreso == 1){
                $impresion->update([
                    'impreso' => $request->impreso,
                    'cod_error_imp' => null,
                    'observacion_error' => null,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_user' => $user->email
                ]);
            }else{
                $impresion->update([
                    'impreso' => $request->impreso,
                    'cod_error_imp' => $request->codigo_error,
                    'observacion_error' => $request->observacion_error,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_user' => $user->email
                ]);
            }           

            return response()->json([
                'success' => true,
                'message' => 'Evaluación impresa correctamente'
            ], 200);

        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al imprimir la evaluación',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
