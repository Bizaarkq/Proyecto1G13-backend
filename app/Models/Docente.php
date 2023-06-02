<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Docente extends Model 
{

    use SoftDeletes;

    protected $table = 'docente';
    protected $primaryKey = 'id_docente';

    protected $fillable = [
        'id_docente'
    ];

    public function Revision()
    {
        return $this->HasMany('App\Models\Revision', 'id_docente');
    }

    public function getEvaluaciones($id_docente, $id_ciclo, $id_materia)
    {
        return Docente::join('docente_materia_ciclo as dmc', 'dmc.id_docente', 'docente.id_docente')
        ->join('evaluacion as e', 'e.id_doc_materia', 'dmc.id_doc_materia')
        ->join('materia as m', 'm.id_materia', 'dmc.id_materia')
        ->join('ciclo as c', 'c.id_ciclo', 'dmc.id_ciclo')
        ->where('docente.id_docente', $id_docente)
        ->where('c.id_ciclo', $id_ciclo)
        ->where('m.id_materia', $id_materia)
        ->select('e.id_evaluacion', 'e.nombre', 'e.fecha_realizacion', 'e.lugar', 'e.es_diferido', 'e.es_repetido', 'm.codigo as materia', 'c.codigo as ciclo')
        ->get();
    }

    public static function getEvaluacionEstudiantes($id_docente, $id_ciclo, $id_materia)
    {
        return Docente::join('docente_materia_ciclo as dmc', 'dmc.id_docente', 'docente.id_docente')
        ->join('evaluacion as e', 'e.id_doc_materia', 'dmc.id_doc_materia')
        ->join('materia as m', 'm.id_materia', 'dmc.id_materia')
        ->join('ciclo as c', 'c.id_ciclo', 'dmc.id_ciclo')
        ->join('evaluacion_estudiante as ee', 'ee.id_evaluacion', 'e.id_evaluacion')
        ->join('estudiante as est', 'est.carnet', 'ee.carnet')
        ->where('docente.id_docente', $id_docente)
        ->where('c.id_ciclo', $id_ciclo)
        ->where('m.id_materia', $id_materia)
        ->select(
            'e.id_evaluacion', 
            'e.nombre', 
            'e.fecha_realizacion', 
            'e.lugar', 
            'e.es_diferido', 
            'e.es_repetido', 
            'm.codigo as materia', 
            'c.codigo as ciclo', 
            'est.carnet', 
            'est.nombres as nombre_estudiante', 
            'est.apellidos as apellido_estudiante', 
        )
        ->get();
    }
}
