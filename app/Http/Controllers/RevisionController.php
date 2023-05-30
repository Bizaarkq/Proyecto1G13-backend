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

class RevisionController extends Controller
{
    public function getRevisionesDocente()
    {
        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();
        $revisiones = Revision::whereHas('Docente', function ($query) use ($docente) {
            $query->where('id_docente', $docente->id);
        })->get();

        return response()->json([
            'success' => true,
            'message' => 'Revisiones obtenidas correctamente',
            'revisiones' => $revisiones
        ], 200);
    }

    public function getRevisionesEstudiante()
    {
        $user = Auth::guard('api')->user();
        $revisiones = Revision::whereHas('Estudiante', function ($query) use ($user) {
            $query->where('carnet', $user->carnet);
        })->get();

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
        $user = Auth::guard('api')->user();
        $docente = Docente::where('codigo', $user->carnet)->first();
        $id_ciclo = $this->getCicloActivo();

        $revisiones = DB::table('solicitud_revision as sr')
            ->join('evaluacion as ev', 'ev.id_evaluacion', '=', 'sr.id_evaluacion')
            ->join('materia as m', 'm.id_materia', '=', 'ev.id_materia')
            ->join('docente_materia_ciclo as dmc', 'dmc.id_materia', '=', 'm.id_materia')
            ->join('docente as d', 'd.id_docente', '=', 'dmc.id_docente')
            ->join('ciclo as c', 'c.id_ciclo', '=', 'dmc.id_ciclo')
            ->where('sr.estado', 'PENDIENTE')
            ->where('d.id_docente', $docente->id_docente)
            ->where('c.id_ciclo', $id_ciclo->id_ciclo)
            ->select(
                'sr.id_sol',
                'sr.id_evaluacion',
                'sr.motivo',
                'sr.fecha_solicitud',
                'sr.estado',
                'm.codigo as materia',
                'ev.nombre as evaluacion'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revisiones
        ], 200);
    }

    public function getEvaluacionesRevision(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            $estudiante = Estudiante::where('carnet', $user->carnet)->first();
            $hoy = Carbon::now()->format('Y-m-d H:i:s');

            $config = DB::table('configuracion')
            ->where('codigo', 'PERIODO DIFERIDO')
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
                    'ev.id_evaluacion',
                    'ev.nombre',
                    'm.codigo',
                    'ee.updated_at'
                )
                ->get();

            $eval = array_filter($evaluaciones->toArray(), function ($item) use ($hoy, $dias_limite) {
                return Carbon::parse($item->updated_at)->diffInDays($hoy) <= $dias_limite ;
            });

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
}
