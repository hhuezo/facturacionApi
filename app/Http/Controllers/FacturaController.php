<?php

namespace App\Http\Controllers;

use App\Models\catalogo\Cliente;
use App\Models\catalogo\Producto;
use App\Models\Empresa;
use App\Models\EmpresaSucursal;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\mh\CondicionVenta;
use App\Models\mh\TipoDocumentoTributario;
use App\Models\mh\TipoPlazo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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


            $sucursal = EmpresaSucursal::where('idEmpresa', $request->idEmpresa)->first();

            $factura = new Factura();

            $factura->idEmpresa        = $request->idEmpresa;
            $factura->idSucursal       = $sucursal->id;
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
