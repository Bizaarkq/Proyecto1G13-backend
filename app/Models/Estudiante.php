<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estudiante extends Model 
{

    use SoftDeletes;

    protected $table = 'estudiante';
    protected $primaryKey = 'carnet';
    protected $keyType = 'string';
    public $incrementing = false;

    public function Revision()
    {
        return $this->HasMany('App\Models\Revision', 'carnet');
    }
}
