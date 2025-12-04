<?php

namespace App\Models\mh;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadEconomica extends Model
{
    use HasFactory;

    protected $table = 'mh_actividad_economica';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'codigoActividad',
        'nombreActividad',
        'activo'
    ];

    protected $casts = [
        'activo' => 'string'
    ];
}
