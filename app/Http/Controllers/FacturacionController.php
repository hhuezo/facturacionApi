<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionController extends Controller
{

    public function generarFacturaElectronica($facturaId)
    {
        $factura = Factura::with([
            'empresa',
            'sucursal',
            'cliente',
            'tipoDocumentoTributario',
            'detalles.producto.unidadMedida'
        ])->findOrFail($facturaId);


        switch ((int) $factura->idTipoDte) {
            case 1: // Consumidor Final
                return $this->buildJsonConsumidorFinal($factura);

            case 2: // Crédito Fiscal
                return $this->buildJsonCreditoFiscal($factura);

            case 3: // Nota de remision
                // Implementación futura

            case 4: // Nota de credito
                $this->buildJsonNotaCredito($factura);

            case 5: // Nota Débito
                return $this->buildJsonNotaDebito($factura);

            case 9: // Factura Exportación
                return $this->buildJsonFacturaExportacion($factura);

            case 10: // Sujeto Excluido
                return $this->buildJsonSujetoExcluido($factura);


            default:
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error' => 'Tipo de DTE no soportado aún'
                ], 400);
        }
    }


    private function buildJsonConsumidorFinal(Factura $factura)
    {
        // 1. Cálculos generales
        $iva = 0;
        $subTotalGravada = 0;
        $subTotalExenta = 0;
        $descuentoTotal = 0;

        foreach ($factura->detalles as $detalle) {
            $descuentoTotal += $detalle->descuento;

            if ($detalle->excentas == 0) {
                $subTotalGravada += $detalle->gravadas;
                $iva += $detalle->iva;
            } else {
                $subTotalExenta += $detalle->excentas;
            }
        }

        $subTotalProductos = $subTotalGravada + $subTotalExenta;

        // 2. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 3. Fecha de emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 4. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => $factura->tipoDocumentoTributario->codigo,
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'nombreComercial' => $factura->empresa->nombreComercial,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo
            ],

            'receptor' => [
                'tipoDocumento' => null,
                'numDocumento' => null,
                'nrc' => null,
                'nombre' => $factura->cliente->nombreCliente,
                'direccion' => null,
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'cuerpoDocumento' => [],

            'resumen' => [
                'totalNoSuj' => 0,
                'totalExenta' => round($subTotalExenta, 2),
                'totalGravada' => round($subTotalGravada, 2),
                'subTotalVentas' => round($subTotalProductos, 2),
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'porcentajeDescuento' => 0,
                'totalDescu' => round($descuentoTotal, 2),
                'subTotal' => round($subTotalProductos, 2),
                'ivaRete1' => round($factura->ivaRetenido1, 2),
                'reteRenta' => round($factura->retencionRenta, 2),
                'montoTotalOperacion' => round($subTotalProductos, 2),
                'totalNoGravado' => 0,
                'totalPagar' => round($factura->totalPagar, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta,
                'totalIva' => round($iva, 2)
            ]
        ];

        // 5. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'tipoItem' => (int) $detalle->idTipoItem,
                'cantidad' => round($detalle->cantidad, 6),
                'codigo' => $detalle->producto->codigo,
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'descripcion' => $detalle->producto->nombre,
                'precioUni' => round($detalle->precioUnitario, 6),
                'montoDescu' => round($detalle->descuento, 6),
                'ventaNoSuj' => 0,
                'ventaExenta' => round($detalle->excentas, 6),
                'ventaGravada' => round($detalle->gravadas, 6),
                'ivaItem' => round($detalle->iva, 6),
                'noGravado' => 0
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }


    private function buildJsonCreditoFiscal(Factura $factura)
    {
        // 1. Cálculos generales
        $iva = 0;
        $subTotalGravada = 0;
        $subTotalExenta = 0;
        $descuentoTotal = 0;
        $esSoloExcento = true;

        foreach ($factura->detalles as $detalle) {
            $descuentoTotal += $detalle->descuento;

            if ($detalle->excentas == 0) {
                $subTotalGravada += $detalle->gravadas;
                $iva += $detalle->iva;
                $esSoloExcento = false;
            } else {
                $subTotalExenta += $detalle->excentas;
            }
        }

        $subTotalProductos = $subTotalGravada + $subTotalExenta;
        $montoTotalOperacion = $subTotalProductos + $factura->totalIVA;

        // 2. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 3. Tributos
        $tributos = null;
        if (!$esSoloExcento) {
            $tributos = [[
                'codigo' => '20',
                'descripcion' => 'Impuesto al Valor Agregado 13%',
                'valor' => round($factura->totalIVA, 2)
            ]];
        }

        // 4. Fecha emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 5. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => '03',
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'nombreComercial' => $factura->empresa->nombreComercial,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo
            ],

            'receptor' => [
                'nit' => $factura->cliente->numeroDocumento
                    ? str_replace('-', '', $factura->cliente->numeroDocumento)
                    : null,
                'nrc' => $factura->cliente->nrc
                    ? str_replace('-', '', $factura->cliente->nrc)
                    : null,
                'nombre' => $factura->cliente->nombreCliente,
                'direccion' => [
                    'departamento' => $factura->cliente->codigoDepartamento,
                    'municipio' => $factura->cliente->codigoMunicipio,
                    'complemento' => $factura->cliente->direccion
                ],
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'cuerpoDocumento' => [],

            'resumen' => [
                'totalNoSuj' => 0,
                'totalExenta' => round($subTotalExenta, 2),
                'totalGravada' => round($subTotalGravada, 2),
                'subTotalVentas' => round($subTotalProductos, 2),
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'porcentajeDescuento' => 0,
                'totalDescu' => round($descuentoTotal, 2),
                'tributos' => $tributos,
                'subTotal' => round($subTotalProductos, 2),
                'ivaPerci1' => round($factura->ivaPercibido1, 2),
                'ivaRete1' => round($factura->ivaRetenido1, 2),
                'reteRenta' => round($factura->retencionRenta, 2),
                'montoTotalOperacion' => round($montoTotalOperacion, 2),
                'totalNoGravado' => 0,
                'totalPagar' => round($factura->totalPagar, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta,
                'pagos' => [[
                    'codigo' => $factura->idTipoPago,
                    'montoPago' => round($factura->totalPagar, 2),
                    'referencia' => $factura->numeroAutorizacionTC,
                    'plazo' => $factura->idPlazo,
                    'periodo' => $factura->diasCredito
                ]],
                'numPagoElectronico' => null
            ]
        ];

        // 6. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $tributosItem = $detalle->excentas > 0 ? null : ['20'];

            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'tipoItem' => (int) $detalle->idTipoItem,
                'cantidad' => round($detalle->cantidad, 4),
                'codigo' => $detalle->producto->codigo,
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'descripcion' => $detalle->producto->nombre,
                'precioUni' => round($detalle->precioUnitario, 4),
                'montoDescu' => round($detalle->descuento, 4),
                'ventaNoSuj' => 0,
                'ventaExenta' => round($detalle->excentas, 4),
                'ventaGravada' => round($detalle->gravadas, 4),
                'tributos' => $tributosItem,
                'psv' => 0,
                'noGravado' => 0
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }


    private function buildJsonNotaCredito(Factura $factura)
    {
        // 1. Cálculos generales
        $iva = 0;
        $subTotalGravada = 0;
        $subTotalExenta = 0;
        $descuentoTotal = 0;

        foreach ($factura->detalles as $detalle) {
            $descuentoTotal += $detalle->descuento;

            if ($detalle->excentas == 0) {
                $subTotalGravada += $detalle->gravadas;
                $iva += $detalle->iva;
            } else {
                $subTotalExenta += $detalle->excentas;
            }
        }

        $subTotalProductos = $subTotalGravada + $subTotalExenta;

        // 2. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 3. Fecha emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 4. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => '05',
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'nombreComercial' => $factura->empresa->nombreComercial,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo
            ],

            'receptor' => [
                'nit' => $factura->cliente->numeroDocumento
                    ? str_replace('-', '', $factura->cliente->numeroDocumento)
                    : null,
                'nrc' => $factura->cliente->nrc
                    ? str_replace('-', '', $factura->cliente->nrc)
                    : null,
                'nombre' => $factura->cliente->nombreCliente,
                'direccion' => [
                    'departamento' => $factura->cliente->codigoDepartamento,
                    'municipio' => $factura->cliente->codigoMunicipio,
                    'complemento' => $factura->cliente->direccion
                ],
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'cuerpoDocumento' => [],

            'resumen' => [
                'totalNoSuj' => 0,
                'totalExenta' => round($subTotalExenta, 2),
                'subTotalVentas' => round($subTotalProductos, 2),
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'totalDescu' => round($descuentoTotal, 2),
                'tributos' => [[
                    'codigo' => '20',
                    'descripcion' => 'Impuesto al Valor Agregado 13%',
                    'valor' => round($factura->totalIVA, 2)
                ]],
                'subTotal' => round($subTotalProductos, 2),
                'ivaPerci1' => round($factura->ivaPercibido1, 2),
                'ivaRete1' => round($factura->ivaRetenido1, 2),
                'reteRenta' => round($factura->retencionRenta, 2),
                'montoTotalOperacion' => round($factura->totalPagar, 2),
                'totalGravada' => round($subTotalGravada, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta
            ],

            'extension' => [
                'nombEntrega' => $factura->usuario->nombre ?? null,
                'docuEntrega' => $factura->usuario->documento ?? null,
                'nombRecibe' => $factura->cliente->nombreCliente,
                'docuRecibe' => $factura->cliente->numeroDocumento,
                'observaciones' => $factura->observaciones
            ],

            'documentoRelacionado' => [[
                'tipoDocumento' => $factura->idDocumentoRelacionado,
                'tipoGeneracion' => 2,
                'numeroDocumento' => $factura->codigoGeneracionRela,
                'fechaEmision' => Carbon::parse($factura->fechaHoraEmisionRela)->format('Y-m-d')
            ]],

            'ventaTercero' => null
        ];

        // 5. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $tributosItem = $detalle->excentas > 0 ? null : ['20'];

            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'tipoItem' => (int) $detalle->idTipoItem,
                'numeroDocumento' => $factura->codigoGeneracionRela,
                'cantidad' => round($detalle->cantidad, 4),
                'codigo' => $detalle->producto->codigo,
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'descripcion' => $detalle->producto->nombre,
                'precioUni' => round($detalle->precioUnitario, 4),
                'montoDescu' => round($detalle->descuento, 4),
                'ventaNoSuj' => 0,
                'ventaExenta' => round($detalle->excentas, 4),
                'ventaGravada' => round($detalle->gravadas, 4),
                'tributos' => $tributosItem
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }


    private function buildJsonNotaDebito(Factura $factura)
    {
        // 1. Cálculos generales
        $iva = 0;
        $subTotalGravada = 0;
        $subTotalExenta = 0;
        $descuentoTotal = 0;

        foreach ($factura->detalles as $detalle) {
            $descuentoTotal += $detalle->descuento;

            if ($detalle->excentas == 0) {
                $subTotalGravada += $detalle->gravadas;
                $iva += $detalle->iva;
            } else {
                $subTotalExenta += $detalle->excentas;
            }
        }

        $subTotalProductos = $subTotalGravada + $subTotalExenta;

        // 2. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 3. Fecha emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 4. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => '06',
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'nombreComercial' => $factura->empresa->nombreComercial,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo
            ],

            'receptor' => [
                'nit' => $factura->cliente->numeroDocumento
                    ? str_replace('-', '', $factura->cliente->numeroDocumento)
                    : null,
                'nrc' => $factura->cliente->nrc
                    ? str_replace('-', '', $factura->cliente->nrc)
                    : null,
                'nombre' => $factura->cliente->nombreCliente,
                'direccion' => [
                    'departamento' => $factura->cliente->codigoDepartamento,
                    'municipio' => $factura->cliente->codigoMunicipio,
                    'complemento' => $factura->cliente->direccion
                ],
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'cuerpoDocumento' => [],

            'resumen' => [
                'totalNoSuj' => 0,
                'totalExenta' => round($subTotalExenta, 2),
                'subTotalVentas' => round($subTotalProductos, 2),
                'descuNoSuj' => 0,
                'descuExenta' => 0,
                'descuGravada' => 0,
                'totalDescu' => round($descuentoTotal, 2),
                'tributos' => [[
                    'codigo' => '20',
                    'descripcion' => 'Impuesto al Valor Agregado 13%',
                    'valor' => round($factura->totalIVA, 2)
                ]],
                'subTotal' => round($subTotalProductos, 2),
                'ivaPerci1' => round($factura->ivaPercibido1, 2),
                'ivaRete1' => round($factura->ivaRetenido1, 2),
                'reteRenta' => round($factura->retencionRenta, 2),
                'montoTotalOperacion' => round($factura->totalPagar, 2),
                'totalGravada' => round($subTotalGravada, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta,
                'numPagoElectronico' => null
            ],

            'extension' => [
                'nombEntrega' => $factura->usuario->nombre ?? null,
                'docuEntrega' => $factura->usuario->documento ?? null,
                'nombRecibe' => $factura->cliente->nombreCliente,
                'docuRecibe' => $factura->cliente->numeroDocumento,
                'observaciones' => $factura->observaciones
            ],

            'documentoRelacionado' => [[
                'tipoDocumento' => $factura->idDocumentoRelacionado,
                'tipoGeneracion' => 2,
                'numeroDocumento' => $factura->codigoGeneracionRela,
                'fechaEmision' => Carbon::parse($factura->fechaHoraEmisionRela)->format('Y-m-d')
            ]],

            'ventaTercero' => null
        ];

        // 5. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $tributosItem = $detalle->excentas > 0 ? null : ['20'];

            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'tipoItem' => (int) $detalle->idTipoItem,
                'numeroDocumento' => $factura->codigoGeneracionRela,
                'cantidad' => round($detalle->cantidad, 4),
                'codigo' => $detalle->producto->codigo,
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'descripcion' => $detalle->producto->nombre,
                'precioUni' => round($detalle->precioUnitario, 4),
                'montoDescu' => round($detalle->descuento, 4),
                'ventaNoSuj' => 0,
                'ventaExenta' => round($detalle->excentas, 4),
                'ventaGravada' => round($detalle->gravadas, 4),
                'tributos' => $tributosItem
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }

    private function buildJsonFacturaExportacion(Factura $factura)
    {
        // 1. Cálculos generales
        $iva = 0;
        $subTotalGravada = 0;
        $subTotalExenta = 0;
        $descuentoTotal = 0;

        foreach ($factura->detalles as $detalle) {
            $descuentoTotal += $detalle->descuento;

            if ($detalle->excentas == 0) {
                $subTotalGravada += $detalle->gravadas;
                $iva += $detalle->iva;
            } else {
                $subTotalExenta += $detalle->excentas;
            }
        }

        $subTotalProductos = $subTotalGravada + $subTotalExenta;

        // 2. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 3. Fecha emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 4. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => '09',
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContingencia' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'nombreComercial' => $factura->empresa->nombreComercial,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo,
                'tipoItemExpor' => (int) $factura->idTipoItem,
                'recintoFiscal' => $factura->idRecinto,
                'regimen' => $factura->idRegimen
            ],

            'receptor' => [
                'nombre' => $factura->cliente->nombreCliente,
                'codPais' => $factura->idPaisExportacion,
                'nombrePais' => $factura->cliente->pais->nombre ?? null,
                'complemento' => $factura->cliente->direccion,
                'tipoDocumento' => $factura->cliente->idTipoDocumentoIdentidad ?? null,
                'numDocumento' => $factura->cliente->numeroDocumento,
                'tipoPersona' => $factura->cliente->idTipoPersona ?? null,
                'descActividad' => $factura->cliente->actividadEconomica->nombre ?? null,
                'nombreComercial' => $factura->cliente->nombreCliente,
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'otrosDocumentos' => null,
            'ventaTercero' => null,
            'cuerpoDocumento' => [],

            'resumen' => [
                'totalGravada' => round($subTotalGravada, 2),
                'totalDescu' => round($descuentoTotal, 2),
                'descuento' => round($descuentoTotal, 2),
                'porcentajeDescuento' => 0,
                'seguro' => round($factura->seguros, 2),
                'flete' => round($factura->fletes, 2),
                'montoTotalOperacion' => round($factura->totalPagar, 2),
                'totalNoGravado' => 0,
                'totalPagar' => round($factura->totalPagar, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta,
                'numPagoElectronico' => null,
                'codIncoterms' => $factura->idIncoterms,
                'descIncoterms' => $factura->incoterm->nombre ?? null,
                'observaciones' => null,
                'pagos' => [[
                    'codigo' => $factura->idTipoPago,
                    'montoPago' => round($factura->totalPagar, 2),
                    'referencia' => $factura->numeroAutorizacionTC,
                    'plazo' => $factura->idPlazo,
                    'periodo' => $factura->diasCredito
                ]]
            ],

            'apendice' => null
        ];

        // 5. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'codigo' => $detalle->producto->codigo,
                'descripcion' => $detalle->producto->nombre,
                'cantidad' => round($detalle->cantidad, 6),
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'precioUni' => round($detalle->precioUnitario, 6),
                'montoDescu' => round($detalle->descuento, 6),
                'ventaGravada' => round($detalle->gravadas, 6),
                'tributos' => null,
                'noGravado' => 0
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }

    private function buildJsonSujetoExcluido(Factura $factura)
    {
        // 1. Dirección del sujeto excluido
        $direccion = null;
        if (
            !empty($factura->cliente->codigoDepartamento) &&
            !empty($factura->cliente->codigoMunicipio) &&
            !empty($factura->cliente->direccion)
        ) {
            $direccion = [
                'departamento' => $factura->cliente->codigoDepartamento,
                'municipio' => $factura->cliente->codigoMunicipio,
                'complemento' => $factura->cliente->direccion,
            ];
        }

        // 2. Totales
        $totalItems = 0;
        $descuentoTotal = 0;

        foreach ($factura->detalles as $detalle) {
            $totalItems += $detalle->gravadas;
            $descuentoTotal += $detalle->descuento;
        }

        // 3. Total en letras
        $totalLetras = $this->totalEnLetras((float) $factura->totalPagar);

        // 4. Fecha emisión
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // 5. JSON DTE
        $dteJson = [
            'identificacion' => [
                'version' => (int) $factura->versionJson,
                'ambiente' => $factura->idAmbiente,
                'tipoDte' => '10',
                'numeroControl' => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo' => (int) $factura->idTipoFacturacion,
                'tipoOperacion' => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin' => $factura->motivoContingencia,
                'fecEmi' => $fecha->format('Y-m-d'),
                'horEmi' => $fecha->format('H:i:s'),
                'tipoMoneda' => 'USD'
            ],

            'emisor' => [
                'nit' => str_replace('-', '', $factura->empresa->nit),
                'nrc' => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre' => $factura->empresa->nombre,
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio' => $factura->sucursal->municipio->codigo,
                    'complemento' => $factura->sucursal->direccion,
                ],
                'telefono' => $factura->sucursal->telefono,
                'correo' => $factura->sucursal->correo
            ],

            'sujetoExcluido' => [
                'tipoDocumento' => $factura->cliente->idTipoDocumentoIdentidad ?? null,
                'numDocumento' => $factura->cliente->numeroDocumento
                    ? str_replace('-', '', $factura->cliente->numeroDocumento)
                    : null,
                'nombre' => $factura->cliente->nombreCliente,
                'codActividad' => $factura->cliente->codigoActividadEconomica ?? null,
                'descActividad' => $factura->cliente->nombreActividadEconomica ?? null,
                'direccion' => $direccion,
                'telefono' => $factura->cliente->telefono,
                'correo' => $factura->cliente->correo
            ],

            'cuerpoDocumento' => [],

            'resumen' => [
                'totalCompra' => round($totalItems, 2),
                'descu' => 0,
                'totalDescu' => round($descuentoTotal, 2),
                'subTotal' => round($totalItems, 2),
                'ivaRete1' => round($factura->ivaRetenido1, 2),
                'reteRenta' => round($factura->retencionRenta, 2),
                'totalPagar' => round($factura->totalPagar, 2),
                'totalLetras' => $totalLetras,
                'condicionOperacion' => (int) $factura->idCondicionVenta,
                'pagos' => [[
                    'codigo' => $factura->idTipoPago,
                    'montoPago' => round($factura->totalPagar, 2),
                    'referencia' => $factura->numeroAutorizacionTC,
                    'plazo' => $factura->idPlazo,
                    'periodo' => $factura->diasCredito
                ]],
                'observaciones' => $factura->observaciones
            ],

            'apendice' => null
        ];

        // 6. Cuerpo documento
        $contador = 1;

        foreach ($factura->detalles as $detalle) {
            $dteJson['cuerpoDocumento'][] = [
                'numItem' => $contador++,
                'tipoItem' => (int) $detalle->idTipoItem,
                'cantidad' => round($detalle->cantidad, 6),
                'codigo' => $detalle->producto->codigo,
                'uniMedida' => (int) $detalle->unidadMedida->codigo,
                'descripcion' => $detalle->producto->nombre,
                'precioUni' => round($detalle->precioUnitario, 6),
                'montoDescu' => round($detalle->descuento, 6),
                'compra' => round($detalle->gravadas, 6),
            ];
        }

        return response()->json([
            'mensaje' => 'EXITO',
            'numeroControl' => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json' => $dteJson
        ]);
    }


    public function regenerarJson(int $facturaId)
    {
        try {

            $factura = Factura::with([
                'empresa',
                'sucursal.departamento',
                'sucursal.municipio',
                'cliente',
                'detalles.producto.unidadMedida'
            ])->findOrFail($facturaId);

            // 1. Reconstruir el JSON según el tipo de DTE (NO se transmite)
            switch ((int) $factura->idTipoDte) {

                case 1:
                    $jsonResponse = $this->buildJsonConsumidorFinal($factura);
                    break;

                case 2:
                    $jsonResponse = $this->buildJsonCreditoFiscal($factura);
                    break;

                case 5:
                    $jsonResponse = $this->buildJsonNotaCredito($factura);
                    break;

                case 6:
                    $jsonResponse = $this->buildJsonNotaDebito($factura);
                    break;

                case 9:
                    $jsonResponse = $this->buildJsonFacturaExportacion($factura);
                    break;

                case 10:
                    $jsonResponse = $this->buildJsonSujetoExcluido($factura);
                    break;

                default:
                    return response()->json([
                        'mensaje' => 'ERROR',
                        'error' => 'Tipo de DTE no soportado para regeneración'
                    ], 400);
            }

            // 2. Extraer JSON reconstruido
            $jsonReconstruido = $jsonResponse->getData(true)['json'];

            // 3. Obtener firma existente (NO se vuelve a firmar)
            $firma = $this->obtenerFirma($factura->id);

            if ($firma->mensaje !== 'EXITO' || empty($firma->firma)) {
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error' => 'No existe firma registrada para este DTE'
                ], 422);
            }

            // 4. Obtener respuesta y sello MH existente
            $sello = $this->obtenerSello($factura->id);

            if ($sello->mensaje !== 'EXITO' || empty($sello->respuesta)) {
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error' => 'No existe respuesta MH para este DTE'
                ], 422);
            }

            // 5. Guardar nuevamente el JSON físico con el sello existente
            app('App\Services\JsonFisicoService')->editarJsonHacienda(
                $factura->codigoGeneracion,
                $sello->respuesta,
                $factura->idEmpresa,
                $factura->fechaHoraEmision,
                $jsonReconstruido
            );

            // 6. Respuesta administrativa
            return response()->json([
                'mensaje' => 'EXITO',
                'codigoGeneracion' => $factura->codigoGeneracion,
                'idEmpresa' => $factura->idEmpresa,
                'fechaEmision' => $factura->fechaHoraEmision->format('Y-m'),
                'nota' => 'JSON regenerado usando firma y sello existentes (sin reenvío a MH)'
            ]);
        } catch (\Throwable $e) {

            Log::error('Error al regenerar JSON DTE', [
                'factura_id' => $facturaId,
                'exception' => $e
            ]);

            return response()->json([
                'mensaje' => 'ERROR',
                'error' => 'No se pudo regenerar el JSON',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }



    private function obtenerFirma(int $facturaId)
    {
        try {
            $registro = DB::table('facturacion_mh_firmador')
                ->select('respuestaBody')
                ->where('idEncabezado', $facturaId)
                ->first();

            if (!$registro) {
                return (object) [
                    'mensaje' => 'ERROR',
                    'firma' => null
                ];
            }

            return (object) [
                'mensaje' => 'EXITO',
                'firma' => $registro->respuestaBody
            ];
        } catch (\Throwable $e) {

            Log::error('ERROR AL OBTENER LA FIRMA DEL DTE', [
                'factura_id' => $facturaId,
                'exception' => $e
            ]);

            return (object) [
                'mensaje' => 'ERROR',
                'firma' => null
            ];
        }
    }

    private function obtenerSello(int $facturaId)
    {
        try {
            $registro = DB::table('facturacion_mh_dte_respuestas')
                ->select('jsonCompleto', 'selloRecibido')
                ->where('idEncabezado', $facturaId)
                ->first();

            if (!$registro) {
                return (object) [
                    'mensaje' => 'ERROR',
                    'respuesta' => null,
                    'selloRecibido' => null
                ];
            }

            return (object) [
                'mensaje' => 'EXITO',
                'respuesta' => $registro->jsonCompleto,
                'selloRecibido' => $registro->selloRecibido
            ];
        } catch (\Throwable $e) {

            Log::error('ERROR AL OBTENER SELLO MH DEL DTE', [
                'factura_id' => $facturaId,
                'exception' => $e
            ]);

            return (object) [
                'mensaje' => 'ERROR',
                'respuesta' => null,
                'selloRecibido' => null
            ];
        }
    }



    private function totalEnLetras(float $monto): string
    {
        $monto = number_format($monto, 2, '.', '');
        [$entero, $decimal] = explode('.', $monto);
        $decimal = str_pad($decimal, 2, '0', STR_PAD_RIGHT);

        $unidades = [
            '',
            'UNO',
            'DOS',
            'TRES',
            'CUATRO',
            'CINCO',
            'SEIS',
            'SIETE',
            'OCHO',
            'NUEVE',
            'DIEZ',
            'ONCE',
            'DOCE',
            'TRECE',
            'CATORCE',
            'QUINCE',
            'DIECISÉIS',
            'DIECISIETE',
            'DIECIOCHO',
            'DIECINUEVE',
            'VEINTE'
        ];

        $decenas = [
            2 => 'VEINTI',
            3 => 'TREINTA',
            4 => 'CUARENTA',
            5 => 'CINCUENTA',
            6 => 'SESENTA',
            7 => 'SETENTA',
            8 => 'OCHENTA',
            9 => 'NOVENTA'
        ];

        $centenas = [
            1 => 'CIENTO',
            2 => 'DOSCIENTOS',
            3 => 'TRESCIENTOS',
            4 => 'CUATROCIENTOS',
            5 => 'QUINIENTOS',
            6 => 'SEISCIENTOS',
            7 => 'SETECIENTOS',
            8 => 'OCHOCIENTOS',
            9 => 'NOVECIENTOS'
        ];

        $convertir = function ($numero) use (&$convertir, $unidades, $decenas, $centenas) {

            if ($numero == 0) return 'CERO';
            if ($numero < 21) return $unidades[$numero];

            if ($numero < 100) {
                $d = intval($numero / 10);
                $u = $numero % 10;
                return $numero <= 29
                    ? $decenas[$d] . $unidades[$u]
                    : $decenas[$d] . ($u ? ' Y ' . $unidades[$u] : '');
            }

            if ($numero == 100) return 'CIEN';

            if ($numero < 1000) {
                $c = intval($numero / 100);
                return $centenas[$c] . ' ' . $convertir($numero % 100);
            }

            if ($numero < 1000000) {
                $m = intval($numero / 1000);
                return ($m == 1 ? 'MIL' : $convertir($m) . ' MIL') . ' ' . $convertir($numero % 1000);
            }

            $millones = intval($numero / 1000000);
            return ($millones == 1 ? 'UN MILLÓN' : $convertir($millones) . ' MILLONES')
                . ' ' . $convertir($numero % 1000000);
        };

        $texto = trim($convertir((int)$entero));

        return "{$texto} DÓLARES CON {$decimal}/100 CENTAVOS";
    }


    public function crearEventoContingencia(int $idEmpresa, int $idContingencia)
    {
        try {

            // 1. Obtener datos del evento
            $datos = DB::table('facturacion_eventos_contingencia')
                ->where('id', $idContingencia)
                ->first();

            if (!$datos) {
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error' => 'Evento de contingencia no encontrado'
                ], 404);
            }

            // 2. Obtener DTE involucrados
            $dtes = DB::table('facturacion_eventos_contingencia_dtes')
                ->where('idEventoContingencia', $idContingencia)
                ->get();

            // 3. Obtener datos de la empresa en sesión (legacy)
            $empresa = DB::table('general_datos_empresa')
                ->where('id', $idEmpresa)
                ->first();

            if (!$empresa) {
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error' => 'Empresa no encontrada'
                ], 404);
            }

            $hoy = now();

            // 4. Construcción del JSON del evento
            $datosJson = [
                'nit' => str_replace('-', '', $empresa->nit),
                'activo' => true,
                'passwordPri' => $empresa->clavePrivada ?? null,
                'dteJson' => [
                    'identificacion' => [
                        'version' => 3,
                        'ambiente' => $datos->codigoAmbiente,
                        'codigoGeneracion' => $datos->codigoGeneracionEvento,
                        'fTransmision' => $hoy->format('Y-m-d'),
                        'hTransmision' => $hoy->format('H:i:s'),
                    ],

                    'emisor' => [
                        'nit' => str_replace('-', '', $empresa->nit),
                        'nombre' => $empresa->nombre,
                        'nombreResponsable' => $datos->nombreUsuarioResponsable,
                        'tipoDocResponsable' => $datos->codigoTipoDocumentoIdentidad,
                        'numeroDocResponsable' => str_replace('-', '', $datos->numeroDocumentoIdentidad),
                        'tipoEstablecimiento' => $datos->codigoTipoEstablecimiento,
                        'telefono' => $datos->telefonoSucursal,
                        'correo' => $datos->correoSucursal,
                        'codEstableMH' => null,
                        'codPuntoVenta' => null,
                    ],

                    'detalleDTE' => [],

                    'motivo' => [
                        'fInicio' => Carbon::parse($datos->fechaHoraInicioContingencia)->format('Y-m-d'),
                        'fFin' => Carbon::parse($datos->fechaHoraFinContingencia)->format('Y-m-d'),
                        'hInicio' => Carbon::parse($datos->fechaHoraInicioContingencia)->format('H:i:s'),
                        'hFin' => Carbon::parse($datos->fechaHoraFinContingencia)->format('H:i:s'),
                        'tipoContingencia' => 1,
                        'motivoContingencia' => $datos->motivocontingencia,
                    ],
                ],
            ];

            // 5. Agregar DTE afectados
            $contador = 1;
            foreach ($dtes as $dte) {
                $datosJson['dteJson']['detalleDTE'][] = [
                    'noItem' => $contador++,
                    'codigoGeneracion' => $dte->codigoGeneracion,
                    'tipoDoc' => $dte->codigoTipoDocumento,
                ];
            }

            // 6. Guardar JSON físico
            app('App\Services\JsonFisicoService')->crearJsonParteEncabezadoDetalle(
                $datos->codigoGeneracionEvento,
                json_encode($datosJson['dteJson']),
                $idEmpresa,
                $hoy
            );

            return response()->json([
                'mensaje' => 'EXITO',
                'codigoGeneracionEvento' => $datos->codigoGeneracionEvento,
                'fechaHoraEmision' => $hoy->format('Y-m-d H:i:s'),
                'json' => $datosJson
            ]);
        } catch (\Throwable $e) {

            Log::error('Error al crear evento de contingencia', [
                'idEmpresa' => $idEmpresa,
                'idContingencia' => $idContingencia,
                'exception' => $e
            ]);

            return response()->json([
                'mensaje' => 'ERROR',
                'error' => 'No se pudo crear el evento de contingencia',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
}
