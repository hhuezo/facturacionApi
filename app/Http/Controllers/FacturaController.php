<?php

namespace App\Http\Controllers;

use App\Models\catalogo\Cliente;
use App\Models\catalogo\Producto;
use App\Models\Empresa;
use App\Models\EmpresaActividadEconomica;
use App\Models\EmpresaConfigTransmisionDte;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\mh\CondicionVenta;
use App\Models\mh\TipoDocumentoTributario;
use App\Models\mh\TipoPlazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->idEmpresa ?? 0;

        $filtro = $request->filtro ?? 'Diario'; // Recibimos el string desde Android

        // Definimos la fecha de inicio por defecto (hoy)
        // Definimos la fecha fin (hoy) formateada
        $fechaFin = Carbon::now()->format('Y-m-d');

        // Ajustamos la fecha de inicio según el filtro
        switch ($filtro) {
            case 'Semanal':
                $fechaInicio = Carbon::now()->subDays(7)->format('Y-m-d');
                break;
            case 'Quincenal':
                $fechaInicio = Carbon::now()->subDays(15)->format('Y-m-d');
                break;
            case 'Mensual':
                $fechaInicio = Carbon::now()->subMonth()->format('Y-m-d');
                break;
            case 'Diario':
                $fechaInicio = Carbon::now()->format('Y-m-d');
                break;
            default:
                $fechaInicio = Carbon::now()->format('Y-m-d');
                break;
        }

        $facturas = Factura::with(['cliente', 'empresa', 'tipoDocumentoTributario', 'usuario'])
            ->where('idEmpresa', $idEmpresa)
            ->whereDate('fechaRegistraOrden', '>=', $fechaInicio)
            ->whereDate('fechaRegistraOrden', '<=', $fechaFin)
            ->orderBy('facturacion_encabezado.id', 'desc')
            ->get()
            ->toArray();


        foreach ($facturas as &$factura) {

            if (!empty($factura['fechaHoraEmision'])) {
                $factura['fechaHoraEmision'] = \Carbon\Carbon::parse(
                    $factura['fechaHoraEmision']
                )->format('d/m/Y');
            }

            if (isset($factura['totalPagar'])) {
                $factura['totalPagar'] = number_format(
                    (float) $factura['totalPagar'],
                    2,
                    '.',
                    ''
                );
            }
        }


        return response()->json([
            'success' => true,
            'data' => $facturas,
        ], 200);
    }

    public function create(Request $request)
    {
        $idEmpresa = $request->idEmpresa ?? 0;

        $empresas = Empresa::where('id', $idEmpresa)->select('id', 'nombreComercial')->get();
        $tiposDocumento = Empresa::with([
            'tiposDocumentoTributario:id,nombre'
        ])
            ->where('id', $idEmpresa)
            ->first()
            ?->tiposDocumentoTributario ?? collect();


        $clientes = Cliente::with([
            'actividadEconomica' => function ($q) {
                $q->select('id', 'nombreActividad as nombre');
            },
            'departamento' => function ($q) {
                $q->select('id', 'nombre');
            },
            'municipio' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('eliminado', 'N')
            ->where('idEmpresa', $idEmpresa)->get();
        $tiposPlazo = TipoPlazo::select('id', 'nombre')->get();
        $condicionesVenta = CondicionVenta::select('id', 'nombre')->get();
        $productos = Producto::with([
            'unidadMedida' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('idEmpresa', $idEmpresa)
            ->where('eliminado', 'N')
            ->select('id', 'nombre', 'idUnidadMedida', 'precioVentaConIva', 'valorDescuento', 'excento')
            ->get();


        return response()->json([
            'success' => true,
            'data' => [
                'empresas' => $empresas,
                'tiposDocumento' => $tiposDocumento,
                'clientes' => $clientes,
                'tiposPlazo' => $tiposPlazo,
                'condicionesVenta' => $condicionesVenta,
                'productos' => $productos,
            ],
        ], 200);
        //
    }




    public function store(Request $request)
    {

        DB::beginTransaction();

        try {

            // ============================
            // 1. VERSION JSON
            // ============================
            switch ((int) $request->idTipoDte) {
                case 1:
                    $versionJson = 1;
                    break;
                case 2:
                case 4:
                case 6:
                    $versionJson = 3;
                    break;
                case 9:
                case 10:
                    $versionJson = 1;
                    break;
                default:
                    throw new \Exception('Tipo DTE no soportado');
            }

            // ============================
            // 2. CONFIG EMPRESA
            // ============================
            $config = EmpresaConfigTransmisionDte::where('idEmpresa', $request->idEmpresa)
                ->firstOrFail();

            // ============================
            // 3. CLIENTE
            // ============================
            $cliente = Cliente::findOrFail($request->idCliente);
            $clienteEsExento = $cliente->esExento === 'S';
            $idTipoContribuyente = (int) $cliente->idTipoContribuyente;

            // ============================
            // 4. FACTURA
            // ============================
            $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());

            $factura = new Factura();

            $factura->codigoGeneracion = $codigoGeneracion;

            $factura->idEmpresa    = $request->idEmpresa;
            $factura->idSucursal   = $request->idSucursal;
            $factura->idPuntoVenta = $request->idPuntoVenta;
            $factura->idTipoDte    = $request->idTipoDte;
            $factura->versionJson  = $versionJson;
            $factura->idCliente    = $request->idCliente;

            $factura->fechaHoraEmision = Carbon::now();
            $factura->idAmbiente       = $config->idTipoAmbiente;
            $factura->idTipoFacturacion = 1;
            $factura->idTipoTransmision = 1;

            $factura->idCondicionVenta = $request->idCondicionVenta;
            $factura->idPlazo          = $request->idPlazo ?? null;
            $factura->diasCredito      = $request->diasCredito ?? null;

            $factura->estadoHacienda   = 'COTIZACION';
            $factura->eliminado        = 'N';
            $factura->fechaRegistraOrden = Carbon::now();
            $factura->idUsuarioRegistraOrden = $request->idUsuario;

            // Iniciales
            $factura->subTotal     = 0;
            $factura->totalGravada = 0;
            $factura->totalIVA     = 0;
            $factura->totalPagar   = 0;

            $factura->save();

            // ============================
            // 5. DETALLES
            // ============================
            $totalGravada = 0;
            $totalExcenta = 0;
            $totalIva     = 0;

            $tmpRentaServicios = 0;

            foreach ($request->items as $item) {

                $producto = Producto::findOrFail($item['idProducto']);

                $cantidad = round((float) $item['cantidad'], 2);

                // ✅ Precio unitario CON IVA (viene así desde el front)
                $precioUnitarioConIva = round((float) $item['precioUnitario'], 6);

                // ✅ Total de línea CON IVA
                $totalLineaConIva = round($cantidad * $precioUnitarioConIva, 6);

                $productoEsExento = $producto->excento === 'S';
                $esExento = $clienteEsExento || $productoEsExento;

                if ($esExento) {

                    $gravada = 0.0;
                    $excenta = $totalLineaConIva;
                    $ivaItem = 0.0;
                } else {

                    // 🔥 AQUÍ SALE 0.2934 CUANDO totalLineaConIva = 2.55
                    $baseSinIva = round($totalLineaConIva / 1.13, 6);
                    $ivaItem    = round($totalLineaConIva - $baseSinIva, 6);

                    // ✅ TU REGLA: gravadas = TOTAL CON IVA
                    $gravada = $totalLineaConIva;
                    $excenta = 0.0;
                }

                $detalle = new FacturaDetalle();
                $detalle->idEncabezado   = $factura->id;
                $detalle->idProducto     = $producto->id;
                $detalle->idTipoItem     = $producto->idTipoItem;
                $detalle->idUnidadMedida = $item['idUnidadMedida'];

                // ✅ Guardas PRECIO UNITARIO CON IVA
                $detalle->cantidad       = $cantidad;
                $detalle->precioUnitario = $precioUnitarioConIva;

                $detalle->porcentajeDescuento = 0;
                $detalle->descuento            = 0;

                // ✅ Valores finales
                $detalle->gravadas = $gravada;   // TOTAL con IVA
                $detalle->excentas = $excenta;
                $detalle->iva      = $ivaItem;

                $detalle->save();

                $totalGravada += $gravada;
                $totalExcenta += $excenta;
                $totalIva     += $ivaItem;
            }




            // ============================
            // 6. RETENCIONES
            // ============================
            $idTipoDte = (int) $request->idTipoDte;

            $retencionIVA1 = 0;
            $totalRetencionRenta = 0;

            // Retención IVA 1%
            if (
                ($idTipoDte === 2 || $idTipoDte === 4 || $idTipoDte === 1) &&
                $idTipoContribuyente === 4 &&
                $totalGravada > 100
            ) {
                $retencionIVA1 = round($totalGravada * 0.01, 2);
            }

            // Subtotal real
            $subTotal = round($totalGravada + $totalExcenta, 2);

            // Retención renta
            if ($idTipoDte === 10) {
                $totalRetencionRenta = round($subTotal * 0.10, 2);
            }

            if (
                $idTipoDte === 1 ||
                (($idTipoDte === 2 || $idTipoDte === 4) && $tmpRentaServicios > 0)
            ) {
                $totalRetencionRenta = round($tmpRentaServicios, 2);
            }

            $totalSeguros = (float) ($request->totalSeguros ?? 0);
            $totalFletes  = (float) ($request->totalFletes ?? 0);

            // ============================
            // 7. TOTAL FINAL
            // ============================
            $totalPagar = round(
                ($subTotal + $totalIva + $totalSeguros + $totalFletes)
                    - ($totalRetencionRenta + $retencionIVA1),
                2
            );

            // ============================
            // 8. GUARDAR TOTALES
            // ============================
            $factura->subTotal     = $subTotal;
            $factura->totalGravada = $totalGravada;
            $factura->totalExenta  = $totalExcenta;
            $factura->totalIVA     = $totalIva;

            $factura->ivaRetenido1   = $retencionIVA1;
            $factura->retencionRenta = $totalRetencionRenta;

            $factura->seguros = $totalSeguros;
            $factura->fletes  = $totalFletes;

            $factura->totalPagar = $subTotal;
            $factura->totalCobrado = $subTotal;
            $factura->totalVuelto = 0;

            $factura->save();

            DB::commit();

            return response()->json([
                'success'   => true,
                'idFactura' => $factura->id
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('FACTURA STORE ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }









    public function emitir($id)
    {
        try {

            $factura = Factura::find($id);

            if (!$factura) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Factura enviada a Hacienda correctamente',
                'idFactura' => $factura->id
            ], 200);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al emitir factura',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reportePdf($id)
    {
        $factura = Factura::with([
            'cliente',
            'empresa',
            'sucursal',
            'usuario',
            'tipoDocumentoTributario',
            'detalles.producto',
            'detalles.unidadMedida'
        ])->findOrFail($id);

        $nombreActividad = EmpresaActividadEconomica::join(
            'mh_actividad_economica as ae',
            'general_datos_empresa_actividades_economicas.idActividad',
            '=',
            'ae.id'
        )
            ->where('general_datos_empresa_actividades_economicas.idEmpresa', $factura->idEmpresa)
            ->where('general_datos_empresa_actividades_economicas.actividadPrincipal', 'S')
            ->value('ae.nombreActividad');

        $sucursal = $factura->sucursal->first();


        $pdf = Pdf::loadView('pdf.factura_a4', [
            'factura' => $factura,
            'nombreActividad' => $nombreActividad,
            'sucursal' => $sucursal,
            // opcional:
            'logoPath' => null, // public_path('images/logo.png')
            'qrBase64' => null, // base64 PNG si lo tienes
            // colores (puedes mapear desde BD)
            'primario' => '#003366',
            'fondo'    => '#F2F2F2',
            'borde'    => '#CCCCCC',
            'texto'    => '#000000',
        ])->setPaper('A4', 'portrait');
        //->setPaper('A4', 'landscape');

        return $pdf->stream('DTE_' . $factura->numeroControl . '.pdf');
    }

    public function ticketJson($id)
    {
        try {

            $factura = Factura::with([
                'cliente',
                'empresa',
                'sucursal',
                'tipoDocumentoTributario',
                'detalles.producto'
            ])->find($id);

            if (!$factura) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [

                    // ============================
                    // EMPRESA
                    // ============================
                    'empresa' => [
                        'nombre'    => optional($factura->empresa)->nombre ?? 'Nombre no disponible',
                        'direccion' => optional($factura->empresa)->direccion ?? '',
                        'nit'       => optional($factura->empresa)->nit ?? '',
                    ],

                    // ============================
                    // DOCUMENTO
                    // ============================
                    'documento' => [
                        'tipo'             => strtoupper($factura->tipoDocumentoTributario->nombre ?? 'DOCUMENTO'),
                        'codigoGeneracion' => $factura->codigoGeneracion,
                        'numeroControl'    => $factura->numeroControl,
                        'fecha'            => Carbon::parse($factura->fechaHoraEmision)->format('d/m/Y H:i'),
                        'caja'             => $factura->sucursal->nombreSucursal ?? 'Caja Principal',
                    ],

                    // ============================
                    // CLIENTE
                    // ============================
                    'cliente' => [
                        'nombre'    => $factura->cliente->nombreCliente ?? 'CLIENTE GENERAL',
                        'documento' => $factura->cliente->numeroDocumento ?? '',
                        'direccion' => $factura->cliente->direccion ?? '',
                    ],

                    // ============================
                    // ITEMS
                    // ============================
                    'items' => $factura->detalles->map(function ($item) {

                        $totalItem = 0;
                        if ((float)$item->excentas > 0) {
                            $totalItem = (float)$item->excentas;
                        } else {
                            $totalItem = (float)$item->gravadas  + (float)$item->iva;
                        }
                        /* $totalItem  =
                            (float)$item->gravadas +
                            (float)$item->excentas +
                            (float)$item->iva;*/

                        return [
                            'cantidad'    => number_format((float)$item->cantidad, 0),
                            'precioUnitario'    => number_format((float)$item->precioUnitario, 2),
                            'descripcion' => $item->producto->nombre ?? 'Producto/Servicio',
                            'total'       => number_format($totalItem, 2),
                        ];
                    }),

                    // ============================
                    // TOTALES
                    // ============================
                    'totales' => [
                        'subtotal' => number_format((float)$factura->subTotal, 2),
                        'iva'      => number_format((float)$factura->totalIVA, 2),
                        'total'    => number_format((float)$factura->totalPagar, 2),
                    ],
                ]
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al generar ticket',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    /*public function reportePdf($id)
    {
        try {

            $factura = Factura::with([
                'cliente',
                'empresa',
                'sucursal',
                'usuario',
                'tipoDocumentoTributario',
                'detalles.producto',
                'detalles.unidadMedida'
            ])->find($id);

            if (!$factura) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }

            $pdf = Pdf::loadView('pdf.factura_ticket', [
                'factura' => $factura
            ])->setPaper([0, 0, 226.77, 600], 'portrait'); // tamaño ticket ~80mm

            return $pdf->stream(
                'Factura_' . $factura->numeroControl . '.pdf'
            );
        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }*/

    public function edit($id, Request $request)
    {
        $factura = Factura::findOrFail($id);

        $detalles = FacturaDetalle::with([
            'producto' => function ($q) {
                $q->select('id', 'nombre');
            },
            'unidadMedida' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('idEncabezado', $factura->id)
            ->get();


        $idEmpresa = $request->idEmpresa ?? 0;

        $empresas = Empresa::where('id', $idEmpresa)->select('id', 'nombreComercial')->get();
        $tiposDocumento = TipoDocumentoTributario::select('id', 'nombre')->get();
        $clientes = Cliente::with([
            'actividadEconomica' => function ($q) {
                $q->select('id', 'nombreActividad as nombre');
            },
            'departamento' => function ($q) {
                $q->select('id', 'nombre');
            },
            'municipio' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('eliminado', 'N')
            ->where('idEmpresa', $idEmpresa)->get();
        $tiposPlazo = TipoPlazo::select('id', 'nombre')->get();
        $condicionesVenta = CondicionVenta::select('id', 'nombre')->get();
        $productos = Producto::with([
            'unidadMedida' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('idEmpresa', $idEmpresa)
            ->where('eliminado', 'N')
            ->select('id', 'nombre', 'idUnidadMedida', 'precioVentaConIva', 'valorDescuento', 'excento')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'factura' => $factura,
                'detalles' => $detalles,
                'empresas' => $empresas,
                'tiposDocumento' => $tiposDocumento,
                'clientes' => $clientes,
                'tiposPlazo' => $tiposPlazo,
                'condicionesVenta' => $condicionesVenta,
                'productos' => $productos,
            ],
        ], 200);
    }


    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            /** =============================
             * 1️⃣ OBTENER FACTURA
             * ============================= */
            $factura = Factura::findOrFail($id);

            /** =============================
             * 2️⃣ ACTUALIZAR ENCABEZADO
             * ============================= */
            $factura->idEmpresa        = $request->idEmpresa;
            $factura->idTipoDte        = $request->idTipoDte;
            $factura->idCliente        = $request->idCliente;

            $factura->subTotal       = $request->subTotal;
            $factura->totalIVA       = $request->totalIVA;
            $factura->totalPagar     = $request->totalPagar;

            $factura->idCondicionVenta = $request->idCondicionVenta;
            $factura->idPlazo          = $request->idPlazo ?? null;
            $factura->diasCredito      = $request->diasCredito ?? null;

            $factura->save();

            /** =============================
             * 3️⃣ IDS DE DETALLES RECIBIDOS
             * ============================= */
            $idsDetalleRequest = collect($request->items)
                ->pluck('idDetalle')
                ->filter(fn($id) => !empty($id) && $id > 0)
                ->values()
                ->toArray();

            /** =============================
             * 4️⃣ ELIMINAR DETALLES QUE YA NO VIENEN
             * ============================= */
            FacturaDetalle::where('idEncabezado', $factura->id)
                ->whereNotIn('id', $idsDetalleRequest)
                ->delete();

            /** =============================
             * 5️⃣ INSERTAR / ACTUALIZAR DETALLES
             * ============================= */
            foreach ($request->items as $item) {

                // 🔹 ACTUALIZAR
                if (!empty($item['idDetalle']) && $item['idDetalle'] > 0) {

                    $detalle = FacturaDetalle::where('idEncabezado', $factura->id)
                        ->where('id', $item['idDetalle'])
                        ->firstOrFail();
                }
                // 🔹 CREAR
                else {

                    $producto = Producto::findOrFail($item['idProducto']);

                    $detalle = new FacturaDetalle();
                    $detalle->idEncabezado = $factura->id;
                    $detalle->idProducto   = $item['idProducto'];
                    $detalle->idTipoItem   = $producto->idTipoItem;
                }

                // 🔹 CAMPOS COMUNES
                $detalle->idUnidadMedida = $item['idUnidadMedida'];
                $detalle->cantidad       = $item['cantidad'];
                $detalle->precioUnitario = $item['precioUnitario'];

                $detalle->descuento  = $item['descuento'] ?? 0;
                $detalle->gravadas   = $item['gravadas'];
                $detalle->excentas   = $item['excentas'] ?? 0;
                $detalle->iva        = $item['iva'];

                $detalle->save();
            }

            DB::commit();

            return response()->json([
                'success'   => true,
                'message'   => 'Factura actualizada correctamente',
                'idFactura' => $factura->id
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('FACTURA UPDATE - ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar factura',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    public function destroy($id)
    {
        //
    }
}
