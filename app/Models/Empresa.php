<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idPersoneria',
        'numeroIVA',
        'nit',
        'nombre',
        'nombreComercial',
        'nombreLogo',
        'representanteLegal',
        'correo',
        'telefono',
        'fechaRegistro',
        'idUsuarioRegistra',
        'eliminado',
        'fechaElimina',
        'idUsuarioElimina',
        'formatoImagen',
        'colorPrimario',
        'colorSecundario',
        'colorFondo',
        'colorBorde',
        'colorTexto',
    ];

    // 🔹 Casts para fechas
    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina' => 'datetime',
    ];
}
