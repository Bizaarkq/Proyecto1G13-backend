<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Revision;
use App\Models\Docente;
use App\Models\Estudiante;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Log;

class RevisionController extends Controller
{
    public function getRevisionesDocente()
    {
        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();
        $ciclo = $this->getCicloActivo();
        $revisiones = DB::table('solicitud_revision as sr')
                ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'sr.id_evaluacion')
                ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
                ->join('docente_materia_ciclo as dmc', 'dmc.id_materia', '=', 'm.id_materia')
                ->join('docente as d', 'd.id_docente', '=', 'dmc.id_docente')
                ->join('ciclo as c', 'c.id_ciclo', '=', 'dmc.id_ciclo')
                ->join('estudiante as est', 'est.carnet', '=', 'sr.carnet')
                ->join('tipo_evaluacion as te', 'te.id_tipo', '=', 'ev.id_tipo')
                ->join('evaluacion_estudiante as ee', function($join) {
                    $join->on('ee.id_evaluacion', '=', 'ev.id_evaluacion')
                        ->on('ee.carnet', '=', 'est.carnet');
                })
                ->leftJoin('revision as r', 'r.id_sol', '=', 'sr.id_sol')
                ->leftJoin('motivo_cambio as mc', 'mc.cod_motivo', '=', 'r.cod_motivo')
                ->leftJoin('docente as docrev', 'docrev.id_docente', '=', 'r.id_docente')
                ->leftJoin('estudiante as respsoc', 'respsoc.carnet', '=', 'r.respsociedadestud_carnet')
                ->where('sr.estado', 'APROBADA')
                ->where('d.id_docente', $docente->id_docente)
                ->where('c.id_ciclo', $ciclo->id_ciclo)
                ->select(
                    'sr.id_sol',
                    'sr.id_evaluacion',
                    'sr.motivo',
                    'm.codigo as materia',
                    'ev.nombre as evaluacion',
                    'sr.fecha_solicitud',
                    'est.carnet',
                    'est.nombres',
                    'est.apellidos',
                    'te.descripcion as tipo',
                    'ee.nota as nota_original',
                    'r.id',
                    'mc.nombre as motivo_cambio',
                    'mc.descripcion as descripcion_cambio',
                    'r.nueva_nota',
                    'r.created_at as fecha_revision',
                    'docrev.nombres as docente_nombre',
                    'docrev.apellidos as docente_apellido',
                    'respsoc.nombres as respsoc_nombre',
                    'respsoc.apellidos as respsoc_apellido'
                )
                ->orderBy('sr.created_at', 'desc')
                ->get();

