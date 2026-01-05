<?php

namespace App\Models;

use App\Models\catalogo\Producto;
use App\Models\mh\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturaDetalle extends Model
{
    use HasFactory;

    protected $table = 'facturacion_encabezado_detalle';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idEncabezado',
        'idProducto',
        'idTipoItem',
        'idUnidadMedida',
        'cantidad',
        'precioUnitario',
        'porcentajeDescuento',
        'descuento',
        'excentas',
        'gravadas',
        'iva',
        'idInventario',
        'motivoCambioPrecio',
    ];

    protected $casts = [
        'cantidad'            => 'decimal:4',
        'precioUnitario'      => 'decimal:4',
        'porcentajeDescuento' => 'decimal:4',
        'descuento'            => 'decimal:4',
        'excentas'            => 'decimal:4',
        'gravadas'            => 'decimal:4',
        'iva'                 => 'decimal:4',
    ];

    /* =========================
     * RELACIONES
     * ========================= */

    public function encabezado()
    {
        return $this->belongsTo(Factura::class, 'idEncabezado');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'idProducto');
    }


    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'idUnidadMedida');
    }
}
