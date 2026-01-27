<?php

namespace App\Models;

use App\Models\catalogo\Cliente;
use App\Models\mh\Ambiente;
use App\Models\mh\TipoDocumentoTributario;
use App\Models\mh\TipoPago;
use App\Models\mh\TipoPlazo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $table = 'facturacion_encabezado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [

        'idEmpresa',
        'idSucursal',
        'idCaja',
        'idTipoDte',
        'versionJson',
        'idAmbiente',
        'idTipoFacturacion',
        'idTipoTransmision',
        'idTipoContingencia',
        'motivoContingencia',
        'fechaHoraEmision',
        'idCliente',
        'codigoGeneracion',
        'idNumeroControl',
        'numeroControl',
        'observaciones',

        'totalNoSujeta',
        'totalExenta',
        'totalGravada',
        'descuentoNoSujeto',
        'descuentoGravada',
        'descuentoExenta',
        'porcentajeDescuento',
        'totalDescuento',
        'totalIVA',
        'subTotal',
        'ivaPercibido1',
        'ivaRetenido1',
        'retencionRenta',
        'seguros',
        'fletes',
        'montoTotalOperacion',
        'totalNoGravado',
        'totalPagar',

        'idTipoPago',
        'idCondicionVenta',
        'idPlazo',
        'diasCredito',
        'totalCobrado',
        'totalVuelto',
        'idPos',
        'numeroAutorizacionTC',
        'numeroCheque',
        'idBancoCheque',
        'observacionesPago',

        'idDocumentoRelacionado',
        'estadoHacienda',
        'selloHacienda',
        'fechaRecibidoHacienda',
        'selloInvalidacion',
        'idInvalidacion',
        'fechaInvalidacion',

        'idEventoContingencia',
        'idRecinto',
        'idRegimen',
        'idPaisExportacion',
        'idIncoterms',
        'idTipoItem',

        'idUsuarioRegistraOrden',
        'fechaRegistraOrden',
        'idUsuarioTransmiteDte',
        'fechaTransmitenDte',

        'eliminado',
        'fechaEliminacion',
        'idUsuarioElimina',
        'motivoEliminacion',

        'idPuntoVenta',
        'idTipoOperacionRenta',
        'idTipoIngresoRenta',
        'idClasificacionRenta',
        'idSectorRenta',
        'idTipoCostoGastoRenta',
        'numeroAnexo',
    ];

    protected $casts = [

        'totalNoSujeta'          => 'decimal:4',
        'totalExenta'            => 'decimal:4',
        'totalGravada'           => 'decimal:4',
        'totalDescuento'         => 'decimal:4',
        'totalIVA'               => 'decimal:4',
        'subTotal'               => 'decimal:4',
        'totalPagar'             => 'decimal:4',
        'totalCobrado'           => 'decimal:4',
        'totalVuelto'            => 'decimal:4',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'idCliente');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'idEmpresa');
    }

    public function sucursal()
    {
        return $this->belongsTo(EmpresaSucursal::class, 'idSucursal');
    }

    public function puntoVenta()
    {
        return $this->belongsTo(EmpresaPuntoVenta::class, 'idPuntoVenta');
    }

    public function tipoDocumentoTributario()
    {
        return $this->belongsTo(TipoDocumentoTributario::class, 'idTipoDte');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idUsuarioRegistraOrden');
    }

    public function ambiente()
    {
        return $this->belongsTo(Ambiente::class, 'idAmbiente');
    }

    public function detalles()
    {
        return $this->hasMany(FacturaDetalle::class, 'idEncabezado');
    }

    public function tipoPago()
    {
        return $this->belongsTo(TipoPago::class, 'idTipoPago');
    }

    public function tipoPlazo()
    {
        return $this->belongsTo(TipoPlazo::class, 'idPlazo');
    }
}
