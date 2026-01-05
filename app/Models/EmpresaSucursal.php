<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaSucursal extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_sucursales';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false; // no usa created_at / updated_at

    protected $fillable = [
        'idEmpresa',
        'idTipoEstablecimiento',
        'responsable',
        'telefono',
        'correo',
        'idDepartamento',
        'idMunicipio',
        'direccion',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioElimina',
        'fechaElimina',
        'nombreSucursal',
        'codigoEstablecimiento',
    ];

    protected $casts = [
        'fechaRegistro' => 'datetime',
        'fechaElimina'  => 'datetime',
    ];
}
