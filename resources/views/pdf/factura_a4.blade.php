<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 12px; }
body { font-family: DejaVu Sans; font-size: 10px; color: {{ $texto }}; }
table { width:100%; border-collapse: collapse; table-layout: fixed; }
th, td { border:1px solid {{ $borde }}; padding:4px; vertical-align: top; }
.title { font-weight:bold; text-align:center; background: {{ $fondo }}; color: {{ $primario }}; }
.section { background: {{ $primario }}; color:#fff; font-weight:bold; text-align:center; }
.small { font-size:9px; }
.right { text-align:right; }
.center { text-align:center; }
.no-border td, .no-border th { border:none; }
</style>
</head>
<body>

<!-- HEADER -->
<table>
<tr>
    <td style="width:18%;" class="center">
        @if(!empty($logoPath))
            <img src="{{ $logoPath }}" style="max-width:120px;">
        @else
            <strong>{{ $factura->empresa->nombre }}</strong>
        @endif
    </td>
    <td style="width:16%;" class="center">
        @if(!empty($qrBase64))
            <img src="data:image/png;base64,{{ $qrBase64 }}" style="width:110px;height:110px;">
        @endif
    </td>
    <td style="width:66%; padding:0;">
        <div class="title">
            DOCUMENTO TRIBUTARIO ELECTRÓNICO - {{ $factura->tipoDocumentoTributario->nombre ?? 'Comprobante Credito Fiscal' }}
        </div>
        <table class="no-border small">
            <tr>
                <td style="width:50%;">
                    <strong>Código Generación:</strong> {{ $factura->codigoGeneracion }}<br>
                    <strong>Número de control:</strong> {{ $factura->numeroControl }}<br>
                    <strong>Sello Recepción:</strong> {{ $factura->selloHacienda ?? '' }}<br>
                    <strong>Tipo Transmisión:</strong> {{ $factura->nombreTipoTransmision ?? 'Transmisión normal' }}
                </td>
                <td style="width:50%;">
                    <strong>Modelo facturación:</strong> {{ $factura->modeloFacturacion ?? 'Modelo Facturación previo' }}<br>
                    <strong>Fecha Procesamiento:</strong> {{ optional($factura->fechaRecibidoHacienda)->format('Y-m-d H:i:s') }}<br>
                    <strong>Sucursal:</strong> {{ $factura->sucursal->nombreSucursal ?? 'Casa matriz' }}<br>
                    <strong>Punto Venta:</strong> {{ $factura->nombrePuntoVenta ?? ('Caja '.$factura->idCaja) }}
                    &nbsp; <strong>Versión Json:</strong> {{ $factura->versionJson }}
                </td>
            </tr>
        </table>
    </td>
</tr>
</table>

<br>

<!-- EMISOR / RECEPTOR -->
<table>
<tr>
    <th class="center">EMISOR</th>
    <th class="center">RECEPTOR</th>
</tr>
<tr class="small">
    <td>
        <strong>Razón Social:</strong> {{ $factura->empresa->nombre }}<br>
        <strong>NIT:</strong> {{ $factura->empresa->nit }}<br>
        <strong>NRC:</strong> {{ $factura->empresa->nrc ?? '' }}<br>
        <strong>Actividad Económica:</strong> {{ $nombreActividad }}<br>
        <strong>Dirección:</strong> {{ $sucursal->direccion ?? '' }}<br>
        <strong>Teléfono:</strong> {{ $sucursal->telefono ?? '' }}<br>
        <strong>Correo:</strong> {{ $factura->empresa->correo ?? '' }}
    </td>
    <td>
        <strong>Nombre o Razón Social:</strong> {{ $factura->cliente->nombreCliente }}<br>
        <strong>NIT:</strong> {{ $factura->cliente->numeroDocumento }}<br>
        <strong>NRC:</strong> {{ $factura->cliente->nrc ?? '' }}<br>
        <strong>Actividad Económica:</strong> {{ $factura->cliente->actividadEconomica->nombreActividad ?? '' }}<br>
        <strong>Dirección:</strong> {{ $factura->cliente->direccion }}<br>
        <strong>Teléfono:</strong> {{ $factura->cliente->telefono ?? '' }}<br>
        <strong>Correo:</strong> {{ $factura->cliente->correo ?? '' }}
    </td>
</tr>
</table>

<br>

<!-- DOCUMENTOS RELACIONADOS -->
<table>
<tr><th colspan="3" class="section">DOCUMENTOS RELACIONADOS</th></tr>
<tr class="center small">
    <th>TIPO DOCUMENTO</th>
    <th>N° DE DOCUMENTO</th>
    <th>FECHA DE DOCUMENTO</th>
</tr>
<tr class="center small">
    <td>{{ $factura->nombreTipoDocumentoTribuRela ?? '' }}</td>
    <td>{{ $factura->codigoGeneracionRela ?? '' }}</td>
    <td>{{ $factura->fechaHoraEmisionRela ?? '' }}</td>
</tr>
</table>

<br>

<!-- DETALLE -->
<table>
<tr class="center small">
    <th style="width:3%;">N</th>
    <th style="width:7%;">Cantidad</th>
    <th style="width:6%;">Unidad</th>
    <th>Descripción</th>
    <th style="width:9%;">Precio Unitario</th>
    <th style="width:9%;">Montos no Afectos</th>
    <th style="width:8%;">Descuento item</th>
    <th style="width:8%;">No Sujetas</th>
    <th style="width:8%;">Exentas</th>
    <th style="width:9%;">Gravadas</th>
</tr>
@foreach($factura->detalles as $i => $d)
<tr class="small">
    <td class="center">{{ $i+1 }}</td>
    <td class="center">{{ number_format($d->cantidad,4) }}</td>
    <td class="center">{{ $d->unidadMedida->nombre ?? 'Unidad' }}</td>
    <td>{{ $d->producto->nombre }} {{ $d->descripcionExtra ?? '' }}</td>
    <td class="right">${{ number_format($d->precioUnitario,2) }}</td>
    <td class="right">$0.00</td>
    <td class="right">${{ number_format($d->descuento ?? 0,2) }}</td>
    <td class="right">$0.00</td>
    <td class="right">${{ number_format($d->excentas ?? 0,2) }}</td>
    <td class="right">${{ number_format($d->gravadas ?? 0,2) }}</td>
</tr>
@endforeach
</table>

<br>

<!-- FOOTER -->
<table>
<tr>
    <td style="width:55%;" class="small">
        <strong>Monto a letras:</strong> {{ $factura->montoLetras ?? '' }} USD<br>
        <strong>Atiende:</strong> {{ $factura->usuario->nombre ?? '' }}<br>
        <strong>Forma de Pago:</strong> {{ $factura->nombreTipoPago ?? '' }}<br>
        <strong>Condición de Operación:</strong> {{ $factura->nombreCondicionVenta ?? '' }}<br>
        <strong>Observaciones:</strong> {{ $factura->observaciones ?? '' }}
    </td>
    <td style="width:45%; padding:0;">
        <table class="small">
            <tr><td>Sumatoria de ventas</td><td class="right">${{ number_format($factura->subTotal,2) }}</td></tr>
            <tr><td>Monto global Desc. no sujetas</td><td class="right">${{ number_format($factura->descuentoNoSujeto ?? 0,2) }}</td></tr>
            <tr><td>Monto global Desc. exentas</td><td class="right">${{ number_format($factura->descuentoExenta ?? 0,2) }}</td></tr>
            <tr><td>Monto global Desc. gravadas</td><td class="right">${{ number_format($factura->descuentoGravada ?? 0,2) }}</td></tr>
            <tr><td>Tributos: IVA</td><td class="right">${{ number_format($factura->totalIVA ?? 0,2) }}</td></tr>
            <tr><td>Sub total</td><td class="right">${{ number_format($factura->subTotal,2) }}</td></tr>
            <tr><td>IVA Percibido</td><td class="right">${{ number_format($factura->ivaPercibido1 ?? 0,2) }}</td></tr>
            <tr><td>IVA Retenido</td><td class="right">${{ number_format($factura->ivaRetenido1 ?? 0,2) }}</td></tr>
            <tr><td>Retención de Renta</td><td class="right">${{ number_format($factura->retencionRenta ?? 0,2) }}</td></tr>
            <tr><td>Total Otros montos no afectos</td><td class="right">$0.00</td></tr>
            <tr><td><strong>Total a pagar</strong></td><td class="right"><strong>${{ number_format($factura->totalPagar,2) }}</strong></td></tr>
        </table>
    </td>
</tr>
</table>

</body>
</html>
