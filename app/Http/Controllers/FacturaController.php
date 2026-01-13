<?php

namespace App\Http\Controllers;

use App\Models\catalogo\Cliente;
use App\Models\catalogo\Producto;
use App\Models\Empresa;
use App\Models\EmpresaActividadEconomica;
use App\Models\EmpresaSucursal;
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

        $facturas = Factura::with(['cliente', 'empresa', 'tipoDocumentoTributario', 'usuario'])
            ->where('idEmpresa', $idEmpresa)
            ->whereBetween('fechaRegistraOrden', [
                Carbon::now()->subMonth()->startOfDay(),
                Carbon::now()->endOfDay()
            ])
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
            ->where('idEmpresa', $idEmpresa)->get();
        $tiposPlazo = TipoPlazo::select('id', 'nombre')->get();
        $condicionesVenta = CondicionVenta::select('id', 'nombre')->get();
        $productos = Producto::with([
            'unidadMedida' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('idEmpresa', $idEmpresa)
            ->select('id', 'nombre', 'idUnidadMedida', 'precioVentaConIva', 'valorDescuento')
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

        /* VALIDACIONES ADICIONALES

            recalcularTotales() {
                    console.log("------------- FUNCION RECALCULANDO TOTALES ------------------")
                    // Inicializar totales
                    this.totalExcenta = 0;
                    this.totalGravada = 0;
                    this.totalDescuento = 0;
                    this.ivaVenta = 0;
                    this.subTotal = 0;
                    this.totalPagar = 0;
                    this.totalFletes = 0;
                    this.totalSeguros = 0;
                    this.retencionIVA1 = 0;
                    this.tmpRentaServicios = 0;

                    var idTipoDte = parseInt(document.getElementById("idTipoDte").value.trim()) || parseInt(facturar.idTipoDte.value);
                    var idTipoContribuyente = parseInt(this.idTipoContribuyente) || 1;
                    console.log("id Tipo DTE para recalcular es: " + idTipoDte);


                    this.detalleProductos.forEach(item => {
                        // aca se le calcula el iva por aparte solo a los CCF y a las NC
                        let totalIva = (idTipoDte === 2 || idTipoDte === 4) ? item.gravada * this.porcentajeIVA : 0.00;
                        console.log("el total de iva es: " + totalIva)
                        console.log("Id tipo DTEE: " + idTipoDte)
                        // Sumar a los totales
                        this.totalExcenta += Number(item.excento);
                        this.totalGravada += Number(item.gravada);
                        this.ivaVenta += totalIva;
                        this.tmpRentaServicios += Number(item.rentaPorServicios);

                    });
                    // si es CCF y a las NC y si es gran contribuyente
                    if ((idTipoDte === 2 || idTipoDte === 4) && idTipoContribuyente === 4) {
                        if (this.totalGravada > 100) {
                            this.retencionIVA1 = this.totalGravada * 0.01; // Retención del 1% sobre el IVA
                        } else {
                            this.retencionIVA1 = 0;
                        }
                    }
                    // si es consumidor final y es gran contribuyente
                    if (idTipoDte === 1 && idTipoContribuyente === 4) {
                        var tmpGravadaSinIVA = this.totalGravada / 1.13;
                        if (tmpGravadaSinIVA > 100) {
                            this.retencionIVA1 = tmpGravadaSinIVA * 0.01; // Retención del 1% sobre el IVA
                        } else {
                            this.retencionIVA1 = 0;
                        }
                    }
                    console.log("IVA Retenido 1%: " + this.retencionIVA1);
                    this.totalSeguros = parseFloat(document.getElementById("txtSeguro").value.trim()) || 0;
                    this.totalFletes = parseFloat(document.getElementById("txtFletes").value.trim()) || 0;

                    // Calcular subtotal y total a pagar
                    this.subTotal = this.totalExcenta + this.totalGravada;
                    // Sujeto Excluido calcularemos el 10% de la renta
                    if (idTipoDte === 10) {
                        this.totalRetencionRenta = this.subTotal * 0.10;
                    }
                    console.log("renta por servcicios: " + this.tmpRentaServicios);
                    console.log("tipo dte para evaluar renta en servicios es: " + idTipoDte);
                    if (idTipoDte === 1 || (idTipoDte === 2 || idTipoDte === 4) && this.tmpRentaServicios > 0) {
                        console.log("ingreso ala a la retencion por servicios");
                        this.totalRetencionRenta = this.tmpRentaServicios;
                    }

                    this.totalPagar = (this.subTotal + this.ivaVenta + this.totalSeguros + this.totalFletes) - (this.totalRetencionRenta + this.retencionIVA1);
                },



        */

        DB::beginTransaction();

        try {

            $factura = new Factura();

            $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());

            $factura->idEmpresa        = $request->idEmpresa;
            $factura->idSucursal       = $request->idSucursal;
            $factura->idTipoDte        = $request->idTipoDte;
            $factura->idCliente        = $request->idCliente;
            $factura->fechaHoraEmision = Carbon::now();

            $factura->subTotal       = $request->subTotal;
            $factura->totalDescuento = $request->totalDescuento ?? 0;
            $factura->totalGravada   = $request->totalGravada;
            $factura->totalIVA       = $request->totalIVA;
            $factura->totalPagar     = $request->totalPagar;

            $factura->idCondicionVenta = $request->idCondicionVenta;
            $factura->idPlazo          = $request->idPlazo ?? null;
            $factura->diasCredito      = $request->diasCredito ?? null;

            $factura->estadoHacienda   = "COTIZACION";
            $factura->codigoGeneracion = $codigoGeneracion;

            $factura->eliminado = 'N';
            $factura->fechaRegistraOrden = Carbon::now();
            $factura->idUsuarioRegistraOrden = auth()->id();

            $factura->save();


            foreach ($request->items as $index => $item) {

                $producto = Producto::find($item['idProducto']);

                $detalle = new FacturaDetalle();

                $detalle->idEncabezado   = $factura->id;
                $detalle->idProducto     = $item['idProducto'];
                $detalle->idTipoItem     = $producto->idTipoItem;
                $detalle->idUnidadMedida = $item['idUnidadMedida'];

                $detalle->cantidad       = $item['cantidad'];
                $detalle->precioUnitario = $item['precioUnitario'];

                $detalle->porcentajeDescuento = $item['porcentajeDescuento'] ?? 0;
                $detalle->descuento            = $item['descuento'] ?? 0;

                $detalle->gravadas = $item['gravadas'];
                $detalle->excentas = $item['excentas'] ?? 0;
                $detalle->iva      = $item['iva'];

                $detalle->motivoCambioPrecio = $item['motivoCambioPrecio'] ?? null;

                $detalle->save();
            }

            DB::commit();


            return response()->json([
                'success' => true,
                'idFactura' => $factura->id
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            // ❌ LOG DE ERROR COMPLETO
            Log::error('FACTURA STORE - ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear factura',
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
                    'empresa' => [
                        'nombre' => $factura->empresa->nombre,
                        'direccion' => $factura->empresa->direccion,
                        'nit' => $factura->empresa->nit,
                    ],
                    'documento' => [
                        'tipo' => strtoupper($factura->tipoDocumentoTributario->nombre ?? ''),
                        'codigoGeneracion' => $factura->codigoGeneracion,
                        'numeroControl' => $factura->numeroControl,
                        'fecha' => $factura->fechaHoraEmision->format('Y-m-d H:i'),
                        'caja' => $factura->sucursal->nombreSucursal ?? 'Caja',
                    ],
                    'cliente' => [
                        'nombre' => $factura->cliente->nombreCliente ?? '',
                        'documento' => $factura->cliente->numeroDocumento ?? '',
                        'direccion' => $factura->cliente->direccion ?? '',
                    ],
                    'items' => $factura->detalles->map(function ($item) {
                        return [
                            'cantidad' => number_format($item->cantidad, 2),
                            'descripcion' => $item->producto->nombre ?? 'Servicio',
                            'total' => number_format(
                                $item->gravadas + $item->iva,
                                2
                            ),
                        ];
                    }),
                    'totales' => [
                        'subtotal' => number_format($factura->subTotal, 2),
                        'iva' => number_format($factura->totalIVA, 2),
                        'total' => number_format($factura->totalPagar, 2),
                    ],
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar ticket',
                'error' => $e->getMessage()
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
            ->where('idEmpresa', $idEmpresa)->get();
        $tiposPlazo = TipoPlazo::select('id', 'nombre')->get();
        $condicionesVenta = CondicionVenta::select('id', 'nombre')->get();
        $productos = Producto::with([
            'unidadMedida' => function ($q) {
                $q->select('id', 'nombre');
            }
        ])
            ->where('idEmpresa', $idEmpresa)
            ->select('id', 'nombre', 'idUnidadMedida', 'precioVentaConIva', 'valorDescuento')
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
