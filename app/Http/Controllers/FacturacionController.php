<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaActividadEconomica;
use App\Models\EmpresaConfigTransmisionDte;
use App\Models\EmpresaPuntoVenta;
use App\Models\EmpresaSucursal;
use App\Models\Factura;
use App\Models\mh\TipoDocumentoTributario;
use App\Traits\MhEndpointsTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class FacturacionController extends Controller
{

    use MhEndpointsTrait;

    public function generarFacturaElectronica(int $facturaId)
    {
        $factura = Factura::with([
            'empresa',
            'sucursal.departamento',
            'sucursal.municipio',
            'cliente.tipoDocumento',
            'tipoDocumentoTributario',
            'detalles.producto.unidadMedida',
            'ambiente',
            'ambiente',
            'puntoVenta',
            'usuario',
        ])->findOrFail($facturaId);




        $datosControl = $this->crearNumeroControl(
            $factura->idEmpresa,
            $factura->idSucursal,
            $factura->idPuntoVenta,
            $factura->idTipoDte
        );

        if ($datosControl->mensaje !== 'EXITO') {
            throw new \Exception($datosControl->excepcion);
        }

        $factura->idNumeroControl = $datosControl->idNumeroControl;
        $factura->numeroControl   = $datosControl->numeroControl;
        $factura->save();

        // ============================
        // 1. GENERAR JSON SEGÚN TIPO DTE
        // ============================
        switch ((int) $factura->idTipoDte) {

            case 1: // Consumidor Final (01)
                $jsonResponse = $this->buildJsonConsumidorFinal($factura);
                //return response()->json($jsonResponse);
                break;

            case 2: // Crédito Fiscal (03)
                $jsonResponse = $this->buildJsonCreditoFiscal($factura);
                break;

            case 5: // Nota de Crédito (05)
                $jsonResponse = $this->buildJsonNotaCredito($factura);
                break;

            case 6: // Nota Débito (06)
                $jsonResponse = $this->buildJsonNotaDebito($factura);
                break;

            case 9: // Factura Exportación (09)
                $jsonResponse = $this->buildJsonFacturaExportacion($factura);
                break;

            case 10: // Sujeto Excluido (10)
                $jsonResponse = $this->buildJsonSujetoExcluido($factura);
                break;

            default:
                return response()->json([
                    'mensaje' => 'ERROR',
                    'error'   => 'Tipo de DTE no soportado'
                ], 400);
        }

        // ============================
        // 2. VALIDAR RESPUESTA JSON
        // ============================
        $jsonResult = $jsonResponse->getData(true);

        if (($jsonResult['mensaje'] ?? 'ERROR') !== 'EXITO') {
            return response()->json($jsonResult, 500);
        }

        // ============================
        // 3. GUARDAR JSON BASE (ARCHIVO FÍSICO)
        // ============================
        $this->crearJsonParteEncabezadoDetalle(
            $factura->codigoGeneracion,
            $jsonResult['json'],              // ARRAY del DTE
            $factura->idEmpresa,
            $factura->fechaHoraEmision
        );

        // ============================
        // 4. ENCODE JSON (PARA FIRMA)
        // ============================
        $jsonEncode = json_encode($jsonResult['json'], JSON_UNESCAPED_UNICODE);


        if ($jsonEncode === false) {
            return response()->json([
                'mensaje' => 'ERROR',
                'error'   => 'Error al codificar JSON para firma'
            ], 500);
        }

        // ============================
        // 5. FIRMAR JSON
        // ============================
        $firma = $this->firmarJson(
            $jsonResult['json'],
            $factura->id,
            $factura->idEmpresa,
            $factura->fechaHoraEmision
        );

        if (($firma->mensaje ?? 'ERROR') !== 'EXITO') {
            return response()->json($firma, 500);
        }

        // ============================
        // 6. GUARDAR FIRMA EN EL MISMO ARCHIVO
        // ============================
        $this->editarJsonFirma(
            $factura->codigoGeneracion,
            $firma->documento,               // JWT / firma
            $factura->idEmpresa,
            $factura->fechaHoraEmision
        );

        // ============================
        // 7. VALIDAR CONTINGENCIA
        // ============================
        $contingencia = $this->validarSiDteExisteEnContingenciaYsiEstaActiva($factura->id);

        if (
            $contingencia->mensaje !== 'CONTINGENCIA_PROCESADA'
            && $contingencia->mensaje !== 'NO_EXISTE_CONTINGENCIA'
        ) {
            $contingencia->idEncabezado = $factura->id;
            return response()->json($contingencia, 409);
        }

        // ============================
        // 8. RECEPCIÓN DTE (HACIENDA)
        // ============================
        $respuestaHacienda = $this->recepciondte(
            $factura->id,
            $factura->idEmpresa,
            $factura->idSucursal,
            $jsonEncode,
            $firma->documento,
            $factura->fechaHoraEmision
        );

        if (($respuestaHacienda->mensaje ?? 'ERROR') !== 'EXITO') {
            return response()->json($respuestaHacienda, 500);
        }

        // ============================
        // 9. RESPUESTA FINAL
        // ============================
        return response()->json([
            'mensaje'          => 'EXITO',
            'estado'           => $respuestaHacienda->estado ?? null,
            'numeroControl'    => $factura->numeroControl,
            'codigoGeneracion' => $respuestaHacienda->codigoGeneracion ?? $factura->codigoGeneracion,
            'selloRecibido'    => $respuestaHacienda->selloRecibido ?? null,
            'idEncabezado'     => $factura->id
        ]);
    }






    private function buildJsonConsumidorFinal(Factura $factura)
    {
        $fecha = Carbon::parse($factura->fechaHoraEmision);

        // ============================
        // 1. ACTIVIDAD ECONÓMICA
        // ============================
        $actividad = EmpresaActividadEconomica::where('idEmpresa', $factura->idEmpresa)
            ->where('actividadPrincipal', 'S')
            ->first();

        // ============================
        // 2. RECEPTOR (DUI / NIT)
        // ============================
        $tipoDoc = trim($factura->cliente->tipoDocumento->codigo ?? '');
        $numDoc  = preg_replace('/[^0-9]/', '', (string)($factura->cliente->numeroDocumento ?? ''));

        $receptorNumDocumento = null;

        // DUI (tipo 13) -> ########-#
        if ($tipoDoc === '13' && strlen($numDoc) === 9) {
            $receptorNumDocumento = substr($numDoc, 0, 8) . '-' . substr($numDoc, 8, 1);
        }

        // NIT (tipo 36) -> 14 dígitos sin guiones
        if ($tipoDoc === '36' && strlen($numDoc) === 14) {
            $receptorNumDocumento = $numDoc;
        }

        // Helpers para formateo MH
        $f6 = function ($n) {
            return (float) number_format((float)$n, 6, '.', '');
        };
        $f2 = function ($n) {
            return (float) number_format((float)$n, 2, '.', '');
        };

        // ============================
        // 3. JSON BASE
        // ============================
        $dteJson = [
            'identificacion' => [
                'version'          => (int) $factura->versionJson,
                'ambiente'         => $factura->ambiente->codigo,
                'tipoDte'          => $factura->tipoDocumentoTributario->codigo,
                'numeroControl'    => $factura->numeroControl,
                'codigoGeneracion' => $factura->codigoGeneracion,
                'tipoModelo'       => (int) $factura->idTipoFacturacion,
                'tipoOperacion'    => (int) $factura->idTipoTransmision,
                'tipoContingencia' => $factura->idTipoContingencia,
                'motivoContin'     => $factura->motivoContingencia,
                'fecEmi'           => $fecha->format('Y-m-d'),
                'horEmi'           => $fecha->format('H:i:s'),
                'tipoMoneda'       => 'USD',
            ],

            'emisor' => [
                'nit'                 => str_replace('-', '', $factura->empresa->nit),
                'nrc'                 => str_replace('-', '', $factura->empresa->numeroIVA),
                'nombre'              => $factura->empresa->nombre,
                'nombreComercial'     => $factura->empresa->nombreComercial,
                'codActividad'        => trim($actividad->actividadEconomica->codigoActividad ?? ''),
                'descActividad'       => trim($actividad->actividadEconomica->nombreActividad ?? ''),
                'tipoEstablecimiento' => $factura->sucursal->tipoEstablecimiento->codigo ?? '',
                'direccion' => [
                    'departamento' => $factura->sucursal->departamento->codigo,
                    'municipio'    => $factura->sucursal->municipio->codigo,
                    'complemento'  => $factura->sucursal->direccion,
                ],
                'telefono'        => $factura->sucursal->telefono,
                'correo'          => $factura->sucursal->correo,
                'codEstableMH'    => $factura->sucursal->codigoEstablecimiento,
                'codEstable'      => $factura->sucursal->codigoEstablecimiento,
                'codPuntoVentaMH' => $factura->puntoVenta->codigoPuntoVentaMh ?? null,
                'codPuntoVenta'   => blank($factura->puntoVenta->codigoPuntoVentaInterno ?? null)
                    ? null
                    : $factura->puntoVenta->codigoPuntoVentaInterno,
            ],

            'receptor' => [
                'tipoDocumento' => in_array($tipoDoc, ['13', '36']) ? $tipoDoc : null,
                'numDocumento'  => $receptorNumDocumento,
                'nrc'           => null,
                'nombre'        => $factura->cliente->nombreCliente ?? '',
                'codActividad'  => null,
                'descActividad' => null,
                'direccion'     => null,
                'telefono'      => blank($factura->cliente->telefono) ? null : $factura->cliente->telefono,
                'correo'        => blank($factura->cliente->correo) ? null : $factura->cliente->correo,
            ],

            'ventaTercero'         => null,
            'documentoRelacionado' => null,
            'otrosDocumentos'      => null,
            'apendice'             => null,

            'extension' => [
                'nombEntrega'   => $factura->usuario->nombre ?? null,
                'docuEntrega'   => $factura->usuario->numeroDocumentoIdentidad ?? null,
                'nombRecibe'    => $factura->cliente->nombreCliente ?? null,
                'docuRecibe'    => $factura->cliente->numeroDocumento ?? null,
                'observaciones' => $factura->observaciones ?? null,
                'placaVehiculo' => null,
            ],

            'cuerpoDocumento' => [],
            'resumen'         => [],
        ];

        // ============================
        // 4. CUERPO DOCUMENTO (CF)
        // REGLA: precioUni trae IVA => ventaGravada = TOTAL ITEM (con IVA)
        // ivaItem = ventaGravada * 13/113
        // ============================
        $i = 1;

        $sumVentaGravada = 0.0; // OJO: aquí será TOTAL con IVA
        $sumVentaExenta  = 0.0;
        $sumIva          = 0.0;
        $sumDescu        = 0.0;

        foreach ($factura->detalles as $detalle) {

            $cantidad  = $f6($detalle->cantidad ?? 0);
            $precioUni = $f6($detalle->precioUnitario ?? 0);
            $descu     = $f6($detalle->descuento ?? 0);

            // Total bruto (con IVA porque precioUni lo trae)
            $totalBruto = $f6($cantidad * $precioUni);

            // Total neto del ítem (después del descuento)
            $totalNeto = $f6($totalBruto - $descu);
            if ($totalNeto < 0) $totalNeto = 0.0;

            $esExento = ((float)($detalle->excentas ?? 0)) > 0;

            $ventaNoSuj   = 0.0;
            $ventaExenta  = 0.0;
            $ventaGravada = 0.0;
            $ivaItem      = 0.0;

            if ($esExento) {
                // Exento: el total va como ventaExenta
                $ventaExenta = $totalNeto;
                $ivaItem = 0.0;
            } else {
                // Gravado CF: ventaGravada ES EL TOTAL del ítem (con IVA)
                $ventaGravada = $totalNeto;

                // IVA MH desde el total
                $ivaItem = $f6($ventaGravada * 13 / 113);
            }

            $descripcion = (string) ($detalle->producto->nombre ?? '');
            $descripcion = preg_replace('/[\x00-\x1F\x7F]/u', '', $descripcion);
            $descripcion = trim($descripcion);

            $dteJson['cuerpoDocumento'][] = [
                'numItem'         => $i++,
                'tipoItem'        => (int) $detalle->idTipoItem,
                'numeroDocumento' => null,
                'cantidad'        => $cantidad,
                'codigo'          => $detalle->producto->codigo,
                'codTributo'      => null,
                'uniMedida'       => (int) $detalle->unidadMedida->codigo,
                'descripcion'     => $descripcion,
                'precioUni'       => $precioUni,
                'montoDescu'      => $descu,
                'ventaNoSuj'      => 0,
                'ventaExenta'     => $ventaExenta,
                'ventaGravada'    => $ventaGravada,
                'tributos'        => null,
                'psv'             => 0,
                'ivaItem'         => $ivaItem,
                'noGravado'       => 0,
            ];

            $sumVentaGravada += $ventaGravada;
            $sumVentaExenta  += $ventaExenta;
            $sumIva          += $ivaItem;
            $sumDescu        += $descu;
        }

        // ============================
        // 5. RESUMEN
        // CF con precio IVA incluido:
        // totalGravada/subTotalVentas/subTotal/montoTotalOperacion/totalPagar = SUM(ventaGravada + ventaExenta)
        // totalIva aparte
        // ============================
        $totalGravada2 = $f2($sumVentaGravada);
        $totalExenta2  = $f2($sumVentaExenta);
        $totalIva2     = $f2($sumIva);
        $totalDescu2   = $f2($sumDescu);

        $subTotalVentas2 = $f2($totalGravada2 + $totalExenta2);

        // En CF precio con IVA incluido => totalPagar = subtotalVentas (NO sumes iva otra vez)
        $totalPagar2 = $subTotalVentas2;

        $dteJson['resumen'] = [
            'totalNoSuj'          => 0,
            'totalExenta'         => $totalExenta2,
            'totalGravada'        => $totalGravada2,
            'subTotalVentas'      => $subTotalVentas2,
            'descuNoSuj'          => 0,
            'descuExenta'         => 0,
            'descuGravada'        => 0,
            'porcentajeDescuento' => 0,
            'totalDescu'          => $totalDescu2,
            'tributos'            => null,
            'subTotal'            => $subTotalVentas2,
            'ivaRete1'            => 0,
            'reteRenta'           => 0,
            'montoTotalOperacion' => $subTotalVentas2,
            'totalNoGravado'      => 0,
            'totalPagar'          => $totalPagar2,
            'totalLetras'         => $this->totalEnLetras($totalPagar2),
            'saldoFavor'          => 0,
            'condicionOperacion'  => (int) $factura->idCondicionVenta,
            'pagos'               => null,
            'numPagoElectronico'  => null,
            'totalIva'            => $totalIva2,
        ];

        return response()->json([
            'mensaje'          => 'EXITO',
            'numeroControl'    => $factura->numeroControl,
            'fechaHoraEmision' => $factura->fechaHoraEmision,
            'json'             => $dteJson,
        ]);
    }




    private function truncar($valor, $decimales = 2)
    {
        $factor = pow(10, $decimales);
        return floor($valor * $factor) / $factor;
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
                'correo' => $factura->sucursal->correo,
                'codPuntoVenta' => $factura->puntoVenta->codigoPuntoVentaMh
                    ?? $factura->sucursal->codigoPuntoVenta
                    ?? 'P001',

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




    private function firmarJson(
        array $jsonDte,
        int $idFactura,
        int $idEmpresa,
        string $fechaEmision
    ): object {

        $respuesta = new \stdClass();

        try {

            // 1️⃣ Configuración de transmisión
            $config = EmpresaConfigTransmisionDte::where('idEmpresa', $idEmpresa)
                //->whereNull('fechaElimina')
                ->firstOrFail();


            if (empty($config->urlFirmador) || empty($config->clavePrivada)) {
                throw new \Exception('Configuración de firmador incompleta');
            }

            // 2️⃣ Empresa (para el NIT)
            $empresa = Empresa::findOrFail($idEmpresa);

            // 3️⃣ PAYLOAD EXACTO QUE EL FIRMADOR ESPERA
            $payloadFirmador = [
                'nit'        => str_replace('-', '', $empresa->nit),
                'activo'     => true,
                'passwordPri' => $config->clavePrivada,
                'dteJson'    => $jsonDte,
            ];

            // 4️⃣ Enviar al firmador
            $httpResponse = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($config->urlFirmador, $payloadFirmador);

            if (!$httpResponse->successful()) {
                throw new \Exception(
                    'Error HTTP firmador: ' . $httpResponse->status()
                );
            }

            $firmado = $httpResponse->json();

            // 5️⃣ Guardar respuesta (igual legacy)
            DB::table('facturacion_mh_firmador')->updateOrInsert(
                ['idEncabezado' => $idFactura],
                [
                    'estado'            => $firmado['status'] ?? 'ERROR',
                    'respuestaBody'     => serialize($firmado['body'] ?? null),
                    'fechaHoraRegistro' => now()->format('Y-m-d'),
                ]
            );

            $respuesta->mensaje   = 'EXITO';
            $respuesta->documento = $firmado['body'] ?? null;
        } catch (\Throwable $e) {

            Log::error('Error al firmar JSON DTE', [
                'idFactura' => $idFactura,
                'exception' => $e,
            ]);

            $respuesta->mensaje   = 'ERROR';
            $respuesta->error     = $e->getMessage();
            $respuesta->documento = null;
        }

        return $respuesta;
    }


    private function validarSiDteExisteEnContingenciaYsiEstaActiva(int $idEncabezado): object
    {
        $respuesta = new \stdClass();

        try {

            $registro = DB::table('facturacion_encabezado as encabezado')
                ->join(
                    'facturacion_evento_contingencia as contingencia',
                    'encabezado.idEventoContingencia',
                    '=',
                    'contingencia.id'
                )
                ->select(
                    'contingencia.estadoMh',
                    'contingencia.SelloMh'
                )
                ->where('encabezado.id', $idEncabezado)
                ->first();

            if ($registro) {

                if (
                    $registro->estadoMh === 'RECIBIDO'
                    && !empty($registro->SelloMh)
                ) {
                    // contingencia ya procesada → se puede enviar
                    $respuesta->mensaje = 'CONTINGENCIA_PROCESADA';
                } else {
                    // existe contingencia activa → NO se envía
                    $respuesta->mensaje = 'EXISTE_CONTINGENCIA';
                    $respuesta->estado  = 'CONTINGENCIA';
                }
            } else {
                // no existe contingencia → se puede enviar
                $respuesta->mensaje = 'NO_EXISTE_CONTINGENCIA';
            }
        } catch (\Throwable $e) {

            Log::error('Error validando contingencia DTE', [
                'idEncabezado' => $idEncabezado,
                'exception'    => $e,
            ]);

            $respuesta->mensaje = 'ERROR';
            $respuesta->error   = $e->getMessage();
        }

        return $respuesta;
    }


    private function recepciondte(
        int $idFactura,
        int $idEmpresa,
        int $idSucursal,
        string $jsonEncode,
        string $documento,
        string $fechaEmision
    ): object {

        Log::info('[MH][1] INICIO recepciondte', compact(
            'idFactura',
            'idEmpresa',
            'idSucursal',
            'fechaEmision'
        ));

        $respuesta = new \stdClass();

        // ============================
        // 0. JSON BASE
        // ============================
        $jsonEnco = json_decode($jsonEncode);

        if (!$jsonEnco || !isset($jsonEnco->identificacion)) {
            Log::error('[MH][ERROR] JSON DTE inválido', [
                'jsonEncode' => $jsonEncode
            ]);
            throw new \Exception('JSON DTE inválido');
        }

        Log::info('[MH][0] JSON DTE válido', [
            'codigoGeneracion' => $jsonEnco->identificacion->codigoGeneracion,
            'tipoDte'          => $jsonEnco->identificacion->tipoDte,
        ]);

        // ============================
        // 1. CONFIGURACIÓN EMPRESA
        // ============================
        $config = EmpresaConfigTransmisionDte::where('idEmpresa', $idEmpresa)->first();

        if (!$config) {
            Log::error('[MH][1] Empresa sin configuración DTE', compact('idEmpresa'));
            throw new \Exception('Empresa sin configuración de transmisión DTE');
        }

        Log::info('[MH][1] Configuración empresa OK', [
            'ambiente' => $config->ambiente->codigo,
            'empresa'  => $idEmpresa
        ]);

        // ============================
        // 2. ENDPOINTS
        // ============================
        $endpoints = $this->obtenerEndpointsMh($config->idTipoAmbiente);

        Log::info('[MH][2] Endpoints MH', $endpoints);

        // ============================
        // 3. TOKEN
        // ============================
        $getToken = $this->obtenerToken($config, $endpoints, $idSucursal);

        Log::info('[MH][3] Respuesta token', (array) $getToken);

        if ($getToken->mensaje !== 'EXITO') {
            Log::error('[MH][3][ERROR] No se obtuvo token');
            return $getToken;
        }

        // ============================
        // 4. BODY ENVÍO
        // ============================
        $body = [
            'ambiente'         => $config->ambiente->codigo,
            'idEnvio'          => (int) $idFactura,
            'version'          => (int) $jsonEnco->identificacion->version,
            'tipoDte'          => $jsonEnco->identificacion->tipoDte,
            'documento'        => $documento,
            'codigoGeneracion' => $jsonEnco->identificacion->codigoGeneracion,
        ];

        Log::info('[MH][4] Body enviado a MH', $body);

        // ============================
        // 5. ENVÍO A HACIENDA
        // ============================
        try {

            $httpResponse = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $getToken->token,
                    'Content-Type'  => 'application/json',
                ])
                ->post($endpoints['urlRecepcion'], $body);
        } catch (\Throwable $e) {

            Log::error('[MH][5][ERROR] Excepción HTTP', [
                'message' => $e->getMessage()
            ]);

            throw $e;
        }

        $status  = $httpResponse->status();
        $rawBody = $httpResponse->body();
        $dte     = json_decode($rawBody, true);

        Log::info('[MH][5] Respuesta HTTP MH', [
            'status' => $status,
            'raw'    => $rawBody,
            'json'   => $dte,
        ]);

        // ============================
        // 6. GUARDAR JSON FÍSICO
        // ============================
        $this->editarJsonHacienda(
            $jsonEnco->identificacion->codigoGeneracion,
            $rawBody,
            $idEmpresa,
            $fechaEmision
        );

        Log::info('[MH][6] JSON Hacienda guardado en archivo');

        // ============================
        // 7. GUARDAR RESPUESTA BD
        // ============================
        $test = $this->guardarRespuestaDTE(
            $idFactura,
            $status,
            is_array($dte) ? $dte : []
        );

        Log::info('[MH][7] Resultado guardarRespuestaDTE', (array) $test);

        if ($test->mensaje !== 'EXITO') {
            Log::error('[MH][7][ERROR] No se pudo guardar respuesta DTE');
            return $test;
        }

        // ============================
        // 8. RESPUESTA FINAL
        // ============================
        if ($status === 200) {

            Log::info('[MH][8] DTE PROCESADO OK', [
                'estado'        => $dte['estado'] ?? null,
                'selloRecibido' => $dte['selloRecibido'] ?? null,
            ]);

            $respuesta->mensaje          = 'EXITO';
            $respuesta->estado           = $dte['estado'] ?? null;
            $respuesta->observaciones    = $dte['observaciones'] ?? null;
            $respuesta->excepcion        = $dte['descripcionMsg'] ?? null;
            $respuesta->codigoGeneracion = $dte['codigoGeneracion'] ?? null;
            $respuesta->selloRecibido    = $dte['selloRecibido'] ?? null;
            $respuesta->idEncabezado     = $idFactura;
            $respuesta->idSucursal       = $idSucursal;
        } else {

            Log::warning('[MH][8] DTE RECHAZADO', [
                'estado' => $dte['estado'] ?? null,
                'error'  => $dte['descripcionMsg'] ?? null,
            ]);

            $respuesta->mensaje          = 'RECHAZADO';
            $respuesta->estado           = $dte['estado'] ?? null;
            $respuesta->excepcion        = $dte['descripcionMsg'] ?? null;
            $respuesta->observaciones    = $dte['observaciones'] ?? null;
            $respuesta->codigoGeneracion = $jsonEnco->identificacion->codigoGeneracion;
            $respuesta->numeroControl    = $jsonEnco->identificacion->numeroControl;
            $respuesta->idEncabezado     = $idFactura;
            $respuesta->idSucursal       = $idSucursal;
        }

        Log::info('[MH][FIN] recepciondte finalizada', (array) $respuesta);

        return $respuesta;
    }



    private function obtenerToken(
        EmpresaConfigTransmisionDte $config,
        array $endpoints,
        int $idSucursal
    ): object {

        $respuesta = new \stdClass();

        try {

            // ============================
            // 1. BUSCAR TOKEN DEL DÍA
            // ============================
            $hoyFecha = Carbon::now()->format('Y-m-d');

            $tokenRow = DB::table('facturacion_mh_tokens')
                ->whereDate('fechaToken', $hoyFecha)
                ->where('idEmpresa', $config->idEmpresa)
                ->where('idSucursal', $idSucursal)
                ->select(DB::raw('COUNT(*) as contador'), 'token')
                ->groupBy('token')
                ->first();



            if ($tokenRow && $tokenRow->contador > 0) {

                $respuesta->mensaje = 'EXITO';
                $respuesta->token   = $tokenRow->token;
                return $respuesta;
            }

            // ============================
            // 2. NO EXISTE → PEDIR TOKEN
            // ============================
            $resToken = $this->getTokenApi($config, $endpoints['urlToken']);

            if ($resToken->mensaje === 'EXITO') {
                return $resToken;
            }

            return $resToken;
        } catch (\Throwable $e) {

            Log::error('Error obtenerToken()', [
                'idEmpresa'  => $config->idEmpresa,
                'idSucursal' => $idSucursal,
                'exception'  => $e,
            ]);

            $respuesta->mensaje = 'ERROR_TOKEN';
            $respuesta->error   = 'Error al obtener token de Hacienda';
            return $respuesta;
        }
    }


    private function getTokenApi(
        EmpresaConfigTransmisionDte $config,
        string $urlToken
    ): object {

        $respuesta = new \stdClass();

        try {

            // ============================
            // 1. DATOS DE AUTENTICACIÓN
            // ============================
            $nit = str_replace('-', '', $config->empresa->nit);
            $password = $config->passwordApi;

            // ============================
            // 2. REQUEST TOKEN MH
            // ============================
            $httpResponse = Http::asForm()
                ->timeout(30)
                ->post($urlToken, [
                    'user' => $nit,
                    'pwd'  => $password,
                ]);

            if (!$httpResponse->successful()) {
                throw new \Exception('Error HTTP token MH');
            }

            $data = $httpResponse->json();

            // ============================
            // 3. VALIDAR RESPUESTA
            // ============================
            if (($data['status'] ?? '') !== 'OK') {

                $respuesta->mensaje = 'NO_TOKEN';
                $respuesta->descripcion = $data['body']['descripcion'] ?? $data['body'] ?? null;
                return $respuesta;
            }

            $token = $data['body']['token'];

            // ============================
            // 4. GUARDAR TOKEN (MISMO LEGACY)
            // ============================
            DB::table('facturacion_mh_tokens')->insert([
                'idEmpresa'   => $config->idEmpresa,
                'idSucursal'  => $config->idSucursal ?? null,
                'token'       => $token,
                'fechaToken'  => Carbon::now()->format('Y-m-d'),
            ]);

            // ============================
            // 5. RESPUESTA FINAL
            // ============================
            $respuesta->mensaje = 'EXITO';
            $respuesta->token   = $token;
        } catch (\Throwable $e) {

            Log::error('Error getTokenApi()', [
                'idEmpresa' => $config->idEmpresa,
                'exception' => $e,
            ]);

            $respuesta->mensaje = 'ERROR_TOKEN';
            $respuesta->error   = 'Error al obtener token MH';
        }

        return $respuesta;
    }



    private function crearJsonParteEncabezadoDetalle(
        string $codigoGeneracion,
        array|string $json,
        int $idEmpresa,
        string $fechaEmision
    ): string {

        $baseDir = public_path('recursos/json');

        $dt = Carbon::parse($fechaEmision);
        $yearMonth = $dt->format('Y-m');

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $idEmpresa . DIRECTORY_SEPARATOR . $yearMonth;

        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0775, true);
        }

        $filename = $idEmpresa . '_' . $codigoGeneracion . '.json';
        $fullpath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (is_array($json)) {
            $json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        file_put_contents($fullpath, $json, LOCK_EX);

        return $fullpath;
    }


    private function editarJsonFirma(
        string $codigoGeneracion,
        string $jsonFirma,
        int $idEmpresa,
        string $fechaEmision
    ): string {

        $baseDir = public_path('recursos/json');
        $yearMonth = Carbon::parse($fechaEmision)->format('Y-m');

        $file = $baseDir . "/{$idEmpresa}/{$yearMonth}/{$idEmpresa}_{$codigoGeneracion}.json";

        if (!File::exists($file)) {
            file_put_contents($file, json_encode(new \stdClass()));
        }

        $contenido = json_decode(file_get_contents($file), true) ?? [];

        $firma = json_decode($jsonFirma, true);
        $contenido['firmaElectronica'] = $firma['body'] ?? $firma;

        file_put_contents(
            $file,
            json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $file;
    }


    private function editarJsonHacienda(
        string $codigoGeneracion,
        string $jsonHacienda,
        int $idEmpresa,
        string $fechaEmision
    ): string {

        $baseDir = public_path('recursos/json');
        $yearMonth = Carbon::parse($fechaEmision)->format('Y-m');

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $idEmpresa . DIRECTORY_SEPARATOR . $yearMonth;

        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0775, true);
        }

        $file = $targetDir . DIRECTORY_SEPARATOR . "{$idEmpresa}_{$codigoGeneracion}.json";

        // si no existe el archivo base, lo creamos vacío (igual que legacy para no romper)
        if (!File::exists($file)) {
            file_put_contents($file, json_encode(new \stdClass()), LOCK_EX);
        }

        $contenido = json_decode(file_get_contents($file), true) ?? [];

        // si viene JSON válido lo guardamos como array, si no como string
        $decoded = json_decode($jsonHacienda, true);
        $contenido['respuestaHacienda'] = is_array($decoded) ? $decoded : $jsonHacienda;

        file_put_contents(
            $file,
            json_encode($contenido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $file;
    }



    private function guardarRespuestaDTE(
        int $idEncabezado,
        int $statusHttp,
        array $jsonMh
    ): object {

        $respuesta = new \stdClass();

        try {

            DB::table('facturacion_mh_dte_respuestas')->insert([
                'idEncabezado'       => $idEncabezado,
                'codigoStatus'       => (string) $statusHttp,

                'versionDte'         => $jsonMh['version'] ?? null,
                'ambiente'           => $jsonMh['ambiente'] ?? null,
                'versionApp'         => null,

                'estado'             => $jsonMh['estado'] ?? null,
                'codigoGeneracion'   => $jsonMh['codigoGeneracion'] ?? null,
                'selloRecibido'      => $jsonMh['selloRecibido'] ?? null,

                'fechaProcesamiento' => now(),

                'clasificaMsg'       => isset($jsonMh['clasificaMsg'])
                    ? json_encode($jsonMh['clasificaMsg'], JSON_UNESCAPED_UNICODE)
                    : null,

                'codigoMsg'          => $jsonMh['codigoMsg'] ?? null,
                'descripcionMsg'     => $jsonMh['descripcionMsg'] ?? null,

                'observaciones'      => isset($jsonMh['observaciones'])
                    ? json_encode($jsonMh['observaciones'], JSON_UNESCAPED_UNICODE)
                    : null,

                'jsonCompleto'       => json_encode($jsonMh, JSON_UNESCAPED_UNICODE),
                'fechaRegistro'      => now(),
            ]);

            // Actualizar encabezado (como hacía el legacy)
            DB::table('facturacion_encabezado')
                ->where('id', $idEncabezado)
                ->update([
                    'estadoHacienda'       => $jsonMh['estado'] ?? null,
                    'selloHacienda'        => $jsonMh['selloRecibido'] ?? null,
                    'fechaTransmitenDte'   => now(),
                ]);

            $respuesta->mensaje = 'EXITO';
            return $respuesta;
        } catch (\Throwable $e) {

            Log::error('Error guardarRespuestaDTE()', [
                'idEncabezado' => $idEncabezado,
                'exception'    => $e,
            ]);

            $respuesta->mensaje = 'ERROR';
            $respuesta->error   = $e->getMessage();
            return $respuesta;
        }
    }



    private function crearNumeroControl(
        int $idEmpresa,
        int $idSucursal,
        int $idPuntoVenta,
        int $idTipoDte
    ): object {

        $respuesta = new \stdClass();

        try {

            // ============================
            // 1. AÑO ACTUAL (MISMO LEGACY)
            // ============================
            $anioActual = Carbon::now()->format('Y-01-01');

            // ============================
            // 2. OBTENER ÚLTIMO CORRELATIVO
            // ============================
            $ultimo = DB::table('facturacion_correlativos_numero_control')
                ->where('idEmpresa', $idEmpresa)
                ->where('idSucursal', $idSucursal)
                ->where('idTipoDte', $idTipoDte)
                ->where('anio', $anioActual)
                ->orderByDesc('correlativo')
                ->first();

            if ($ultimo) {
                $correlativo = ($ultimo->correlativo == 0)
                    ? 1
                    : ($ultimo->correlativo + 1);
            } else {
                $correlativo = 1;
            }

            // ============================
            // 3. CÓDIGOS (TUS MÉTODOS)
            // ============================
            $codigoDocTributario = TipoDocumentoTributario::where('id', $idTipoDte)->first()->codigo;
            $codigoSucursal      = EmpresaSucursal::where('id', $idSucursal)->first()->codigoEstablecimiento;
            $codigoPuntoVenta    = EmpresaPuntoVenta::where('id', $idPuntoVenta)->first()->codigoPuntoVentaMh;

            // ============================
            // 4. ARMAR NÚMERO CONTROL
            // ============================
            $numeroControl = sprintf(
                'DTE-%s-%s%s-%015d',
                $codigoDocTributario,
                $codigoSucursal,
                $codigoPuntoVenta,
                $correlativo
            );

            // ============================
            // 5. INSERTAR CORRELATIVO
            // ============================
            $idInsertado = DB::table('facturacion_correlativos_numero_control')
                ->insertGetId([
                    'idEmpresa'     => $idEmpresa,
                    'idSucursal'    => $idSucursal,
                    'idPuntoVenta'  => $idPuntoVenta,
                    'idTipoDte'     => $idTipoDte,
                    'correlativo'   => $correlativo,
                    'numeroControl' => $numeroControl,
                    'anio'          => $anioActual,
                ]);

            // ============================
            // 6. RESPUESTA
            // ============================
            $respuesta->mensaje         = 'EXITO';
            $respuesta->numeroControl   = $numeroControl;
            $respuesta->idNumeroControl = $idInsertado;
        } catch (\Throwable $e) {

            Log::error('Error crearNumeroControl()', [
                'idEmpresa'  => $idEmpresa,
                'idSucursal' => $idSucursal,
                'idTipoDte'  => $idTipoDte,
                'exception'  => $e,
            ]);

            $respuesta->mensaje   = 'ERROR';
            $respuesta->excepcion = $e->getMessage();
        }

        return $respuesta;
    }
}
