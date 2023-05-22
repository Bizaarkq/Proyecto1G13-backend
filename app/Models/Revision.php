<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revision extends Model 
{

    use SoftDeletes;

    protected $table = 'revision';

    public function Estudiante()
    {
        return $this->belongsTo('App\Models\Estudiante', 'carnet');
    }

    public function Docente()
    {
        return $this->belongsTo('App\Models\Docente', 'id_docente');
    }
}
