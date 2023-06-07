<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Mail\Correo;
use Illuminate\Support\Facades\Mail;
use Log;

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

    function checkEstudiante($carnet)
    {
        $estudiante = DB::table('estudiante')->where('carnet', $carnet)->first();
        return !is_null($estudiante);
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

    function sendEmail($to, $subject, $view, $data, $attach = null)
    {
        $from = env('MAIL_FROM_ADDRESS', '');
        $name = env('MAIL_FROM_NAME', 'CPA');

        $send = Mail::to($to)->send(new Correo($from, $name, $subject, $view, $data, $attach));
    
        return $send;
    }

    function getCoordinadorMateria($id_evaluacion)
    {
        return DB::table('evaluacion as e')
        ->join('docente_materia_ciclo as dmc', 'dmc.id_materia', 'e.id_materia')
        ->join('docente as d', 'd.id_docente', 'dmc.id_docente')
        ->join('cargo as c', 'c.id_cargo', 'dmc.id_cargo')
        ->join('users as u', 'u.carnet', 'd.codigo')
        ->where('e.id_evaluacion', $id_evaluacion)
        ->where('c.descripcion', 'Coordinador')
        ->select(
            'u.email',
            'd.nombres as docente_nombre',
            'd.apellidos as docente_apellido'
        )
        ->first();
    }
}
