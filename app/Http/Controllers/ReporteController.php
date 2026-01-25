<?php

namespace App\Http\Controllers;

use App\Helpers\NumeroALetras;
use App\Models\EmpresaConfigTransmisionDte;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReporteController extends Controller
{
    /**
     * Genera el reporte JSON de la factura para consumidor final
     */
    public function reporteJson($id)
    {
        try {
            // ============================
            // 1. OBTENER DATOS DE FACTURA
            // ============================
            $encabezado = $this->obtenerFacturaById($id);

            if ($encabezado->mensaje !== 'EXITO') {
                return response()->json([
                    'success' => false,
                    'message' => $encabezado->descripcion ?? 'No se encontró la factura'
                ], 404);
            }

            $datos = $encabezado->datos;
            $detalle = $encabezado->detalle;

            // ============================
            // 2. OBTENER CLAVE PRIVADA
            // ============================
            // Obtener la clave privada de la tabla general_datos_empresa_config_transmision_dte
            // según el idEmpresa de la factura
            $config = EmpresaConfigTransmisionDte::where('idEmpresa', $datos->idEmpresa)
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la configuración de transmisión DTE para la empresa'
                ], 404);
            }

            $clavePrivada = $config->clavePrivada;

            if (empty($clavePrivada)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La clave privada no está configurada para esta empresa'
                ], 400);
            }

            // ============================
            // 3. CREAR JSON SEGÚN TIPO DE DTE
            // ============================
            // idTipoDte == 2 corresponde a Crédito Fiscal (tipoDte "03")
            // idTipoDte == 1 corresponde a Consumidor Final (tipoDte "01")
            $idTipoDte = (int)($datos->idTipoDte ?? 1);

            if ($idTipoDte == 2) {
                // Crédito Fiscal
                $jsonFactura = $this->crearFacturaCreditoFiscal($datos, $detalle, $clavePrivada);
            } else {
                // Consumidor Final (por defecto)
                $jsonFactura = $this->crearFacturaConsumidorFinal($datos, $detalle, $clavePrivada);
            }

            if ($jsonFactura->mensaje !== 'EXITO') {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar JSON de factura',
                    'error' => $jsonFactura->descripcion ?? 'Error desconocido'
                ], 500);
            }

            // ============================
            // 4. GENERAR NOMBRE DEL ARCHIVO
            // ============================
            $numeroControl = $jsonFactura->numeroControl ?? 'FACTURA_' . $id;
            $nombreArchivo = 'DTE_' . $numeroControl . '.json';

            // ============================
            // 5. DEVOLVER ARCHIVO JSON DESCARGABLE
            // ============================
            return response()->streamDownload(function () use ($jsonFactura) {
                echo $jsonFactura->jsonEncode;
            }, $nombreArchivo, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error en reporteJson', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte JSON',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los datos completos de la factura con todas las relaciones
     */
    public function obtenerFacturaById($id)
    {
        $respuesta = new \stdClass();

        try {
            $sql = "SELECT
                    encabezado.id,
                    ambiente.codigo as codigoAmbiente,
                    tipofactura.codigo as codigoTipoFactura,
                    tipofactura.nombre as modeloFacturacion,
                    tipoTransmision.codigo as codigoTipoTransmision,
                    tipoTransmision.nombre as nombreTipoTransmision,
                    tipoContingencia.codigo as codigoTipoContingencia,
                    eventoConting.motivocontingencia,
                    encabezado.versionJson,
                    encabezado.fechaHoraEmision,
                    encabezado.fechaRegistraOrden,
                    encabezado.codigoGeneracion,
                    encabezado.numeroControl,
                    encabezado.selloHacienda,
                    encabezado.estadoHacienda,
                    encabezado.idEmpresa,
                    empresa.id as idEmpresa,
                    empresa.nombre as nombreEmpresa,
                    empresa.nombreComercial,
                    empresa.nombreLogo,
                    empresa.nit,
                    empresa.numeroIVA,
                    empresa.formatoImagen,
                    empresa.colorPrimario,
                    empresa.colorSecundario,
                    empresa.colorFondo,
                    empresa.colorBorde,
                    empresa.colorTexto,
                    (select concat(economicas.codigoActividad,'%%%',economicas.nombreActividad) actividadEco
                     from general_datos_empresa_actividades_economicas actividades
                             inner join mh_actividad_economica economicas on actividades.idActividad = economicas.id
                              where actividades.idEmpresa = empresa.id and actividades.actividadPrincipal ='S' order by actividades.id desc limit 1) as actividadEconomica ,
                    encabezado.idSucursal,
                    sucursal.codigoEstablecimiento as codigoSucursal,
                    tipoEstablecimiento.codigo as codigoTipoEstablecimiento,
                    encabezado.idPuntoVenta,
                    pventa.codigoPuntoVentaMh,
                    pventa.ubicacion as nombrePuntoVenta,
                    sucursal.nombreSucursal,
                    sucursal.telefono as telefonoSucursal,
                    sucursal.correo as correoSucursal,
                    depaSucu.codigo as codigoDepartamentoSucursal,
                    depaSucu.nombre as nombreDepartamentoSucursal,
                    muniSucu.codigo as codigoMunicipioSucursal,
                    muniSucu.nombre as nombreMunicipiosucursal,
                    sucursal.direccion as direccionSucursal,
                    encabezado.idTipoDte,
                    tDocTribu.codigo as codigoTipoDte,
                    tDocTribu.nombre as nombreTipoDte,
                    encabezado.idTipoPago,
                    tipoPago.codigo as codigoTipoPago,
                    tipoPago.nombre as nombreTipoPago,
                    encabezado.subTotal,
                    encabezado.ivaRetenido1,
                    encabezado.ivaPercibido1,
                    encabezado.descuentoGravada,
                    encabezado.descuentoExenta,
                    encabezado.descuentoNoSujeto,
                    encabezado.totalDescuento,
                    encabezado.totalIVA,
                    encabezado.totalExenta,
                    encabezado.totalGravada,
                    encabezado.retencionRenta,
                    encabezado.fletes,
                    encabezado.seguros,
                    encabezado.totalPagar,
                    encabezado.idCondicionVenta,
                    condicionVenta.codigo as codigoCondicionVenta,
                    condicionVenta.nombre as nombreCondicionVenta,
                    encabezado.idPlazo,
                    tipoPlazos.codigo as codigoPlazo,
                    tipoPlazos.nombre as nombrePlazo,
                    encabezado.diasCredito,
                    recintos.id as idRecinto,
                    recintos.codigo as  codigoRecinto,
                    recintos.nombre as nombreRecinto,
                    regimen.id as idRegimen,
                    regimen.codigo as codigoRegimen,
                    regimen.nombre as nombreRegimen,
                    pais.id as idPais,
                    pais.codigo as codigoPais,
                    pais.nombre as nombrePais,
                    incoterms.id as idIncoterm,
                    incoterms.codigo as codigoIncoterm,
                    incoterms.nombre as nombreIncoterm,
                    encabezado.idTipoItem,
                    tipoItemExport.codigo as codigoTipoItem,
                    tipoItemExport.nombre as nombreTipoItem,
                    encabezado.idPos,
                    encabezado.numeroAutorizacionTC,
                    encabezado.idBancoCheque,
                    encabezado.numeroCheque,
                    encabezado.observacionesPago,
                    encabezado.idCliente,
                    cliente.nombreCliente,
                    personeria.id as idTipoPersona,
                    personeria.nombre as nombreTipoPersona,
                    tDocu.id as idTipoDocumentoIdentidad,
                    tDocu.codigo as codigoTipoDocumentoIdentidad,
                    tDocu.nombre as nombreTipoDocumentoIdentidad,
                    cliente.numeroDocumento as numeroDocumentoIdentidad,
                    cliente.nrc,
                    actividades.id as idActividadEconomica,
                    actividades.codigoActividad as codigoActividadEconomica,
                    actividades.nombreActividad as nombreActividadEconomica,
                    cliente.correo as correoCliente,
                    cliente.telefono as telefonoCliente,
                    depaCliente.id as idDepartamentoCliente,
                    depaCliente.codigo as codigoDepartamentoCliente,
                    depaCliente.nombre as nombreDepartamentoCliente,
                    muniCliente.id as idMunicipioCliente,
                    muniCliente.codigo as codigoMunicipioCliente,
                    muniCliente.nombre as nombreMunicipioCliente,
                    cliente.direccion,
                    encabezado.observaciones,
                    usuario.nombre as nombreUsuario,
                    null as documentoUsuario,
                    encaRela.codigoGeneracion as codigoGeneracionRela,
                    encaRela.fechaHoraEmision as fechaHoraEmisionRela,
                    tdocTribuRela.codigo as codigoTipoDocumentoTribuRela,
                    tdocTribuRela.nombre as nombreTipoDocumentoTribuRela,
                    tipoTransRela.codigo as codigoTipoTransmisionRela

                FROM facturacion_encabezado encabezado
                left join general_datos_empresa empresa on encabezado.idEmpresa = empresa.id
                left join mh_ambiente ambiente on encabezado.idAmbiente = ambiente.id
                left join mh_tipo_facturacion tipofactura on encabezado.idTipoFacturacion = tipofactura.id
                left join mh_tipo_contigencia tipoContingencia on encabezado.idTipoContingencia = tipoContingencia.id
                left join mh_tipo_transmision tipoTransmision on encabezado.idTipoTransmision = tipoTransmision.id
                left join facturacion_evento_contingencia eventoConting  on encabezado.idEventoContingencia = eventoConting.id
                inner join general_datos_empresa_sucursales sucursal on encabezado.idSucursal = sucursal.id
                inner join mh_tipo_establecimiento tipoEstablecimiento on sucursal.idTipoEstablecimiento = tipoEstablecimiento.id
                inner join mh_departamentos depaSucu on sucursal.idDepartamento = depaSucu.id
                inner join mh_municipios muniSucu on sucursal.idMunicipio = muniSucu.id
                left join general_datos_empresa_sucursales_puntos_venta pventa on encabezado.idPuntoVenta = pventa.id
                inner join mh_tipo_documento_tributario tDocTribu on encabezado.idTipoDte = tDocTribu.id
                left join mh_tipo_pago tipoPago on encabezado.idTipoPago = tipoPago.id
                inner join mh_condicion_venta condicionVenta on encabezado.idCondicionVenta = condicionVenta.id
                left join clientes_datos_generales cliente on encabezado.idCliente = cliente.id
                left join mh_tipo_documento_identidad tDocu on cliente.idTipoDocumentoIdentidad = tDocu.id
                left join mh_actividad_economica actividades on cliente.idActividadEconomica = actividades.id
                left join mh_departamentos depaCliente on cliente.idDepartamento = depaCliente.id
                left join mh_municipios muniCliente on cliente.idMunicipio = muniCliente.id
                left join general_personeria as personeria  on cliente.idTipoPersona = personeria.id
                left join mh_recinto_fiscal recintos on encabezado.idRecinto = recintos.id
                left join mh_regimen_exportacion regimen on encabezado.idRegimen = regimen.id
                left join mh_paises pais on pais.id = encabezado.idPaisExportacion
                left join mh_incoterms incoterms on encabezado.idIncoterms = incoterms.id
                inner join general_usuarios usuario on encabezado.idUsuarioRegistraOrden = usuario.id
                left join mh_tipo_plazos tipoPlazos on encabezado.idPlazo = tipoPlazos.id
                left join facturacion_encabezado encaRela on encabezado.idDocumentoRelacionado = encaRela.id
                left join mh_tipo_documento_tributario tdocTribuRela on encaRela.idTipoDte = tdocTribuRela.id
                left join mh_tipo_transmision as tipoTransRela on encaRela.idTipoTransmision = tipoTransRela.id
                left join mh_tipo_item as tipoItemExport on encabezado.idTipoItem = tipoItemExport.id
               where  encabezado.id = ?";

            $result = DB::select($sql, [$id]);

            if (count($result) > 0) {
                $respuesta->mensaje = "EXITO";
                $respuesta->datos = $result[0];
                $respuesta->detalle = $this->obtenerDetalleFacturaById($id);
            } else {
                $respuesta->mensaje = "SIN_DATOS";
                $respuesta->descripcion = "No se encontró la factura";
            }
        } catch (\Exception $exception) {
            Log::error('Error al obtener factura por ID', [
                'id' => $id,
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);

            $respuesta->mensaje = "ERROR";
            $respuesta->descripcion = "Error al obtener la factura: " . $exception->getMessage();
        }

        return $respuesta;
    }

    /**
     * Obtiene los detalles de la factura
     */
    private function obtenerDetalleFacturaById($id)
    {
        $sql = "SELECT
                    detalle.id,
                    detalle.idEncabezado,
                    detalle.idProducto,
                    detalle.idTipoItem,
                    detalle.idUnidadMedida,
                    detalle.cantidad,
                    detalle.precioUnitario,
                    detalle.descuento as descuentoItem,
                    detalle.excentas as excento,
                    detalle.gravadas as gravada,
                    detalle.iva,
                    producto.codigo,
                    producto.nombre,
                    producto.descripcion as descripcionExtra,
                    tipoItem.codigo as codigoTipoItem,
                    tipoItem.nombre as nombreTipoItem,
                    unidadMedida.codigo as codigoUnidadMedida,
                    unidadMedida.nombre as nombreUnidadMedida
                FROM facturacion_encabezado_detalle detalle
                left join productos producto on detalle.idProducto = producto.id
                left join mh_tipo_item tipoItem on detalle.idTipoItem = tipoItem.id
                left join mh_unidad_medida unidadMedida on detalle.idUnidadMedida = unidadMedida.id
                WHERE detalle.idEncabezado = ?
                ORDER BY detalle.id";

        return DB::select($sql, [$id]);
    }

    /**
     * Crea el JSON para factura consumidor final
     */
    private function crearFacturaConsumidorFinal($encabezado, $detalle, $clavePrivada)
    {
        $respuesta = new \stdClass();

        try {
            $direccion = null;

            // Validar si pondremos direcciones
            if (!empty($encabezado->codigoDepartamentoCliente) &&
                !empty($encabezado->codigoMunicipioCliente) &&
                !empty($encabezado->direccion)) {
                $direccion = array(
                    "departamento" => trim($encabezado->codigoDepartamentoCliente),
                    "municipio" => trim($encabezado->codigoMunicipioCliente),
                    "complemento" => trim($encabezado->direccion)
                );
            }

            $iva = 0;
            $subTotalGravada = 0;
            $subTotalExenta = 0;
            $descuentoTotal = 0;
            $subTotalProductos = 0;

            // Obtener el iva de los productos
            foreach ($detalle as $item) {
                $descuentoTotal += $item->descuentoItem ?? 0;
                if (($item->excento ?? 0) == 0.00) {
                    $subTotalGravada += $item->gravada ?? 0;
                    $iva += $item->iva ?? 0;
                } else {
                    $subTotalExenta += $item->excento ?? 0;
                }
            }

            $subTotalProductos = ($subTotalGravada + $subTotalExenta);

            // Actividad económica
            $actividad = explode('%%%', $encabezado->actividadEconomica ?? '');
            $codigoActividad = !empty($actividad[0]) ? trim($actividad[0]) : '';
            $nombreActividad = !empty($actividad[1]) ? $actividad[1] : '';

            // Convertir total a letras
            $formatter = new NumeroALetras();
            $totalLetras = $formatter->toInvoice($encabezado->totalPagar, 2, 'CENTAVOS');

            // Determinar versión JSON
            $versionFAC = $encabezado->versionJson ?? 1;

            $datosJson = array(
                "nit" => str_replace("-", "", $encabezado->nit),
                "activo" => true,
                "passwordPri" => $clavePrivada,
                "dteJson" => array(
                    'identificacion' => array(
                        "version" => intval($versionFAC),
                        "ambiente" => $encabezado->codigoAmbiente,
                        "tipoDte" => $encabezado->codigoTipoDte,
                        "numeroControl" => $encabezado->numeroControl,
                        "codigoGeneracion" => $encabezado->codigoGeneracion,
                        "tipoModelo" => intval($encabezado->codigoTipoFactura ?? 1),
                        "tipoOperacion" => intval($encabezado->codigoTipoTransmision ?? 1),
                        "tipoContingencia" => !empty($encabezado->codigoTipoContingencia) ? intval($encabezado->codigoTipoContingencia) : null,
                        "motivoContin" => !empty($encabezado->motivocontingencia) ? $encabezado->motivocontingencia : null,
                        "fecEmi" => Carbon::parse($encabezado->fechaHoraEmision)->format('Y-m-d'),
                        "horEmi" => Carbon::parse($encabezado->fechaHoraEmision)->format('H:i:s'),
                        "tipoMoneda" => "USD"
                    ),
                    "emisor" => array(
                        "nit" => str_replace("-", "", $encabezado->nit),
                        "nrc" => str_replace("-", "", $encabezado->numeroIVA ?? ''),
                        "nombre" => $encabezado->nombreEmpresa,
                        "codActividad" => $codigoActividad,
                        "descActividad" => $nombreActividad,
                        "nombreComercial" => $encabezado->nombreComercial,
                        "tipoEstablecimiento" => $encabezado->codigoTipoEstablecimiento,
                        "direccion" => array(
                            "departamento" => $encabezado->codigoDepartamentoSucursal,
                            "municipio" => $encabezado->codigoMunicipioSucursal,
                            "complemento" => $encabezado->direccionSucursal ?? '',
                        ),
                        "telefono" => $encabezado->telefonoSucursal,
                        "codEstableMH" => $encabezado->codigoSucursal,
                        "codEstable" => null,
                        "codPuntoVenta" => null,
                        "codPuntoVentaMH" => $encabezado->codigoPuntoVentaMh,
                        "correo" => $encabezado->correoSucursal
                    ),
                    "receptor" => array(
                        "tipoDocumento" => empty(trim($encabezado->codigoTipoDocumentoIdentidad ?? '')) ||
                                       $encabezado->codigoTipoDocumentoIdentidad == 0 ||
                                       $encabezado->codigoTipoDocumentoIdentidad == "null" ? null : trim($encabezado->codigoTipoDocumentoIdentidad),
                        "numDocumento" => $encabezado->codigoTipoDocumentoIdentidad == 13 ?
                                         ($encabezado->numeroDocumentoIdentidad != null ? $encabezado->numeroDocumentoIdentidad : null) :
                                         (!empty(trim($encabezado->numeroDocumentoIdentidad ?? '')) ? str_replace("-", "", trim($encabezado->numeroDocumentoIdentidad)) : null),
                        "nrc" => null,
                        "nombre" => trim($encabezado->nombreCliente ?? ''),
                        "codActividad" => empty(trim($encabezado->codigoActividadEconomica ?? '')) ||
                                        $encabezado->codigoActividadEconomica == "null" ? null : trim($encabezado->codigoActividadEconomica),
                        "descActividad" => empty(trim($encabezado->nombreActividadEconomica ?? '')) ||
                                         $encabezado->nombreActividadEconomica == "No aplica" ? null : trim($encabezado->nombreActividadEconomica),
                        "direccion" => $direccion,
                        "telefono" => $encabezado->telefonoCliente,
                        "correo" => $encabezado->correoCliente
                    ),
                    "ventaTercero" => null,
                    "cuerpoDocumento" => array(),
                    "resumen" => array(
                        "totalNoSuj" => 0,
                        "totalExenta" => (float)sprintf('%0.2f', $encabezado->totalExenta ?? 0),
                        "totalGravada" => (float)sprintf('%0.2f', $subTotalGravada),
                        "subTotalVentas" => (float)sprintf('%0.2f', $subTotalProductos),
                        "descuNoSuj" => 0,
                        "descuExenta" => 0,
                        "descuGravada" => 0,
                        "porcentajeDescuento" => 0,
                        "totalDescu" => (float)sprintf('%0.2f', $descuentoTotal),
                        "tributos" => null,
                        "subTotal" => (float)sprintf('%0.2f', $subTotalProductos),
                        "ivaRete1" => (float)sprintf('%0.2f', $encabezado->ivaRetenido1 ?? 0),
                        "reteRenta" => (float)sprintf('%0.2f', $encabezado->retencionRenta ?? 0),
                        "montoTotalOperacion" => (float)sprintf('%0.2f', $subTotalProductos),
                        "totalNoGravado" => 0,
                        "totalPagar" => (float)sprintf('%0.2f', $encabezado->totalPagar),
                        "totalLetras" => $totalLetras,
                        "saldoFavor" => 0,
                        "condicionOperacion" => intval($encabezado->codigoCondicionVenta),
                        "pagos" => null,
                        "numPagoElectronico" => null,
                        "totalIva" => (float)sprintf('%0.2f', $iva)
                    ),
                    "extension" =>  array(
                        "nombEntrega" => $encabezado->nombreUsuario ?? '',
                        "docuEntrega" => $encabezado->documentoUsuario ?? '',
                        "nombRecibe" => $encabezado->nombreCliente ?? '',
                        "docuRecibe" => $encabezado->numeroDocumentoIdentidad ?? '',
                        "observaciones" =>  $encabezado->observaciones ?? '',
                        "placaVehiculo" => null
                    ),
                    "apendice" => null,
                    "documentoRelacionado" => null,
                    "otrosDocumentos" => null,
                )
            );

            // Agregar items al cuerpo del documento
            $contador = 0;
            foreach ($detalle as $item) {
                $itemArray = array(
                    "numItem" => $contador += 1,
                    "tipoItem" => intval($item->codigoTipoItem ?? 1),
                    "numeroDocumento" => null,
                    "cantidad" => (float)sprintf('%0.6f', $item->cantidad),
                    "codigo" => $item->codigo ?? '',
                    "codTributo" => null,
                    "uniMedida" => intval($item->codigoUnidadMedida ?? 1),
                    "descripcion" => $item->nombre ?? '',
                    "precioUni" => (float)sprintf('%0.6f', $item->precioUnitario),
                    "montoDescu" => (float)sprintf('%0.6f', $item->descuentoItem ?? 0),
                    "ventaNoSuj" => 0,
                    "ventaExenta" => (float)sprintf('%0.6f', $item->excento ?? 0),
                    "ventaGravada" => (float)sprintf('%0.6f', $item->gravada ?? 0),
                    "tributos" => null,
                    "psv" => 0,
                    "ivaItem" => (float)sprintf('%0.6f', $item->iva ?? 0),
                    "noGravado" => 0
                );

                $datosJson["dteJson"]['cuerpoDocumento'][] = $itemArray;
            }

            $respuesta->mensaje = "EXITO";
            $respuesta->numeroControl = $encabezado->numeroControl;
            $respuesta->fechaHoraEmision = $encabezado->fechaHoraEmision;
            $respuesta->jsonEncode = json_encode($datosJson);

        } catch (\Exception $exception) {
            Log::error('Error al crear JSON consumidor final', [
                'idFactura' => $encabezado->id ?? null,
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);

            $respuesta->mensaje = "ERROR";
            $respuesta->descripcion = "Error al crear el json para consumidor final: " . $exception->getMessage();
        }

        return $respuesta;
    }

    /**
     * Crea el JSON para factura crédito fiscal (tipo DTE 03)
     */
    private function crearFacturaCreditoFiscal($encabezado, $detalle, $clavePrivada)
    {
        $respuesta = new \stdClass();

        try {
            $direccion = null;

            // Validar si pondremos direcciones
            if (!empty($encabezado->codigoDepartamentoCliente) &&
                !empty($encabezado->codigoMunicipioCliente) &&
                !empty($encabezado->direccion)) {
                $direccion = array(
                    "departamento" => trim($encabezado->codigoDepartamentoCliente),
                    "municipio" => trim($encabezado->codigoMunicipioCliente),
                    "complemento" => trim($encabezado->direccion)
                );
            }

            $iva = 0;
            $subTotalGravada = 0;
            $subTotalExenta = 0;
            $descuentoTotal = 0;
            $subTotalProductos = 0;
            $esSoloExcento = false;

            // Obtener el iva de los productos
            foreach ($detalle as $item) {
                $descuentoTotal += $item->descuentoItem ?? 0;
                if (($item->excento ?? 0) == 0.00) {
                    $subTotalGravada += $item->gravada ?? 0;
                    $iva += $item->iva ?? 0;
                    $esSoloExcento = false;
                } else {
                    $subTotalExenta += $item->excento ?? 0;
                    $esSoloExcento = true;
                }
            }

            $subTotalProductos = ($subTotalGravada + $subTotalExenta);
            $montoTotalOperacion = ($subTotalProductos + ($encabezado->totalIVA ?? 0));

            // Actividad económica
            $actividad = explode('%%%', $encabezado->actividadEconomica ?? '');
            $codigoActividad = !empty($actividad[0]) ? trim($actividad[0]) : '';
            $nombreActividad = !empty($actividad[1]) ? $actividad[1] : '';

            // Convertir total a letras
            $formatter = new NumeroALetras();
            $totalLetras = $formatter->toInvoice($encabezado->totalPagar, 2, 'CENTAVOS');

            // Determinar tributos
            $tributos = null;
            if (!$esSoloExcento) {
                $tributos = array(
                    array(
                        "codigo" => "20",
                        "descripcion" => "Impuesto al Valor Agregado 13%",
                        "valor" => (float)sprintf('%0.2f', $encabezado->totalIVA ?? 0)
                    )
                );
            }

            // Determinar versión JSON
            $versionFAC = $encabezado->versionJson ?? 3;

            $datosJson = array(
                "nit" => str_replace("-", "", $encabezado->nit),
                "activo" => true,
                "passwordPri" => $clavePrivada,
                "dteJson" => array(
                    "identificacion" => array(
                        "version" => intval($versionFAC),
                        "ambiente" => trim($encabezado->codigoAmbiente),
                        "tipoDte" => "03",
                        "numeroControl" => $encabezado->numeroControl,
                        "codigoGeneracion" => $encabezado->codigoGeneracion,
                        "tipoModelo" => intval($encabezado->codigoTipoFactura ?? 1),
                        "tipoOperacion" => intval($encabezado->codigoTipoTransmision ?? 1),
                        "tipoContingencia" => !empty($encabezado->codigoTipoContingencia) ? intval($encabezado->codigoTipoContingencia) : null,
                        "motivoContin" => !empty($encabezado->motivocontingencia) ? $encabezado->motivocontingencia : null,
                        "fecEmi" => Carbon::parse($encabezado->fechaHoraEmision)->format('Y-m-d'),
                        "horEmi" => Carbon::parse($encabezado->fechaHoraEmision)->format('H:i:s'),
                        "tipoMoneda" => "USD"
                    ),
                    "emisor" => array(
                        "nit" => str_replace("-", "", $encabezado->nit),
                        "nrc" => str_replace("-", "", $encabezado->numeroIVA ?? ''),
                        "nombre" => $encabezado->nombreEmpresa,
                        "codActividad" => $codigoActividad,
                        "descActividad" => $nombreActividad,
                        "nombreComercial" => $encabezado->nombreComercial,
                        "tipoEstablecimiento" => $encabezado->codigoTipoEstablecimiento,
                        "direccion" => array(
                            "departamento" => $encabezado->codigoDepartamentoSucursal,
                            "municipio" => $encabezado->codigoMunicipioSucursal,
                            "complemento" => $encabezado->direccionSucursal ?? '',
                        ),
                        "telefono" => $encabezado->telefonoSucursal,
                        "codEstableMH" => $encabezado->codigoSucursal,
                        "codEstable" => null,
                        "codPuntoVenta" => null,
                        "codPuntoVentaMH" => $encabezado->codigoPuntoVentaMh,
                        "correo" => $encabezado->correoSucursal
                    ),
                    "receptor" => array(
                        "nit" => empty(trim($encabezado->numeroDocumentoIdentidad ?? '')) ? null : str_replace("-", "", trim($encabezado->numeroDocumentoIdentidad)),
                        "nrc" => empty(trim($encabezado->nrc ?? '')) ? null : str_replace("-", "", trim($encabezado->nrc)),
                        "nombre" => empty(trim($encabezado->nombreCliente ?? '')) ? null : trim($encabezado->nombreCliente),
                        "codActividad" => empty(trim($encabezado->codigoActividadEconomica ?? '')) ? null : trim($encabezado->codigoActividadEconomica),
                        "descActividad" => empty(trim($encabezado->nombreActividadEconomica ?? '')) ? null : trim($encabezado->nombreActividadEconomica),
                        "nombreComercial" => null,
                        "direccion" => array(
                            "departamento" => trim($encabezado->codigoDepartamentoCliente ?? ''),
                            "municipio" => trim($encabezado->codigoMunicipioCliente ?? ''),
                            "complemento" => trim($encabezado->direccion ?? '')
                        ),
                        "telefono" => $encabezado->telefonoCliente,
                        "correo" => $encabezado->correoCliente
                    ),
                    "cuerpoDocumento" => array(),
                    "resumen" => array(
                        "totalNoSuj" => 0,
                        "totalExenta" => (float)sprintf('%0.2f', $encabezado->totalExenta ?? 0),
                        "totalGravada" => (float)sprintf('%0.2f', $subTotalGravada),
                        "subTotalVentas" => (float)sprintf('%0.2f', $subTotalProductos),
                        "descuNoSuj" => 0,
                        "descuExenta" => 0,
                        "descuGravada" => 0,
                        "porcentajeDescuento" => 0,
                        "totalDescu" => (float)sprintf('%0.2f', $descuentoTotal),
                        "tributos" => $tributos,
                        "subTotal" => (float)sprintf('%0.2f', $subTotalProductos),
                        "ivaPerci1" => (float)sprintf('%0.2f', $encabezado->ivaPercibido1 ?? 0),
                        "ivaRete1" => (float)sprintf('%0.2f', $encabezado->ivaRetenido1 ?? 0),
                        "reteRenta" => (float)sprintf('%0.2f', $encabezado->retencionRenta ?? 0),
                        "montoTotalOperacion" => (float)sprintf('%0.2f', $montoTotalOperacion),
                        "totalNoGravado" => 0,
                        "totalPagar" => (float)sprintf('%0.2f', $encabezado->totalPagar),
                        "totalLetras" => $totalLetras,
                        "saldoFavor" => 0,
                        "condicionOperacion" => intval($encabezado->codigoCondicionVenta),
                        "pagos" => array(
                            array(
                                "codigo" => $encabezado->codigoTipoPago ?? null,
                                "montoPago" => (float)sprintf('%0.2f', $encabezado->totalPagar),
                                "referencia" => !empty($encabezado->numeroAutorizacionTC) ? $encabezado->numeroAutorizacionTC : null,
                                "plazo" => !empty($encabezado->codigoPlazo) ? ($encabezado->codigoPlazo != "null" ? $encabezado->codigoPlazo : null) : null,
                                "periodo" => !empty($encabezado->diasCredito) ? $encabezado->diasCredito : null,
                            )
                        ),
                        "numPagoElectronico" => null
                    ),
                    "extension" =>  array(
                        "nombEntrega" => $encabezado->nombreUsuario ?? '',
                        "docuEntrega" => $encabezado->documentoUsuario ?? '',
                        "nombRecibe" => $encabezado->nombreCliente ?? '',
                        "docuRecibe" => $encabezado->numeroDocumentoIdentidad ?? '',
                        "observaciones" =>  $encabezado->observaciones ?? '',
                        "placaVehiculo" => null
                    ),
                    "apendice" => null,
                    "documentoRelacionado" => null,
                    "otrosDocumentos" => null,
                    "ventaTercero" => null
                )
            );

            // Agregar items al cuerpo del documento
            $contador = 0;
            foreach ($detalle as $item) {
                // Determinar tributos por item
                $tributosItem = null;
                if (($item->excento ?? 0) > 0) {
                    $tributosItem = null;
                } else {
                    $tributosItem = array("20");
                }

                $itemArray = array(
                    "numItem" => $contador += 1,
                    "tipoItem" => intval($item->codigoTipoItem ?? 1),
                    "numeroDocumento" => null,
                    "cantidad" => (float)sprintf('%0.4f', $item->cantidad),
                    "codigo" => $item->codigo ?? '',
                    "codTributo" => null,
                    "uniMedida" => intval($item->codigoUnidadMedida ?? 1),
                    "descripcion" => $item->nombre ?? '',
                    "precioUni" => (float)sprintf('%0.4f', $item->precioUnitario),
                    "montoDescu" => (float)sprintf('%0.4f', $item->descuentoItem ?? 0),
                    "ventaNoSuj" => 0,
                    "ventaExenta" => (float)sprintf('%0.4f', $item->excento ?? 0),
                    "ventaGravada" => (float)sprintf('%0.4f', $item->gravada ?? 0),
                    "tributos" => $tributosItem,
                    "psv" => 0,
                    "noGravado" => 0
                );

                $datosJson["dteJson"]['cuerpoDocumento'][] = $itemArray;
            }

            $respuesta->mensaje = "EXITO";
            $respuesta->numeroControl = $encabezado->numeroControl;
            $respuesta->fechaHoraEmision = $encabezado->fechaHoraEmision;
            $respuesta->jsonEncode = json_encode($datosJson);

        } catch (\Exception $exception) {
            Log::error('Error al crear JSON crédito fiscal', [
                'idFactura' => $encabezado->id ?? null,
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);

            $respuesta->mensaje = "ERROR";
            $respuesta->descripcion = "Error al crear el json para crédito fiscal: " . $exception->getMessage();
        }

        return $respuesta;
    }
}
