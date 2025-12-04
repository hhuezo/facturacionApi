<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    // 🔹 Nombre de la tabla
    protected $table = 'general_datos_empresa';

    // 🔹 Clave primaria
    protected $primaryKey = 'id';

    // 🔹 La tabla NO usa timestamps de Laravel (created_at / updated_at)
    public $timestamps = false;

    // 🔹 Campos asignables
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
