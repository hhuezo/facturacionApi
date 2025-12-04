<?php

namespace App\Models\catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    use HasFactory;

    protected $table = 'marcas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'codigo',
        'nombre',
        'descripcion',
        'estado',
        'eliminado'
    ];

    protected $casts = [
        'idEmpresa' => 'integer',
        'estado' => 'string',
        'eliminado' => 'string',
    ];
}
