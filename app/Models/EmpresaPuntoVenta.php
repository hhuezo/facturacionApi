<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaPuntoVenta extends Model
{
    use HasFactory;

    protected $table = 'general_datos_empresa_sucursales_puntos_venta';

    protected $fillable = [
        'idSucursal',
        'idEmpresa',
        'ubicacion',
        'codigoEstablecimientoMh',
        'codigoPuntoVentaMh',
        'codigoEstablecimientoInterno',
        'codigoPuntoVentaInterno',
        'eliminado',
        'idUsuarioRegistra',
        'fechaRegistro',
        'idUsuarioElimina',
        'fechaElimina',
    ];

    public $timestamps = false;


    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa', 'id');
    }

    public function sucursal()
    {
        return $this->belongsTo(EmpresaSucursal::class, 'idSucursal', 'id');
    }
}