        foreach($revisiones as $key => $revision) {
            $revisiones[$key]->fecha_revision = Carbon::parse($revision->fecha_revision)->format('d/m/Y');
            $revisiones[$key]->fecha_solicitud = Carbon::parse($revision->fecha_solicitud)->format('d/m/Y');
            $revisiones[$key]->nota_original = number_format($revision->nota_original, 2, '.', '');

            if ($revision->nueva_nota) {
                $revisiones[$key]->nueva_nota = number_format($revision->nueva_nota, 2, '.', '');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Revisiones obtenidas correctamente',
            'data' => $revisiones
        ], 200);
    }

    public function getRevisionesEstudiante()
    {
        $user = Auth::guard('api')->user();
        $revisiones = DB::table('solicitud_revision as sr')
        ->join('evaluacion as e', 'sr.id_evaluacion', '=', 'e.id_evaluacion')
        ->join('materia as m', 'e.id_materia', '=', 'm.id_materia')
        ->join('estudiante as es', 'sr.carnet', '=', 'es.carnet')
        ->leftJoin('revision as r', 'sr.id_sol', '=', 'r.id_sol')
        ->leftJoin('docente as d', 'r.id_docente', '=', 'd.id_docente')
        ->where('es.carnet', $user->carnet)
        ->select(
            'sr.id_sol',
            'sr.fecha_solicitud',
            'sr.motivo',
            'sr.estado',
            'e.nombre',
            'm.codigo',
            'sr.fecha_aprobacion',
            'sr.local_destinado',
            'sr.fecha_hora_revision',
            'r.id as existe_revision',
            'r.nueva_nota',
            'r.created_at as fecha_revision',
        )
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Revisiones obtenidas correctamente',
            'revisiones' => $revisiones
        ], 200);
    }

    public function solicitarRevision(Request $request)
    {
        $validators = Validator::make($request->all(), [
            'id_evaluacion' => 'required|integer',
            'motivo' => 'required|string|max:300'
        ]);

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        if (!$this->checkIntEValuacion($request->input('id_evaluacion'))) {
            return response()->json([
                'success' => false,
                'message' => 'La evaluación no existe'
            ], 404);
        }

        $user = Auth::guard('api')->user();
        $estudiante = Estudiante::where('carnet', $user->carnet)->first();

        $checkEvaluacion = DB::table('evaluacion_estudiante')
            ->where('id_evaluacion', $request->input('id_evaluacion'))
            ->where('carnet', $estudiante->carnet)
            ->first();

        if (!$checkEvaluacion) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no está inscrito en la evaluación'
            ], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('solicitud_revision')
                ->insert([
                    'id_evaluacion' => $request->input('id_evaluacion'),
                    'carnet' => $estudiante->carnet,
                    'motivo' => $request->input('motivo'),
                    'fecha_solicitud' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_user' => $estudiante->carnet,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_user' => $estudiante->carnet
                ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de revisión enviada correctamente'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la solicitud de revisión',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function desistirRevision(Request $request)
    {
        $validators = Validator::make($request->all(), [
            'id_sol' => 'required|integer',
            'motivo' => 'required|string|max:300'
        ]);

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();
        $estudiante = Estudiante::where('carnet', $user->carnet)->first();

        $solicitud = DB::table('solicitud_revision')
            ->where('id_sol', $request->input('id_sol'))
            ->where('carnet', $estudiante->carnet)
            ->first();

        if (!$solicitud) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud no existe'
            ], 404);
        }

        if ($solicitud->estado != 'PENDIENTE') {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud ya fue '. $solicitud->estado
            ], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('solicitud_revision')
            ->where('id_sol', $request->input('id_sol'))
            ->where('carnet', $estudiante->carnet)
            ->update([
                'estado' => 'DESISTIDA',
                'motivo' => $request->input('motivo'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $estudiante->carnet
            ]);
        } catch(\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al desistir la solicitud de revisión',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function aprobarRevision(Request $request)
    {
        $validators = Validator::make($request->all(), [
            'id_sol' => 'required|integer',
            'decision' => 'required|boolean',
            'local' => 'sometimes|required|string|max:100',
            'fecha' => 'sometimes|required|date_format:Y-m-d H:i:s',
        ]);

        if ($validators->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos',
                'errors' => $validators->errors()
            ], 422);
        }

        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();
        $id_ciclo = $this->getCicloActivo();

        $materia = DB::table('materia')
            ->join('evaluacion as ev', 'ev.id_materia', '=', 'materia.id_materia')
            ->join('solicitud_revision as sr', 'sr.id_evaluacion', '=', 'ev.id_evaluacion')
            ->where('sr.id_sol', $request->input('id_sol'))
            ->select('materia.id_materia')
            ->first();

        if (!$materia) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud de revisión no existe'
            ], 422);
        }

        $docenteMateriaCiclo = DB::table('docente_materia_ciclo')
            ->where('id_docente', $docente->id_docente)
            ->where('id_materia', $materia->id_materia)
            ->where('id_ciclo', $id_ciclo->id_ciclo)
            ->first();

        if (!$docenteMateriaCiclo) {
            return response()->json([
                'success' => false,
                'message' => 'El docente no está asignado a la materia'
            ], 422);
        }

        try {
            DB::table('solicitud_revision')
                ->where('id_sol', $request->input('id_sol'))
                ->update([
                    'fecha_aprobacion' => Carbon::now()->format('Y-m-d H:i:s'),
                    'estado' => $request->input('decision') ? 'APROBADA' : 'RECHAZADA',
                    'local_destinado' => $request->input('local'),
                    'fecha_hora_revision' => $request->input('fecha'),
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_user' => $docente->codigo
                ]);
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de revisión actualizada correctamente'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la solicitud de revisión',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function getListadoRevPendientes(Request $request)
    {
        try { 
            $user = Auth::guard('api')->user();
            $docente = Docente::where('codigo', $user->carnet)->first();
            $id_ciclo = $this->getCicloActivo();

            $revisiones = DB::table('solicitud_revision as sr')
                ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'sr.id_evaluacion')
                ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
                ->join('docente_materia_ciclo as dmc', 'dmc.id_materia', '=', 'm.id_materia')
                ->join('docente as d', 'd.id_docente', '=', 'dmc.id_docente')
                ->join('ciclo as c', 'c.id_ciclo', '=', 'dmc.id_ciclo')
                ->join('estudiante as est', 'est.carnet', '=', 'sr.carnet')
                ->join('tipo_evaluacion as te', 'te.id_tipo', '=', 'ev.id_tipo')
                ->leftJoin('revision as r', 'r.id_sol', '=', 'sr.id_sol')
                ->where('sr.estado', 'PENDIENTE')
                ->where('r.id_sol', null)
                ->where('d.id_docente', $docente->id_docente)
                ->where('c.id_ciclo', $id_ciclo->id_ciclo)
                ->select(
                    'sr.id_sol',
                    'sr.id_evaluacion',
                    'sr.motivo',
                    'sr.fecha_solicitud',
                    'sr.estado',
                    'm.codigo as materia',
                    'ev.nombre as evaluacion',
                    'est.carnet',
                    'est.nombres',
                    'est.apellidos',
                    'te.descripcion as tipo'
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Solicitudes de revisión obtenidas correctamente',
                'data' => $revisiones
            ], 200);
        }catch (\Throwable $th) {
            Log::info('Error al obtener las solicitudes de revisión: ' . $th);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes de revisión',
            ], 500);
        }
        
    }

    public function getEvaluacionesRevision(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $estudiante = Estudiante::where('carnet', $user->carnet)->first();
            $hoy = Carbon::now()->format('Y-m-d H:i:s');

            $config = DB::table('configuracion')
            ->where('codigo', 'PERIODO REVISION')
            ->first();

            $dias_limite = $config->valor_fijo;

            $solicitudesActivas = DB::table('solicitud_revision as sr')
            ->where('carnet', $estudiante->carnet)
            ->where('estado', 'PENDIENTE')
            ->pluck('id_evaluacion');

            $evaluaciones = DB::table('estudiante as est')
                ->join('evaluacion_estudiante as ee', 'ee.carnet', '=', 'est.carnet')
                ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'ee.id_evaluacion')
                ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
                ->where('est.carnet', $estudiante->carnet)
                ->whereNotIn('ev.id_evaluacion', $solicitudesActivas)
                ->whereNotNull('ee.nota')
                ->whereNotNull('ee.asistencia')
                ->select(
                    'est.carnet',
                    'ev.id_evaluacion',
                    'ev.nombre',
                    'm.codigo',
                    'ee.updated_at'
                )
                ->get();

            $eval = array_filter($evaluaciones->toArray(), function ($item) use ($hoy, $dias_limite) {
                return Carbon::parse($item->updated_at)->diffInDays($hoy) <= $dias_limite ;
            });

            $eval = array_filter($eval, function ($item) {
                return DB::table('solicitud_revision')
                    ->where('id_evaluacion', $item->id_evaluacion)
                    ->where('carnet', $item->carnet)
                    ->where('estado', 'APROBADA')
                    ->count() < 3;
            });

            $eval = array_values($eval);
            return response()->json([
                'success' => true,
                'data' => $eval
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las evaluaciones',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function getMotivosRevision(Request $request)
    {
        try {
            $motivos = DB::table('motivo_cambio')
                ->select('cod_motivo', 'nombre')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Motivos de revisión obtenidos correctamente',
                'data' => $motivos
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los motivos de revisión',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function getResponsablesSociales(Request $request)
    {
        try {
            $responsables = DB::table('estudiante as es')
                ->where('es.respsocestud', true)
                ->select('es.carnet', 'es.nombres', 'es.apellidos')
                ->get();
            return response()->json([
                'success' => true,
                'message' => 'Responsables sociales obtenidos correctamente',
                'data' => $responsables
            ], 200);
        } catch (\Throwable $th) {
            \Log::warning($th);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los responsables sociales',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function crearRevision(Request $request)
    {
        try{
            $validators = Validator::make($request->all(), [
                'carnet' => 'required|string|max:7',
                'respsociedad' => 'string|max:7|nullable',
                'cod_motivo' => 'required|string',
                'nueva_nota' => 'required|numeric',
                'id_sol' => 'required|numeric'
            ]);

            if($validators->fails()){
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validators->errors()
                ], 422);
            }

            $user = Auth::guard('api')->user();
            $docente = Docente::where('codigo', $user->carnet)->first();

            $num_rev = 1;
            $sol_rev = DB::table('solicitud_revision')
                ->where('id_sol', $request->id_sol)
                ->select('carnet', 'id_evaluacion')
                ->first();

            $check = DB::table('solicitud_revision')
                ->where('carnet', $sol_rev->carnet)
                ->where('id_evaluacion', $sol_rev->id_evaluacion)
                ->whereNot('id_sol', $request->id_sol)
                ->get();

            if($check->count() > 0){
                $num_rev = $check->count() + 1;
            }

            $check = DB::table('solicitud_revision as sr')
                ->join('revision as r', 'sr.id_sol', '=', 'r.id_sol')
                ->where('sr.estado', 'APROBADA')
                ->where('sr.id_evaluacion', $sol_rev->id_evaluacion)
                ->where('sr.carnet', $sol_rev->carnet)
                ->orderBy('sr.created_at', 'desc')
                ->select('r.id')
                ->first();

            $rev_id = $check ? $check->id : null;

            $revision = new Revision();
            $revision->id_sol = $request->id_sol;
            $revision->carnet = $request->carnet;
            $revision->rev_id = $rev_id;
            $revision->num_rev = $num_rev;
            $revision->respsociedadestud_carnet = $request->respsociedad;
            $revision->cod_motivo = $request->cod_motivo;
            $revision->nueva_nota = $request->nueva_nota;
            $revision->id_docente = $docente->id_docente;
            $revision->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $revision->created_user = $user->email;
            $revision->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $revision->updated_user = $user->email;
            $revision->save();

            DB::table('evaluacion_estudiante')
            ->where('carnet', $request->carnet)
            ->where('id_evaluacion', $sol_rev->id_evaluacion)
            ->update([
                'nota' => $request->nueva_nota,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_user' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de revisión creada correctamente',
            ], 200);


        }catch(\Throwable $th){
            Log::info('Error al crear la revisión: ' . $th);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la solicitud de revisión',
            ], 500);
        }
    }

}
