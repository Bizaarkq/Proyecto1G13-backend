<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class Controller extends BaseController
{
    function checkIntEValuacion($id_evaluacion)
    {
        $evaluacion = DB::table('evaluacion')->where('id_evaluacion', $id_evaluacion)->first();
        return !is_null($evaluacion);
    }

    function checkIntMateria($id_materia)
    {
        $evaluacion = DB::table('materia')->where('id_materia', $id_materia)->first();
        return !is_null($evaluacion);
    }

    function getCicloActivo()
    {
        $hoy = Carbon::now();
        $ciclo = DB::table('ciclo')
        ->where('fecha_inicio', '<=', $hoy)
        ->where('fecha_fin', '>=', $hoy)
        ->select('id_ciclo')
        ->first();

        return $ciclo;
    }
}
