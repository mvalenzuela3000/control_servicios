<?php

namespace App\Http\Controllers;

use App\Models\CiudadaniaAprobacion;
use App\Models\CiudadaniaAprobacionDocumento;
use App\Services\CiudadaniaDigitalAprobadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CiudadaniaDigitalController extends Controller
{
    public function aprobarIndividual(Request $request)
    {
        $this->validate($request, [
            'sistema' => 'required|string|max:50',
            'modulo' => 'nullable|string|max:100',
            'referencia_id' => 'nullable|string|max:100',
            'tipoDocumento' => 'required|in:PDF,JSON',
            'descripcion' => 'required|string',
            'accessToken' => 'required|string',
            'documento' => 'required|string',
        ]);

        $binario = base64_decode($request->documento, true);

        if ($binario === false) {
            return response()->json([
                'finalizado' => false,
                'mensaje' => 'Documento Base64 inválido.',
            ], 422);
        }

        $idTramite = (string) Str::uuid();
        $hashDocumento = hash('sha256', $binario);

        $payload = [
            'tipoDocumento' => $request->tipoDocumento,
            'hashDocumento' => $hashDocumento,
            'descripcion' => $request->descripcion,
            'idTramite' => $idTramite,
            'accessToken' => $request->accessToken,
            'documento' => $request->documento,
        ];

        $service = new CiudadaniaDigitalAprobadorService();

        return DB::transaction(function () use ($request, $payload, $service, $idTramite, $hashDocumento) {
            $aprobacion = CiudadaniaAprobacion::create([
                'id_tramite' => $idTramite,
                'sistema' => $request->sistema,
                'modulo' => $request->modulo,
                'referencia_id' => $request->referencia_id,
                'tipo' => 'individual',
                'estado' => 'pendiente',
                'request_payload' => $payload,
            ]);

            CiudadaniaAprobacionDocumento::create([
                'aprobacion_id' => $aprobacion->id,
                'tipo_documento' => $request->tipoDocumento,
                'hash_documento' => $hashDocumento,
                'descripcion' => $request->descripcion,
            ]);

            $respuesta = $service->aprobarIndividual($payload);
            $body = $respuesta['body'];

            $aprobacion->update([
                'estado' => $respuesta['ok'] ? 'enviado' : 'error',
                'response_payload' => $body,
                'link_aprobacion' => data_get($body, 'datos.link'),
                'mensaje' => data_get($body, 'mensaje'),
            ]);

            return response()->json([
                'finalizado' => $respuesta['ok'],
                'idTramite' => $idTramite,
                'link' => data_get($body, 'datos.link'),
                'mensaje' => data_get($body, 'mensaje'),
                'respuesta_agetic' => $body,
            ], $respuesta['ok'] ? 200 : 502);
        });
    }

    public function estado($idTramite)
    {
        $aprobacion = CiudadaniaAprobacion::with('documentos')
            ->where('id_tramite', $idTramite)
            ->first();

        if (!$aprobacion) {
            return response()->json([
                'finalizado' => false,
                'mensaje' => 'Solicitud no encontrada.',
            ], 404);
        }

        return response()->json([
            'finalizado' => true,
            'datos' => $aprobacion,
        ]);
    }

    public function callback(Request $request)
    {
        $authorization = $request->header('Authorization');
        $token = str_replace('Bearer ', '', $authorization);

        if ($token !== config('ciudadania.callback_token')) {
            return response()->json([
                'finalizado' => false,
                'mensaje' => 'Token callback no autorizado.',
            ], 401);
        }

        $requestUuid = $request->input('requestUuid') ?: $request->input('uuidSolicitud');

        $aprobacion = CiudadaniaAprobacion::where('id_tramite', $requestUuid)->first();

        if (!$aprobacion) {
            return response()->json([
                'finalizado' => false,
                'mensaje' => 'Solicitud no encontrada.',
            ], 404);
        }

        $aceptado = filter_var($request->input('aceptado'), FILTER_VALIDATE_BOOLEAN);
        $introducido = filter_var($request->input('introducido'), FILTER_VALIDATE_BOOLEAN);

        $aprobacion->update([
            'aceptado' => $aceptado,
            'introducido' => $introducido,
            'estado' => $aceptado && $introducido ? 'aceptado' : 'rechazado',
            'codigo_operacion' => $request->input('codigoOperacion'),
            'transaction_id' => $request->input('transaction_id'),
            'ci' => $request->input('ci') ?: $request->input('nroDocumento'),
            'mensaje' => $request->input('mensaje'),
            'callback_payload' => $request->all(),
        ]);

        return response()->json([
            'finalizado' => true,
            'mensaje' => 'Notificación procesada correctamente.',
        ]);
    }
}
