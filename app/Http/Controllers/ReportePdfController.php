<?php

namespace App\Http\Controllers;

use App\Helpers\NumeroALetras;
use App\Http\Controllers\ReporteController;
use Carbon\Carbon;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportePdfController extends Controller
{

    public function reportePdf($id)
    {
        try {
            // ============================
            // 1. OBTENER DATOS DE FACTURA
            // ============================
            $reporteController = new ReporteController();
            $encabezado = $reporteController->obtenerFacturaById($id);

            if ($encabezado->mensaje !== 'EXITO') {
                return response()->json([
                    'success' => false,
                    'message' => $encabezado->descripcion ?? 'No se encontró la factura'
                ], 404);
            }

            $datos = $encabezado->datos;

            $detalle = $encabezado->detalle;

            // ============================
            // 2. PREPARAR DATOS PARA PDF
            // ============================
            $ambiente = $datos->codigoAmbiente == "00"
                ? "DOCUMENTO DE PRUEBA, SIN VALIDEZ ANTE EL MINISTERIO HACIENDA"
                : " ";

            $idTipoDte = (int)($datos->idTipoDte ?? 1);

            // Colores de la empresa
            $primario = $datos->colorPrimario ?? '#003366';
            $secundario = $datos->colorSecundario ?? '#0055A4';
            $acento = '#CE1126';
            $fondo = $datos->colorFondo ?? '#F2F2F2';
            $borde = $datos->colorBorde ?? '#CCCCCC';
            $texto = $datos->colorTexto ?? '#000000';
            $formatoImagen = $datos->formatoImagen ?? 1;

            // Actividad económica
            $actividad = explode('%%%', $datos->actividadEconomica ?? '');
            $actividadEconomica = !empty($actividad[1]) ? $actividad[1] : '';

            // ============================
            // 3. GENERAR QR CODE (igual que FacturaController)
            // ============================
            $fechaEmision = Carbon::parse($datos->fechaHoraEmision)->format('Y-m-d');

            $urlQr = "https://admin.factura.gob.sv/consultaPublica"
                . "?ambiente=" . ($datos->codigoAmbiente ?? '00')
                . "&codGen={$datos->codigoGeneracion}"
                . "&fechaEmi={$fechaEmision}";

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'scale' => 6,
            ]);

            $qrDataUri = (new QRCode($options))->render($urlQr);

            // El render() ya devuelve "data:image/png;base64,XXXXX"
            // Extraemos solo la parte del base64 para usar en la vista
            if (strpos($qrDataUri, 'data:image/png;base64,') === 0) {
                $qrBase64 = substr($qrDataUri, 22); // Eliminar "data:image/png;base64,"
            } else {
                // Si por alguna razón no viene con el prefijo, usar directamente
                $qrBase64 = $qrDataUri;
            }

            // Verificar que el QR se generó correctamente
            if (empty($qrBase64)) {
                Log::warning('QR code no se generó correctamente', [
                    'urlQr' => $urlQr,
                    'codigoGeneracion' => $datos->codigoGeneracion
                ]);
            }

            // ============================
            // 4. RUTA DEL LOGO
            // ============================
            $logoPath = public_path('logos/' . $datos->nombreLogo);
            $urlLogo = null;
            if (!empty($datos->nombreLogo) && File::exists($logoPath)) {
                // Para vista HTML usar URL relativa, para PDF usar ruta absoluta
                $urlLogo = asset('logos/' . $datos->nombreLogo);
                $logoAbsolutePath = $logoPath; // Para PDF
            } else {
                $logoAbsolutePath = null;
            }

            // Dimensiones del logo según formato
            $height = null;
            $width = null;
            $br = null;
            switch ($formatoImagen) {
                case 1: // vertical
                    $br = "<br>";
                    $height = 120;
                    $width = 110;
                    break;
                case 2: // horizontal
                    $br = "<br><br><br>";
                    $height = 58;
                    $width = 135;
                    break;
                default:
                    $br = "<br>";
                    $height = 120;
                    $width = 110;
                    break;
            }

            // ============================
            // 5. PREPARAR DETALLES
            // ============================
            $detallesFormateados = [];
            foreach ($detalle as $item) {
                $descripcionExtra = $item->descripcionExtra ?? '';
                $descripcion = str_replace("\t", '    ', $descripcionExtra);
                $descripcion = preg_replace('/  /', '&nbsp;&nbsp;', $descripcion);
                $descripcion = nl2br(htmlspecialchars($descripcion));

                $detallesFormateados[] = [
                    'numero' => count($detallesFormateados) + 1,
                    'cantidad' => $item->cantidad,
                    'unidadMedida' => $item->nombreUnidadMedida ?? '',
                    'nombre' => $item->nombre ?? '',
                    'descripcion' => $descripcion,
                    'precioUnitario' => $item->precioUnitario,
                    'descuentoItem' => $item->descuentoItem ?? 0,
                    'excento' => $item->excento ?? 0,
                    'gravada' => $item->gravada ?? 0,
                ];
            }

            // ============================
            // 6. TOTALES EN LETRAS
            // ============================
            $formatter = new NumeroALetras();
            $totalLetras = $formatter->toInvoice($datos->totalPagar, 2, 'CENTAVOS');


            // ============================
            // 7. GENERAR PDF
            // ============================
            // Vista HTML para verificar que todo funciona (comentado para devolver PDF)
            // return view('pdf.factura_dte', [
            //     'datos' => $datos,
            //     'detalle' => $detallesFormateados,
            //     'ambiente' => $ambiente,
            //     'idTipoDte' => $idTipoDte,
            //     'actividadEconomica' => $actividadEconomica,
            //     'primario' => $primario,
            //     'secundario' => $secundario,
            //     'acento' => $acento,
            //     'fondo' => $fondo,
            //     'borde' => $borde,
            //     'texto' => $texto,
            //     'formatoImagen' => $formatoImagen,
            //     'urlLogo' => $urlLogo,
            //     'logoAbsolutePath' => $logoAbsolutePath ?? null,
            //     'qrBase64' => $qrBase64,
            //     'height' => $height,
            //     'width' => $width,
            //     'br' => $br,
            //     'totalLetras' => $totalLetras,
            // ]);
            $pdf = Pdf::loadView('pdf.factura_dte', [
                'datos' => $datos,
                'detalle' => $detallesFormateados,
                'ambiente' => $ambiente,
                'idTipoDte' => $idTipoDte,
                'actividadEconomica' => $actividadEconomica,
                'primario' => $primario,
                'secundario' => $secundario,
                'acento' => $acento,
                'fondo' => $fondo,
                'borde' => $borde,
                'texto' => $texto,
                'formatoImagen' => $formatoImagen,
                'urlLogo' => $logoAbsolutePath ?? null, // Para PDF usar ruta absoluta
                'logoAbsolutePath' => $logoAbsolutePath ?? null,
                'qrBase64' => $qrBase64,
                'height' => $height,
                'width' => $width,
                'br' => $br,
                'totalLetras' => $totalLetras,
            ])->setPaper('letter', 'portrait');

            // ============================
            // 8. GUARDAR PDF EN DISCO
            // ============================
            $year = Carbon::parse($datos->fechaHoraEmision)->format('Y');
            $month = Carbon::parse($datos->fechaHoraEmision)->format('m');
            $yearMonth = $year . '-' . $month;

            $baseDir = storage_path('app/public/recursos/dte');
            $companyDir = $baseDir . DIRECTORY_SEPARATOR . $datos->idEmpresa;
            $targetDir = $companyDir . DIRECTORY_SEPARATOR . $yearMonth;

            // Crear directorios si no existen
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0775, true);
            }

            $filename = $datos->idEmpresa . '_' . $datos->codigoGeneracion . '.pdf';
            $fullpath = $targetDir . DIRECTORY_SEPARATOR . $filename;

            // Guardar PDF
            $pdf->save($fullpath);

            // Limpiar archivo temporal del QR (opcional, puedes mantenerlo para referencia)
            // if (isset($qrFullPath) && File::exists($qrFullPath)) {
            //     File::delete($qrFullPath);
            // }

            // ============================
            // 9. DEVOLVER ARCHIVO PDF
            // ============================
            return response()->download($fullpath, 'DTE_' . $datos->numeroControl . '.pdf', [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Throwable $e) {
            Log::error('Error al generar PDF', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
