<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsuarioEmpresa extends Model
{
    use HasFactory;

    protected $table = 'general_usuarios_det_empresas_asignadas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'idEmpresa',
        'idSucursal',
        'idPuntoVenta',
        'idUsuarioAsignado',
        'sucursalDefecto',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistra',
        'idUsuarioEdita',
        'fechaEdita',
        'fechaElimina',
        'idUsuarioElimina'
    ];

    protected $casts = [
        'fechaRegistra' => 'datetime',
        'fechaEdita'    => 'datetime',
        'fechaElimina'  => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuarioAsignado');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa');
    }

    public function sucursal()
    {
        return $this->belongsTo(EmpresaSucursal::class, 'idSucursal');
    }
}
