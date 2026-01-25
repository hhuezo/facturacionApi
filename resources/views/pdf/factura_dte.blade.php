<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 5mm 5mm 20mm 5mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: {{ $texto }};
        }
        .titulo {
            font-size: 12px;
            font-weight: bold;
            color: {{ $primario }};
            background-color: {{ $fondo }};
            text-align: center;
        }
        .subtitulo {
            color: {{ $primario }};
            font-weight: bold;
        }
        .info {
            font-size: 10px;
            color: {{ $texto }};
        }
        .encabezado {
            border-collapse: collapse;
            width: 100%;
        }
        .encabezado td {
            border: 1px solid {{ $borde }};
            padding: 5px;
        }
        .th-claro {
            background-color: {{ $fondo }};
        }
        .th-primario {
            background-color: {{ $primario }};
            color: #FFF;
        }
        .th-primario td {
            background-color: {{ $primario }};
            color: #FFF;
        }
        .th-claro td {
            background-color: {{ $fondo }};
        }
        .invalidado {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(45deg);
            font-size: 100px;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.07);
            z-index: 1000;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .left {
            text-align: left;
        }
    </style>
</head>
<body>
    @if($datos->estadoHacienda === 'INVALIDADO')
        <div class="invalidado">INVALIDADO</div>
    @endif

    <!-- HEADER -->
    <table cellpadding="4" cellspacing="0" class="encabezado" width="100%" style="margin-top: 10px;">
        <tr>
            <td rowspan="2" width="20%" align="center" style="padding: 3px;">
                {!! $br !!}
                @if(!empty($logoAbsolutePath) && file_exists($logoAbsolutePath))
                    <img src="{{ $logoAbsolutePath }}" height="{{ $height }}" width="{{ $width }}" style="max-width: 100%; height: auto;" />
                @elseif(!empty($urlLogo))
                    <img src="{{ $urlLogo }}" height="{{ $height }}" width="{{ $width }}" style="max-width: 100%; height: auto;" />
                @endif
            </td>
            <td colspan="2" width="80%" class="titulo" style="padding: 5px;">
                DOCUMENTO TRIBUTARIO ELECTRÓNICO <br> {{ $datos->nombreTipoDte }}
            </td>
        </tr>
        <tr>
            <td width="18%" align="center" style="vertical-align: top; padding: 3px;">
                @if(!empty($qrBase64) && strlen($qrBase64) > 100)
                    <img src="data:image/png;base64,{{ $qrBase64 }}" width="100" height="100" alt="QR Code" style="max-width: 100px; max-height: 100px;" />
                @else
                    <div style="width: 100px; height: 100px; background: #f0f0f0; border: 1px solid #ccc; display: table-cell; vertical-align: middle; text-align: center;">
                        <span style="font-size: 8px; color: #999;">QR no disponible</span>
                        @if(config('app.debug'))
                            <br><span style="font-size: 7px; color: #f00;">Debug: {{ !empty($qrBase64) ? 'Existe (' . strlen($qrBase64) . ' chars)' : 'No existe' }}</span>
                        @endif
                    </div>
                @endif
            </td>
            <td width="62%" class="info" style="padding: 3px; font-size: 9px;">
                <span class="subtitulo">Código Generación:</span> {{ $datos->codigoGeneracion }}<br>
                <span class="subtitulo">Número de control:</span> {{ $datos->numeroControl }}<br>
                <span class="subtitulo">Sello Recepción:</span> {{ $datos->selloHacienda ?? '' }}<br>
                <span class="subtitulo">Tipo Transmisión:</span> {{ $datos->nombreTipoTransmision }}<br>
                <span class="subtitulo">Modelo facturación:</span> {{ $datos->modeloFacturacion }}<br>
                <span class="subtitulo">Fecha Procesamiento:</span> {{ \Carbon\Carbon::parse($datos->fechaHoraEmision)->format('d/m/Y H:i:s') }}<br>
                <span class="subtitulo">Sucursal:</span> {{ $datos->nombreSucursal }}<br>
                <span class="subtitulo">Punto Venta:</span> {{ $datos->nombrePuntoVenta ?? '' }} &nbsp;&nbsp;&nbsp;
                <span class="subtitulo">Versión Json:</span> {{ $datos->versionJson }}
            </td>
        </tr>
    </table>

    <br><br>

    <!-- EMISOR Y RECEPTOR -->
    <table cellpadding="4" cellspacing="0" border="1" width="100%" class="encabezado">
        <tr class="th-claro">
            <td width="50%" align="center"><strong>EMISOR</strong></td>
            <td width="50%" align="center"><strong>RECEPTOR</strong></td>
        </tr>
        <tr>
            <td class="info">
                <strong>Razón Social:</strong> {{ $datos->nombreEmpresa }}<br>
                <strong>NIT:</strong> {{ $datos->nit }}<br>
                <strong>NRC:</strong> {{ $datos->numeroIVA ?? '' }}<br>
                <strong>Actividad Económica:</strong> {{ $actividadEconomica }}<br>
                <strong>Dirección:</strong> {{ $datos->nombreDepartamentoSucursal }}, {{ $datos->nombreMunicipiosucursal }}, {{ $datos->direccionSucursal ?? '' }}<br>
                <strong>Teléfono:</strong> {{ $datos->telefonoSucursal ?? '' }}<br>
                <strong>Correo:</strong> {{ $datos->correoSucursal ?? '' }}
            </td>
            <td class="info">
                <strong>Nombre o Razón Social:</strong> {{ $datos->nombreCliente ?? '' }}<br>
                <strong>{{ $datos->nombreTipoDocumentoIdentidad ?? 'Documento' }}:</strong> {{ $datos->numeroDocumentoIdentidad ?? '' }}<br>
                <strong>NRC:</strong> {{ $datos->nrc ?? '' }}<br>
                <strong>Actividad Económica:</strong> {{ $datos->nombreActividadEconomica ?? '' }}<br>
                <strong>Dirección:</strong> {{ $datos->nombreDepartamentoCliente ?? '' }}, {{ $datos->nombreMunicipioCliente ?? '' }}, {{ $datos->direccion ?? '' }}<br>
                <strong>Teléfono:</strong> {{ $datos->telefonoCliente ?? '' }}<br>
                <strong>Correo:</strong> {{ $datos->correoCliente ?? '' }}
            </td>
        </tr>
    </table>

    <br>

    <!-- DOCUMENTOS RELACIONADOS -->
    @if(!empty($datos->codigoGeneracionRela))
    <table cellspacing="0" cellpadding="4" border="1" width="100%" class="encabezado">
        <tr class="th-primario">
            <td colspan="3" align="center" style="font-size: 10px!important;"><strong>DOCUMENTOS RELACIONADOS</strong></td>
        </tr>
        <tr style="font-weight:bold; background-color: {{ $fondo }};">
            <td width="33%" align="center" style="font-size: 10px!important;">TIPO DOCUMENTO</td>
            <td width="33%" align="center" style="font-size: 10px!important;">N° DE DOCUMENTO</td>
            <td width="34%" align="center" style="font-size: 10px!important;">FECHA DE DOCUMENTO</td>
        </tr>
        <tr>
            <td align="center" class="info">{{ $datos->nombreTipoDocumentoTribuRela ?? '' }}</td>
            <td align="center" class="info">{{ $datos->codigoGeneracionRela ?? '' }}</td>
            <td align="center" class="info">{{ $datos->fechaHoraEmisionRela ? \Carbon\Carbon::parse($datos->fechaHoraEmisionRela)->format('d/m/Y') : '' }}</td>
        </tr>
    </table>
    @endif

    <br><br>

    <!-- DETALLES -->
    @if($idTipoDte == 1 || $idTipoDte == 2 || $idTipoDte == 4 || $idTipoDte == 9)
    <table cellspacing="0" cellpadding="1" class="encabezado" border="0" bordercolor="{{ $borde }}" width="100%">
        <tr class="th-claro">
            <td width="3%" align="center"><strong>N</strong></td>
            <td width="8%" align="center"><strong>Cantidad</strong></td>
            <td width="5%" align="center"><strong>Unidad</strong></td>
            <td width="36%" align="center"><strong>Descripción</strong></td>
            <td width="9%" align="center"><strong>Precio Unitario</strong></td>
            <td width="7%" align="center"><strong>Montos no Afectos</strong></td>
            <td width="7%" align="center"><strong>Descuento item</strong></td>
            <td width="7%" align="center"><strong>No Sujetas</strong></td>
            <td width="9%" align="center"><strong>Exentas</strong></td>
            <td width="9%" align="center"><strong>Gravadas</strong></td>
        </tr>
        @foreach($detalle as $item)
        <tr>
            <td width="3%" align="center">{{ $item['numero'] }}</td>
            <td width="8%" align="center">{{ number_format($item['cantidad'], 2, '.', ',') }}</td>
            <td width="5%" align="center">{{ $item['unidadMedida'] }}</td>
            <td width="36%" align="left" style="white-space: pre-wrap; font-size:10px;">{{ $item['nombre'] }} {!! $item['descripcion'] !!}</td>
            <td width="9%" align="right">${{ number_format($item['precioUnitario'], 2, '.', ',') }}</td>
            <td width="7%" align="right">${{ number_format(0, 2, '.', ',') }}</td>
            <td width="7%" align="right">${{ number_format($item['descuentoItem'], 2, '.', ',') }}</td>
            <td width="7%" align="center">{{ number_format(0, 2, '.', ',') }}</td>
            <td width="9%" align="right">${{ number_format($item['excento'], 2, '.', ',') }}</td>
            <td width="9%" align="right">${{ number_format($item['gravada'], 2, '.', ',') }}</td>
        </tr>
        @endforeach
    </table>
    @elseif($idTipoDte == 10)
    <table cellspacing="0" cellpadding="1" class="encabezado" border="0" bordercolor="{{ $borde }}" width="100%">
        <tr class="th-claro">
            <td width="3%" align="center"><strong>N</strong></td>
            <td width="8%" align="center"><strong>Cantidad</strong></td>
            <td width="5%" align="center"><strong>Unidad</strong></td>
            <td width="45%" align="center"><strong>Descripción</strong></td>
            <td width="13%" align="center"><strong>Precio Unitario</strong></td>
            <td width="13%" align="center"><strong>Descuento item</strong></td>
            <td width="13%" align="center"><strong>Ventas</strong></td>
        </tr>
        @foreach($detalle as $item)
        <tr>
            <td width="3%" align="center">{{ $item['numero'] }}</td>
            <td width="8%" align="center">{{ number_format($item['cantidad'], 2, '.', ',') }}</td>
            <td width="5%" align="center">{{ $item['unidadMedida'] }}</td>
            <td width="45%" align="left" style="white-space: pre-wrap; font-size:10px;">{{ $item['nombre'] }} {!! $item['descripcion'] !!}</td>
            <td width="13%" align="right">${{ number_format($item['precioUnitario'], 2, '.', ',') }}</td>
            <td width="13%" align="right">${{ number_format($item['descuentoItem'], 2, '.', ',') }}</td>
            <td width="13%" align="right">${{ number_format($item['gravada'], 2, '.', ',') }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <br>

    <!-- TOTALES -->
    <table class="encabezado" cellspacing="0" cellpadding="1" border="0" bordercolor="{{ $borde }}" width="100%">
        <tr>
            <td rowspan="12" width="61%">
                <strong>Monto a letras:</strong> {{ $totalLetras }} USD <br>
                <strong>Atiende:</strong> {{ $datos->nombreUsuario ?? '' }}<br>
                <strong>Forma de Pago:</strong> {{ $datos->nombreTipoPago ?? '' }} <br>
                <strong>Condición de Operación:</strong> {{ $datos->nombreCondicionVenta ?? '' }} <br>
                @if(($datos->idCondicionVenta ?? 1) != 1)
                    <strong>Plazo:</strong> {{ $datos->diasCredito ?? '' }}, <strong>{{ $datos->nombrePlazo ?? '' }}</strong> <br>
                @endif
                <strong>Observaciones:</strong> {{ $datos->observaciones ?? '' }} <br>
            </td>
            <td width="29%">Sumatoria de ventas</td>
            <td width="10%" align="right">${{ number_format($datos->subTotal ?? 0, 2, '.', ',') }}</td>
        </tr>

        @if($idTipoDte == 1 || $idTipoDte == 2 || $idTipoDte == 5)
        <tr>
            <td>Monto global Desc., Rebajas y otros a ventas no sujetas</td>
            <td align="right">${{ number_format($datos->descuentoNoSujeto ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>Monto global Desc., Rebajas y otros a ventas exentas</td>
            <td align="right">${{ number_format($datos->descuentoExenta ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>Monto global Desc., Rebajas y otros a ventas gravadas</td>
            <td align="right">${{ number_format($datos->descuentoGravada ?? 0, 2, '.', ',') }}</td>
        </tr>
        @elseif($idTipoDte == 9)
        <tr>
            <td>Monto global de Desc., Rebajas de operaciones afectas:</td>
            <td align="right">${{ number_format(($datos->descuentoNoSujeto ?? 0) + ($datos->descuentoExenta ?? 0) + ($datos->descuentoGravada ?? 0), 2, '.', ',') }}</td>
        </tr>
        @endif

        @if($idTipoDte == 1 || $idTipoDte == 2 || $idTipoDte == 5)
        <tr>
            <td>Tributos: IVA</td>
            <td align="right">${{ number_format($datos->totalIVA ?? 0, 2, '.', ',') }}</td>
        </tr>
        @endif

        <tr>
            <td>Sub total</td>
            <td align="right">${{ number_format($datos->subTotal ?? 0, 2, '.', ',') }}</td>
        </tr>

        @if($idTipoDte == 1)
        <tr>
            <td>IVA Retenido</td>
            <td align="right">${{ number_format($datos->ivaRetenido1 ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>Retención de Renta</td>
            <td align="right">${{ number_format($datos->retencionRenta ?? 0, 2, '.', ',') }}</td>
        </tr>
        @elseif($idTipoDte == 2)
        <tr>
            <td>IVA Percibido</td>
            <td align="right">${{ number_format($datos->ivaPercibido1 ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>IVA Retenido</td>
            <td align="right">${{ number_format($datos->ivaRetenido1 ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>Retención de Renta</td>
            <td align="right">${{ number_format($datos->retencionRenta ?? 0, 2, '.', ',') }}</td>
        </tr>
        @elseif($idTipoDte == 4)
        <tr>
            <td>IVA Percibido</td>
            <td align="right">${{ number_format($datos->ivaPercibido1 ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>IVA Retenido</td>
            <td align="right">${{ number_format($datos->ivaRetenido1 ?? 0, 2, '.', ',') }}</td>
        </tr>
        @endif

        @if($idTipoDte == 9)
        <tr>
            <td>Fletes</td>
            <td align="right">${{ number_format($datos->fletes ?? 0, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>Seguros</td>
            <td align="right">${{ number_format($datos->seguros ?? 0, 2, '.', ',') }}</td>
        </tr>
        @endif

        @if($idTipoDte == 1 || $idTipoDte == 2)
        <tr>
            <td>Total Otros montos no afectos</td>
            <td align="right">${{ number_format(0, 2, '.', ',') }}</td>
        </tr>
        @endif

        @if($idTipoDte == 10)
        <tr>
            <td>Retencion de Renta</td>
            <td align="right">${{ number_format($datos->retencionRenta ?? 0, 2, '.', ',') }}</td>
        </tr>
        @endif

        <tr>
            <td>Total a pagar</td>
            <td align="right">${{ number_format($datos->totalPagar ?? 0, 2, '.', ',') }}</td>
        </tr>
    </table>

    @if(!empty($ambiente))
    <div style="text-align: center; margin-top: 20px; font-weight: bold; color: red;">
        {{ $ambiente }}
    </div>
    @endif
</body>
</html>
