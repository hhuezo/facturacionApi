<?php

namespace App\Http\Controllers;

use App\Models\catalogo\Cliente;
use App\Models\catalogo\Producto;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\mh\CondicionVenta;
use App\Models\mh\TipoDocumentoTributario;
use App\Models\mh\TipoPlazo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $idEmpresa = $request->idEmpresa ?? 0;

        $facturas = Factura::with(['cliente', 'empresa', 'tipoDocumentoTributario', 'usuario'])
            ->where('idEmpresa', $idEmpresa)
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

            // -----------------------------
            // 1️⃣ VALIDACIONES BÁSICAS
            // -----------------------------
            $request->validate([
                'idEmpresa'        => 'required|integer',
                'idSucursal'       => 'required|integer',
                'idTipoDte'        => 'required|integer',
                'idCliente'        => 'required|integer',
                'idCondicionVenta' => 'required|integer',
                'items'            => 'required|array|min:1',
                'items.*.idProducto'      => 'required|integer',
                'items.*.cantidad'        => 'required|numeric|min:0.0001',
                'items.*.precioUnitario'  => 'required|numeric|min:0',
            ]);

            // -----------------------------
            // 2️⃣ CÁLCULOS GENERALES
            // -----------------------------
            $subTotal = 0;
            $totalIVA = 0;
            $totalDescuento = 0;

            foreach ($request->items as $item) {
                $lineaSub = $item['cantidad'] * $item['precioUnitario'];
                $lineaDesc = $item['descuento'] ?? 0;

                $subTotal += $lineaSub;
                $totalDescuento += $lineaDesc;
            }

            $baseGravada = $subTotal - $totalDescuento;
            $totalIVA = $baseGravada * 0.13;
            $totalPagar = $baseGravada + $totalIVA;

            // -----------------------------
            // 3️⃣ INSERTAR ENCABEZADO
            // -----------------------------
            $encabezadoId = DB::table('facturacion_encabezado')->insertGetId([
                'idEmpresa'        => $request->idEmpresa,
                'idSucursal'       => $request->idSucursal,
                'idTipoDte'        => $request->idTipoDte,
                'fechaHoraEmision' => Carbon::now(),
                'idCliente'        => $request->idCliente,

                'subTotal'         => $subTotal,
                'totalDescuento'   => $totalDescuento,
                'totalGravada'     => $baseGravada,
                'totalIVA'         => $totalIVA,
                'totalPagar'       => $totalPagar,

                'idCondicionVenta' => $request->idCondicionVenta,
                'idPlazo'          => $request->idPlazo ?? null,
                'diasCredito'      => $request->diasCredito ?? null,

                'eliminado'        => 'N',
                'fechaRegistraOrden' => Carbon::now(),
            ]);

            // -----------------------------
            // 4️⃣ INSERTAR DETALLE
            // -----------------------------
            foreach ($request->items as $item) {

                $cantidad = $item['cantidad'];
                $precio   = $item['precioUnitario'];
                $descuento = $item['descuento'] ?? 0;

                $gravadas = ($cantidad * $precio) - $descuento;
                $ivaLinea = $gravadas * 0.13;

                DB::table('facturacion_encabezado_detalle')->insert([
                    'idEncabezado'        => $encabezadoId,
                    'idProducto'          => $item['idProducto'],
                    'idTipoItem'          => $item['idTipoItem'] ?? null,
                    'idUnidadMedida'      => $item['idUnidadMedida'] ?? null,

                    'cantidad'            => $cantidad,
                    'precioUnitario'      => $precio,
                    'porcentajeDescuento' => $item['porcentajeDescuento'] ?? 0,
                    'descuento'            => $descuento,

                    'excentas'            => 0,
                    'gravadas'            => $gravadas,
                    'iva'                 => $ivaLinea,

                    'idInventario'        => $item['idInventario'] ?? null,
                    'motivoCambioPrecio'  => $item['motivoCambioPrecio'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Factura creada correctamente',
                'idFactura' => $encabezadoId
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la factura',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
