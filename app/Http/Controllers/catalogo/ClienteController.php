<?php

namespace App\Http\Controllers\catalogo;

use App\Http\Controllers\Controller;
use App\Models\catalogo\Cliente;
use App\Models\catalogo\TipoContribuyente;
use App\Models\catalogo\TipoPersona;
use App\Models\Empresa;
use App\Models\mh\ActividadEconomica;
use App\Models\mh\Departamento;
use App\Models\mh\Municipio;
use App\Models\mh\Pais;
use App\Models\mh\TipoDocumentoIdentidad;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $empresas = Empresa::select('id', 'nombreComercial as nombre')
            ->where('eliminado', 'N')->whereIn('id', [$request->idEmpresa])->get();

        return response()->json([
            'success' => true,
            'data' => [
                'empresas' => $empresas,
                'tiposDocumento'       => TipoDocumentoIdentidad::get(),
                'actividadesEconomicas' => ActividadEconomica::select('id', 'nombreActividad as nombre')->get(),
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

        $request->validate(
            [
                'idEmpresa'                 => 'required|integer',
                'nombreCliente'             => 'required|string|max:1500',
                'idTipoDocumentoIdentidad'  => 'required|integer',
                'numeroDocumento'           => 'required|string|max:250',
                'idActividadEconomica'      => 'required|integer',
                'idTipoPersona'             => 'required|integer',
                'idTipoContribuyente'       => 'required|integer',
                'esExento'                  => 'required|in:S,N',
                'correo'                    => 'nullable|email|max:150',
                'telefono'                  => 'nullable|string|max:12',
            ],
            [
                'idEmpresa.required'                => 'La empresa es obligatoria.',
                'idEmpresa.integer'                 => 'La empresa seleccionada no es válida.',

                'nombreCliente.required'            => 'El nombre del cliente es obligatorio.',
                'nombreCliente.string'              => 'El nombre del cliente debe ser texto.',
                'nombreCliente.max'                 => 'El nombre del cliente no puede exceder 1500 caracteres.',

                'idTipoDocumentoIdentidad.required' => 'Debe seleccionar un tipo de documento.',
                'idTipoDocumentoIdentidad.integer'  => 'El tipo de documento seleccionado no es válido.',

                'numeroDocumento.required'          => 'El número de documento es obligatorio.',
                'numeroDocumento.string'            => 'El número de documento debe ser texto.',
                'numeroDocumento.max'               => 'El número de documento no puede exceder 250 caracteres.',

                'idActividadEconomica.required'     => 'Debe seleccionar una actividad económica.',
                'idActividadEconomica.integer'      => 'La actividad económica seleccionada no es válida.',

                'idTipoPersona.required'            => 'Debe seleccionar el tipo de persona.',
                'idTipoPersona.integer'             => 'El tipo de persona seleccionado no es válido.',

                'idTipoContribuyente.required'      => 'Debe seleccionar el tipo de contribuyente.',
                'idTipoContribuyente.integer'       => 'El tipo de contribuyente seleccionado no es válido.',

                'esExento.required'                 => 'Debe indicar si el cliente es exento.',
                'esExento.in'                       => 'El valor de exento debe ser S o N.',

                'correo.email'                      => 'El correo electrónico no tiene un formato válido.',
                'correo.max'                        => 'El correo electrónico no puede exceder 150 caracteres.',

                'telefono.max'                      => 'El teléfono no puede exceder 12 caracteres.',
            ]
        );


        $cliente = new Cliente();
        $cliente->idEmpresa                = $request->idEmpresa;
        $cliente->nombreCliente            = $request->nombreCliente;
        $cliente->idTipoDocumentoIdentidad = $request->idTipoDocumentoIdentidad;
        $cliente->numeroDocumento          = $request->numeroDocumento;
        $cliente->idActividadEconomica     = $request->idActividadEconomica;
        $cliente->idTipoPersona            = $request->idTipoPersona;
        $cliente->idTipoContribuyente      = $request->idTipoContribuyente;
        $cliente->esExento                 = $request->esExento;
        $cliente->correo                   = $request->correo;
        $cliente->telefono                 = $request->telefono;
        $cliente->nrc                 = $request->nrc;

        $cliente->idPais = $request->idPais ?? null;
        $cliente->idDepartamento                   = $request->idDepartamento ?? null;
        $cliente->idMunicipio                      = $request->idMunicipio ?? null;
        $cliente->direccion                        = $request->direccion ?? null;

        $cliente->clienteFrecuente   = 'N';
        $cliente->eliminado          = 'N';
        $cliente->fechaRegistro      = Carbon::now();
        //$cliente->idUsuarioRegistra  = auth()->id();

        $cliente->save();

        return response()->json([
            'success' => true,
            'message' => 'Cliente registrado correctamente.',
            'data'    => $cliente
        ], 201);
    }


    public function show($id)
    {
        //
    }

    public function edit($id, Request $request)
    {

        // LOG DEL REQUEST COMPLETO
        Log::channel('cliente_edit')->info('REQUEST EDIT CLIENTE', [
            'cliente_id' => $id,
            'request' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);


        $cliente = Cliente::find($id);
        $empresas = Empresa::select('id', 'nombreComercial as nombre')
            ->where('eliminado', 'N')->whereIn('id', [$request->idEmpresa])->get();

        return response()->json([
            'success' => true,
            'data' => [
                'cliente' => $cliente,
                'empresas' => $empresas,
                'tiposDocumento'       => TipoDocumentoIdentidad::get(),
                'actividadesEconomicas' => ActividadEconomica::select('id', 'nombreActividad as nombre')->get(),
                'tiposPersona'         => TipoPersona::get(),
                'tiposContribuyente'   => TipoContribuyente::get(),
                'paises'               => Pais::get(),
                'departamentos'        => Departamento::get(),
                'municipios'           => Municipio::get(),
            ]
        ]);
    }


    public function update(Request $request, $id)
    {
        $request->validate(
            [
                'idEmpresa'                 => 'required|integer',
                'nombreCliente'             => 'required|string|max:1500',
                'idTipoDocumentoIdentidad'  => 'required|integer',
                'numeroDocumento'           => 'required|string|max:250',
                'idActividadEconomica'      => 'required|integer',
                'idTipoPersona'             => 'required|integer',
                'idTipoContribuyente'       => 'required|integer',
                'esExento'                  => 'required|in:S,N',
                'correo'                    => 'nullable|email|max:150',
                'telefono'                  => 'nullable|string|max:12',
            ],
            [
                'idEmpresa.required'                => 'La empresa es obligatoria.',
                'idEmpresa.integer'                 => 'La empresa seleccionada no es válida.',

                'nombreCliente.required'            => 'El nombre del cliente es obligatorio.',
                'nombreCliente.string'              => 'El nombre del cliente debe ser texto.',
                'nombreCliente.max'                 => 'El nombre del cliente no puede exceder 1500 caracteres.',

                'idTipoDocumentoIdentidad.required' => 'Debe seleccionar un tipo de documento.',
                'idTipoDocumentoIdentidad.integer'  => 'El tipo de documento seleccionado no es válido.',

                'numeroDocumento.required'          => 'El número de documento es obligatorio.',
                'numeroDocumento.string'            => 'El número de documento debe ser texto.',
                'numeroDocumento.max'               => 'El número de documento no puede exceder 250 caracteres.',

                'idActividadEconomica.required'     => 'Debe seleccionar una actividad económica.',
                'idActividadEconomica.integer'      => 'La actividad económica seleccionada no es válida.',

                'idTipoPersona.required'            => 'Debe seleccionar el tipo de persona.',
                'idTipoPersona.integer'             => 'El tipo de persona seleccionado no es válido.',

                'idTipoContribuyente.required'      => 'Debe seleccionar el tipo de contribuyente.',
                'idTipoContribuyente.integer'       => 'El tipo de contribuyente seleccionado no es válido.',

                'esExento.required'                 => 'Debe indicar si el cliente es exento.',
                'esExento.in'                       => 'El valor de exento debe ser S o N.',

                'correo.email'                      => 'El correo electrónico no tiene un formato válido.',
                'correo.max'                        => 'El correo electrónico no puede exceder 150 caracteres.',

                'telefono.max'                      => 'El teléfono no puede exceder 12 caracteres.',
            ]
        );

        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado.'
            ], 404);
        }

        $cliente->idEmpresa                = $request->idEmpresa;
        $cliente->nombreCliente            = $request->nombreCliente;
        $cliente->idTipoDocumentoIdentidad = $request->idTipoDocumentoIdentidad;
        $cliente->numeroDocumento          = $request->numeroDocumento;
        $cliente->idActividadEconomica     = $request->idActividadEconomica;
        $cliente->idTipoPersona            = $request->idTipoPersona;
        $cliente->idTipoContribuyente      = $request->idTipoContribuyente;
        $cliente->esExento                 = $request->esExento;
        $cliente->correo                   = $request->correo;
        $cliente->telefono                 = $request->telefono;

        $cliente->idPais = $request->idPais ?? null;
        $cliente->idDepartamento = $request->idDepartamento ?? null;
        $cliente->idMunicipio    = $request->idMunicipio ?? null;
        $cliente->direccion      = $request->direccion ?? null;
        $cliente->nrc                 = $request->nrc;

        // NO se tocan estos campos en update
        // $cliente->clienteFrecuente
        // $cliente->eliminado
        // $cliente->fechaRegistro

        $cliente->save();

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente.',
            'data'    => $cliente
        ]);
    }


    public function destroy($id)
    {
        //
    }
}
