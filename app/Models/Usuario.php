<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'general_usuarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'usuario',
        'nombre',
        'correo',
        'password',
        'estado',
        'eliminado',
        'idPerfil',
        'fechaRegistro',
        'idUsuarioRegistra',
        'fechaElimina',
        'idUsuarioElimina',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina'  => 'datetime',
    ];
}
