<?php

namespace App\Http\Controllers\catalogo;

use App\Http\Controllers\Controller;
use App\Models\catalogo\Producto;
use App\Models\catalogo\TipoItem;
use App\Models\mh\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProductoController extends Controller
{

    public function index(Request $request)
    {
        $idEmpresa = $request->idEmpresa ?? 0;
        $productos = Producto::with([
            'unidadMedida:id,nombre',
            'tipoItem:id,nombre'
        ])
            ->select(
                'id',
                'idUnidadMedida',
                'idTipoItem',
                'codigo',
                'nombre',
                'precioUnitarioConIva',
                'poseeDescuento',
                'porcentajeDescuento',
                'valorDescuento',
                'precioUnitarioFinalConIVA',
                'descripcion',
                'especificaciones'
            )
            ->where('idEmpresa', $idEmpresa)
            ->where('eliminado', 'N')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $productos,
        ], 200);
    }

    function generarAlfanumRandom(int $len = 12): string
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $idx = random_int(0, $max);
            $out .= $chars[$idx];
        }
        return $out;
    }

    public function create(Request $request)
    {
        $tipoItem = TipoItem::where('eliminado', 'N')->select('id', 'nombre')->get();
        $unidadesMedida = UnidadMedida::where('eliminado', 'N')->select('id', 'nombre')->get();
        $codigo = $this->generarAlfanumRandom();
        return response()->json([
            'success' => true,
            'data' => [
                'tipoItem' => $tipoItem,
                'unidadesMedida' => $unidadesMedida,
                'codigo' => $codigo,
            ],
        ], 200);
    }


    public function store(Request $request)
    {
        // FORZAMOS que cualquier error de aquí en adelante sea JSON
        // Esto evita que Laravel mande el HTML de "página no encontrada" o "error 500"
        if (!$request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        try {
            $request->validate([
                'idEmpresa'            => 'required|integer',
                'codigo'               => 'required|string|max:100',
                'nombre'               => 'required|string|max:1500',
                'idTipoItem'           => 'required|integer',
                'idUnidadMedida'       => 'required|integer',
                'precioUnitarioConIva' => 'required|numeric', // Verifica si acepta el string "100"
                'poseeDescuento'       => 'required|in:S,N',
                'excento'               => 'required|in:S,N',
            ]);

            $producto = new Producto();
            $producto->idEmpresa        = $request->idEmpresa;
            $producto->codigo           = $request->codigo;
            $producto->nombre           = $request->nombre;
            $producto->idTipoItem       = $request->idTipoItem;
            $producto->idUnidadMedida   = $request->idUnidadMedida;
            $producto->precioUnitarioConIva = $request->precioUnitarioConIva;
            $producto->precioVentaConIva    = $request->precioUnitarioConIva;
            $producto->poseeDescuento   = $request->poseeDescuento;
            $producto->excento           = $request->excento;
            $producto->eliminado        = 'N';
            $producto->fechaRegistra    = Carbon::now();
            $producto->idUsuarioRegistra = $request->idUsuario;

            $producto->save();

            return response()->json(['success' => true, 'data' => $producto], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de ejecución',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }



    public function edit($id)
    {
        $producto = Producto::select(
            'id',
            'idUnidadMedida',
            'idTipoItem',
            'codigo',
            'nombre',
            'precioUnitarioConIva',
            'poseeDescuento',
            'porcentajeDescuento',
            'valorDescuento',
            'precioUnitarioFinalConIVA',
            'descripcion',
            'especificaciones',
            'excento'
        )
            ->where('eliminado', 'N')->find($id);
        $tipoItem = TipoItem::where('eliminado', 'N')->select('id', 'nombre')->get();
        $unidadesMedida = UnidadMedida::where('eliminado', 'N')->select('id', 'nombre')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'producto' => $producto,
                'tipoItem' => $tipoItem,
                'unidadesMedida' => $unidadesMedida,
            ],
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate(
            [
                'idEmpresa'            => 'required|integer',
                'nombre'               => 'required|string|max:1500',

                'idTipoItem'           => 'required|integer',
                'idUnidadMedida'       => 'required|integer',

                'precioUnitarioConIva' => 'required|numeric|min:0',

                'poseeDescuento'       => 'required|in:S,N',
                'porcentajeDescuento'  => 'nullable|numeric|min:0',
                'valorDescuento'       => 'nullable|numeric|min:0',

                'descripcion'          => 'nullable|string|max:2000',
                'especificaciones'     => 'nullable|string|max:3000',

                'excento'               => 'required|in:S,N',
            ],
            [
                // Empresa
                'idEmpresa.required'   => 'La empresa es obligatoria.',
                'idEmpresa.integer'    => 'La empresa seleccionada no es válida.',

                // Nombre
                'nombre.required'      => 'El nombre del producto es obligatorio.',
                'nombre.string'        => 'El nombre del producto debe ser texto.',
                'nombre.max'           => 'El nombre del producto no puede exceder 1500 caracteres.',

                // Tipo Ítem
                'idTipoItem.required'  => 'Debe seleccionar el tipo de ítem.',
                'idTipoItem.integer'   => 'El tipo de ítem seleccionado no es válido.',

                // Unidad Medida
                'idUnidadMedida.required' => 'Debe seleccionar la unidad de medida.',
                'idUnidadMedida.integer'  => 'La unidad de medida seleccionada no es válida.',

                // Precio
                'precioUnitarioConIva.required' => 'El precio unitario con IVA es obligatorio.',
                'precioUnitarioConIva.numeric'  => 'El precio unitario con IVA debe ser numérico.',
                'precioUnitarioConIva.min'      => 'El precio unitario con IVA no puede ser negativo.',

                // Descuento
                'poseeDescuento.required' => 'Debe indicar si el producto posee descuento.',
                'poseeDescuento.in'       => 'El valor de descuento debe ser S o N.',

                'porcentajeDescuento.numeric' => 'El porcentaje de descuento debe ser un número válido.',
                'porcentajeDescuento.min'     => 'El porcentaje de descuento no puede ser negativo.',

                'valorDescuento.numeric' => 'El valor del descuento debe ser un número válido.',
                'valorDescuento.min'     => 'El valor del descuento no puede ser negativo.',

                // Descripción
                'descripcion.string' => 'La descripción debe ser texto.',
                'descripcion.max'    => 'La descripción no puede exceder 2000 caracteres.',

                // Especificaciones
                'especificaciones.string' => 'Las especificaciones deben ser texto.',
                'especificaciones.max'    => 'Las especificaciones no pueden exceder 3000 caracteres.',
            ]
        );

        $producto = Producto::findOrFail($id);

        // Seguridad básica: misma empresa
        if ($producto->idEmpresa != $request->idEmpresa) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permiso para modificar este producto.'
            ], 403);
        }

        $producto->nombre                   = $request->nombre;
        $producto->idTipoItem               = $request->idTipoItem;
        $producto->idUnidadMedida            = $request->idUnidadMedida;

        $producto->precioUnitarioConIva     = $request->precioUnitarioConIva;
        $producto->precioVentaConIva     = $request->precioUnitarioConIva;
        $producto->poseeDescuento           = $request->poseeDescuento;

        if ($request->poseeDescuento == 'S') {
            $producto->porcentajeDescuento      = $request->porcentajeDescuento ?? 0;
            $producto->valorDescuento           = $request->valorDescuento ?? 0;
        } else {
            $producto->porcentajeDescuento      = null;
            $producto->valorDescuento           = null;
        }

        $producto->porcentajeDescuento      = $request->porcentajeDescuento ?? null;
        $producto->valorDescuento           = $request->valorDescuento ?? null;

        $producto->precioUnitarioFinalConIVA = $request->precioUnitarioFinalConIVA ?? null;

        $producto->descripcion              = $request->descripcion ?? null;
        $producto->especificaciones         = $request->especificaciones ?? null;

        $producto->excento           = $request->excento;

        // Auditoría
        $producto->fechaEdicion            = Carbon::now();
        $producto->idUsuarioEdita        = $request->idUsuario ?? null;

        $producto->save();

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'data'    => $producto
        ], 200);
    }
}
