<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Docente extends Model 
{

    use SoftDeletes;

    protected $table = 'docente';
    protected $primaryKey = 'id_docente';

    public function Revision()
    {
        return $this->HasMany('App\Models\Revision', 'id_docente');
    }
}
