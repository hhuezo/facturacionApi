<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, monospace;
            font-size: 10px;
            color: #000;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 2px 0;
            vertical-align: top;
        }
    </style>
</head>
<body>

<div class="center bold">
    {{ $factura->empresa->nombre }}
</div>

<div class="center">
    {{ $factura->empresa->direccion ?? '' }}
</div>

<div class="center">
    NIT: {{ $factura->empresa->nit ?? '' }}
</div>

<div class="line"></div>

<div class="center bold">
    DOCUMENTO TRIBUTARIO ELECTRÓNICO<br>
    {{ strtoupper($factura->tipoDocumentoTributario->nombre ?? '') }}
</div>

<div class="line"></div>

<table>
    <tr>
        <td>Generación:</td>
        <td class="right">{{ $factura->codigoGeneracion }}</td>
    </tr>
    <tr>
        <td>Control:</td>
        <td class="right">{{ $factura->numeroControl }}</td>
    </tr>
    <tr>
        <td>Fecha:</td>
        <td class="right">
            {{ $factura->fechaHoraEmision->format('d-m-Y H:i') }}
        </td>
    </tr>
    <tr>
        <td>Caja:</td>
        <td class="right">
            {{ $factura->sucursal->nombreSucursal ?? 'Caja' }}
        </td>
    </tr>
</table>

<div class="line"></div>

<div class="bold">CLIENTE:</div>
<div>{{ $factura->cliente->nombreCliente ?? '' }}</div>
<div>NIT: {{ $factura->cliente->numeroDocumento ?? '' }}</div>
<div>{{ $factura->cliente->direccion ?? '' }}</div>

<div class="line"></div>

<table>
    <thead>
        <tr class="bold">
            <td>CANT</td>
            <td>DESCRIPCIÓN</td>
            <td class="right">TOTAL</td>
        </tr>
    </thead>
    <tbody>
        @foreach($factura->detalles as $item)
        <tr>
            <td>{{ number_format($item->cantidad, 2) }}</td>
            <td>
                {{ $item->producto->nombre ?? 'Servicio' }}
            </td>
            <td class="right">
                ${{ number_format($item->gravadas + $item->iva, 2) }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="line"></div>

<table>
    <tr>
        <td>Sumatoria Ventas:</td>
        <td class="right">${{ number_format($factura->subTotal, 2) }}</td>
    </tr>
    <tr>
        <td>IVA (13%):</td>
        <td class="right">${{ number_format($factura->totalIVA, 2) }}</td>
    </tr>
    <tr class="bold">
        <td>Total a Pagar:</td>
        <td class="right">${{ number_format($factura->totalPagar, 2) }}</td>
    </tr>
</table>

<div class="line"></div>

<div class="center">
    {{ strtoupper($factura->totalPagar) }} USD
</div>

<div class="center" style="margin-top:6px;">
    ¡Gracias por preferirnos!
</div>

</body>
</html>
