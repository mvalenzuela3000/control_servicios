<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Services\BitacoraService;
use App\Services\CuotaService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProxyController extends Controller
{
    public function verificaComunicacionSIN(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }
    public function actosImpugnadosSIN(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }
    public function verificaComunicacionSEPREC(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }
    public function matriculasPorNitSEPREC(Request $request, $nit)
    {
        $request->attributes->set('param_nit', $nit);
        return $this->consumirServicioConfigurado($request);
    }
    public function matriculaPorNumeroSEPREC(Request $request, $matricula)
    {
        $request->attributes->set('param_matricula', $matricula);
        return $this->consumirServicioConfigurado($request);
    }
    public function representantesPorMatriculaSEPREC(Request $request, $matricula)
    {
        $request->attributes->set('param_matricula', $matricula);
        return $this->consumirServicioConfigurado($request);
    }
    public function impuestosAlzadaJukumari(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }

    public function impuestosJerarquicoJukumari(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }

    public function aduanaAlzadaJukumari(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }

    public function aduanaJerarquicoJukumari(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }
    /**
     * Consume un servicio externo tomando su configuración desde la BD
     * en función al endpoint interno invocado.
     */
    private function consumirServicioConfigurado(Request $request)
    {
        $inicio = microtime(true);

        $cliente = $request->attributes->get('cliente_api');
        $tokenCliente = $request->attributes->get('token_api');

        $loginUsuario = $request->header('X-User-Login') ?? $request->input('usuario');
        $nombreUsuario = $request->header('X-User-Name') ?? $request->input('nombre');
        $documentoUsuario = $request->header('X-User-Document') ?? $request->input('documento');
        $userAgent = $request->header('User-Agent');
        $ipAcceso = $request->ip();

        $requestId = uniqid('REQ_', true);
        $endpointInterno = '/' . ltrim($request->path(), '/');

        if (!$cliente) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cliente no autenticado o no disponible en el request',
                'request_id' => $requestId
            ], 401);
        }

        // Busca primero exacto; si no, busca por endpoint base sin el último parámetro
        $endpointBase = preg_replace('#/[^/]+$#', '', $endpointInterno);

        $servicio = Servicio::where(function ($q) use ($endpointInterno, $endpointBase) {
            $q->where('endpoint', $endpointInterno)
                ->orWhere('endpoint', $endpointBase);
        })
            ->where('activo', true)
            ->first();

        if (!$servicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Servicio no configurado en base de datos',
                'endpoint' => $endpointInterno,
                'endpoint_base' => $endpointBase,
                'request_id' => $requestId
            ], 404);
        }

        $clientePuedeConsumir = DB::table('api_cliente_servicios')
            ->where('id_cliente', $cliente->id)
            ->where('id_servicio', $servicio->id)
            ->where('activo', true)
            ->exists();

        if (!$clientePuedeConsumir) {
            return response()->json([
                'status' => 'error',
                'message' => 'El cliente no está autorizado para consumir este servicio',
                'request_id' => $requestId
            ], 403);
        }

        if (empty($servicio->url_destino) || empty($servicio->metodo_http)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El servicio no tiene configuración completa (url_destino o metodo_http)',
                'request_id' => $requestId
            ], 500);
        }

        $metodo = strtoupper(trim((string) $servicio->metodo_http));
        $urlDestino = trim((string) $servicio->url_destino);

        preg_match_all('/{(.*?)}/', $urlDestino, $matches);

        foreach ($matches[1] as $param) {
            $valor = $request->route($param) ?? $request->attributes->get("param_$param");

            if (!$valor) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Falta el parámetro requerido: $param",
                    'request_id' => $requestId
                ], 400);
            }

            $urlDestino = str_replace("{{$param}}", $valor, $urlDestino);
        }
        $tipoAuth = strtoupper(trim((string) $servicio->tipo_auth));

        $tokenDestino = !empty($servicio->token_destino)
            ? trim($servicio->token_destino)
            : trim((string) $tokenCliente);

        if ($servicio->requiere_token && empty($tokenDestino)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El servicio requiere token, pero no existe token disponible',
                'request_id' => $requestId
            ], 500);
        }

        if ($servicio->controla_cuota) {
            $puedeConsumir = CuotaService::puedeConsumir($servicio->id, $cliente->id);

            if (!$puedeConsumir) {
                BitacoraService::registrar([
                    'id_servicio' => $servicio->id,
                    'id_cliente' => $cliente->id,
                    'token_recibido' => $tokenCliente,
                    'login_usuario_header' => $loginUsuario,
                    'nombre_usuario_header' => $nombreUsuario,
                    'documento_usuario_header' => $documentoUsuario,
                    'ip_acceso' => $ipAcceso,
                    'dispositivo_acceso' => $userAgent,
                    'metodo_http' => $metodo,
                    'endpoint' => $endpointInterno,
                    'codigo_respuesta' => 429,
                    'tiempo_respuesta_ms' => 0,
                    'exito' => false,
                    'mensaje_error' => 'Cuota diaria excedida',
                    'request_id' => $requestId
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Cuota diaria excedida para este servicio',
                    'request_id' => $requestId
                ], 429);
            }
        }

        try {
            $client = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);

            $headers = [
                'Accept' => 'application/json',
                'User-Agent' => 'control_servicios/1.0',
            ];

            if ($servicio->requiere_token) {
                switch ($tipoAuth) {
                    case 'TOKEN':
                        $headers['Authorization'] = 'Token ' . $tokenDestino;
                        break;
                    case 'BEARER':
                        $headers['Authorization'] = 'Bearer ' . $tokenDestino;
                        break;
                }
            }

            $payload = $request->all();

            $options = [
                'headers' => $headers,
            ];

            if ($metodo === 'GET') {
                if (!empty($payload)) {
                    $options['query'] = $payload;
                }
            } else {
                if (!empty($payload)) {
                    $options['json'] = $payload;
                }
            }

            $respuesta = $client->request($metodo, $urlDestino, $options);

            $responseBody = (string) $respuesta->getBody();
            $cuerpo = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $cuerpo = $responseBody;
            }

            $tiempo = (int) ((microtime(true) - $inicio) * 1000);

            BitacoraService::registrar([
                'id_servicio' => $servicio->id,
                'id_cliente' => $cliente->id,
                'token_recibido' => $tokenCliente,
                'login_usuario_header' => $loginUsuario,
                'nombre_usuario_header' => $nombreUsuario,
                'documento_usuario_header' => $documentoUsuario,
                'ip_acceso' => $ipAcceso,
                'dispositivo_acceso' => $userAgent,
                'metodo_http' => $metodo,
                'endpoint' => $endpointInterno,
                'codigo_respuesta' => $respuesta->getStatusCode(),
                'tiempo_respuesta_ms' => $tiempo,
                'exito' => true,
                'mensaje_error' => null,
                'request_id' => $requestId
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Consumo realizado correctamente',
                'request_id' => $requestId,
                'servicio' => [
                    'id' => $servicio->id,
                    'nombre' => $servicio->nombre,
                    'metodo_http' => $metodo,
                    'url_destino' => $urlDestino
                ],
                'data' => $cuerpo
            ], $respuesta->getStatusCode());

        } catch (RequestException $e) {
            $tiempo = (int) ((microtime(true) - $inicio) * 1000);

            $codigo = 500;
            $detalleRespuesta = null;

            if ($e->hasResponse()) {
                $codigo = $e->getResponse()->getStatusCode();
                $detalleRespuesta = (string) $e->getResponse()->getBody();
            }

            BitacoraService::registrar([
                'id_servicio' => $servicio->id,
                'id_cliente' => $cliente->id,
                'token_recibido' => $tokenCliente,
                'login_usuario_header' => $loginUsuario,
                'nombre_usuario_header' => $nombreUsuario,
                'documento_usuario_header' => $documentoUsuario,
                'ip_acceso' => $ipAcceso,
                'dispositivo_acceso' => $userAgent,
                'metodo_http' => $metodo,
                'endpoint' => $endpointInterno,
                'codigo_respuesta' => $codigo,
                'tiempo_respuesta_ms' => $tiempo,
                'exito' => false,
                'mensaje_error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al consumir el servicio externo',
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'response_body' => $detalleRespuesta
            ], $codigo);

        } catch (\Throwable $e) {
            $tiempo = (int) ((microtime(true) - $inicio) * 1000);

            BitacoraService::registrar([
                'id_servicio' => $servicio->id,
                'id_cliente' => $cliente->id,
                'token_recibido' => $tokenCliente,
                'login_usuario_header' => $loginUsuario,
                'nombre_usuario_header' => $nombreUsuario,
                'documento_usuario_header' => $documentoUsuario,
                'ip_acceso' => $ipAcceso,
                'dispositivo_acceso' => $userAgent,
                'metodo_http' => $metodo,
                'endpoint' => $endpointInterno,
                'codigo_respuesta' => 500,
                'tiempo_respuesta_ms' => $tiempo,
                'exito' => false,
                'mensaje_error' => $e->getMessage(),
                'request_id' => $requestId
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno al procesar la solicitud',
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function statusJukumari(Request $request)
    {
        return $this->consumirServicioConfigurado($request);
    }
}
