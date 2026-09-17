<?php

namespace App\Http\Controllers;

use App\Services\OnlyOfficePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PdfConversionController extends Controller
{
    public function convertir(Request $request,OnlyOfficePdfService $service) {
        $this->validate(
            $request,
            [
                'archivo' =>
                    'required|file|mimes:docx|max:30720'
            ]
        );
        $requestId =(string) Str::uuid();
        $archivo =$request->file('archivo');
        $nombreOriginal =basename($archivo->getClientOriginalName());
        $baseDir = rtrim((string) env('ONLYOFFICE_SOURCE_DIR', ''),'/\\');
        if ($baseDir === '') {
            throw new \RuntimeException(
                'ONLYOFFICE_SOURCE_DIR no se encuentra configurado.'
            );
        }
        $directorio =$baseDir. '/'. $requestId;

        /* Nunca usamos el nombre real físicamente */
        $nombreTemporal = 'documento.docx';
        try {
            /* Crear temporal*/
            File::makeDirectory($directorio, 0750, true, true);
            $rutaTemporal = $directorio . DIRECTORY_SEPARATOR . $nombreTemporal;
            if (!File::copy($archivo->getRealPath(), $rutaTemporal)) {
                throw new \RuntimeException(
                    'No fue posible preparar el documento temporal para la conversión.'
                );
            }
            @chmod($rutaTemporal, 0640);
            /* URL solamente visible dentro de Docker */
            $sourceUrl =rtrim(env('ONLYOFFICE_SOURCE_BASE'), '/') . '/' . $requestId . '/' . $nombreTemporal;
            /*Convertir*/
            $resultado = $service->convertir($sourceUrl, $nombreOriginal, $requestId);
            $nombreSalida = pathinfo($nombreOriginal, PATHINFO_FILENAME) . '.pdf';
            /*Respuesta BINARIA*/
            return response(
                $resultado['contenido'],
                200,
                [
                    'Content-Type' =>'application/pdf',
                    'Content-Disposition' =>'attachment; filename="'. $nombreSalida. '"',
                    'Content-Length' =>$resultado['tamano'],
                    'X-SHA256' =>$resultado['sha256'],
                    'X-PDF-Size' =>$resultado['tamano'],
                    'X-Request-Id' =>$resultado['request_id'],
                    'Cache-Control' =>'no-store, private',
                ]
            );
        } catch (\Throwable $e) {
            report($e);
            return response()->json(
                [
                    'status' =>'error',
                    'message' =>'No fue posible convertir el documento a PDF.',
                    'request_id' =>$requestId,
                ],500
            );
        } finally {
            /* El DOCX NO queda almacenado en control_servicios*/
            if (File::exists($directorio))
            {
                File::deleteDirectory($directorio);
            }
        }
    }
}
