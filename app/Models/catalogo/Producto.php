<?php

namespace App\Models\catalogo;

use App\Models\mh\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'idEmpresa',
        'idCliente',
        'nombre',
        'imagen',
        'idTipoItem',
        'idUnidadMedida',
        'aplicarRetencion',
        'idMarca',
        'idCatalogo',
        'idCategoria',
        'idSubCategoria',
        'descripcion',
        'especificaciones',
        'precioVentaConIva',
        'poseeDescuento',
        'porcentajeDescuento',
        'valorDescuento',
        'precioUnitarioFinalConIVA',
        'excento',
        'fechaRegistra',
        'idUsuarioRegistra',
        'fechaEdicion',
        'idUsuarioEdita',
        'eliminado',
        'idUsuarioElimina',
        'fechaElimina',
        'mostrarEnInventario',
        'precioUnitarioConIva'
    ];

    protected $casts = [
        'precioVentaConIva' => 'decimal:4',
        'porcentajeDescuento' => 'decimal:2',
        'valorDescuento' => 'decimal:4',
        'precioUnitarioFinalConIVA' => 'decimal:4',
        'precioUnitarioConIva' => 'decimal:4',

        'fechaRegistra' => 'datetime',
        'fechaEdicion' => 'datetime',
        'fechaElimina' => 'datetime',

        'aplicarRetencion' => 'string',
        'poseeDescuento' => 'string',
        'excento' => 'string',
        'eliminado' => 'string',
        'mostrarEnInventario' => 'string',
    ];


    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'idUnidadMedida');
    }


     public function tipoItem()
    {
        return $this->belongsTo(TipoItem::class, 'idTipoItem');
    }
}
