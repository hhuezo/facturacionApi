<?php

namespace App\Http\Controllers\catalogo;

use App\Http\Controllers\Controller;
use App\Models\catalogo\Cliente;
use App\Models\catalogo\TipoContribuyente;
use App\Models\catalogo\TipoPersona;
use App\Models\mh\ActividadEconomica;
use App\Models\mh\Departamento;
use App\Models\mh\Municipio;
use App\Models\mh\Pais;
use App\Models\mh\TipoDocumentoIdentidad;
use Illuminate\Http\Request;

class ClienteController extends Controller
{

    public function index(Request $request)
    {
        $idEmpresa = $request->idEmpresa ?? 0;
        $clientes = Cliente::join('mh_actividad_economica as act', 'act.id', '=', 'clientes_datos_generales.idActividadEconomica')
            ->select(
                'clientes_datos_generales.id',
                'clientes_datos_generales.nombreCliente',
                'clientes_datos_generales.numeroDocumento',
                'clientes_datos_generales.telefono',
                'clientes_datos_generales.correo',
                'act.nombreActividad as actividad_nombre'
            )
            ->where('clientes_datos_generales.idEmpresa', $idEmpresa)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $clientes,
        ], 200);
    }

    public function create(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'tiposDocumento'       => TipoDocumentoIdentidad::get(),
                'actividadesEconomicas' => ActividadEconomica::select('id','nombreActividad as nombre')->get(),
                'tiposPersona'         => TipoPersona::get(),
                'tiposContribuyente'   => TipoContribuyente::get(),
                'paises'               => Pais::get(),
                'departamentos'        => Departamento::get(),
                'municipios'           => Municipio::get(),
            ]
        ]);
    }


    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
