<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $inicio = microtime(true);
        /*
         * 1. REQUEST ID ÚNICO PARA TODA LA OPERACIÓN
         * Si JUKUMARI ya envió uno, lo conservamos, Si no, control_servicios lo genera.
         */
        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }
        $request->attributes->set('request_id',$requestId);
        /*
         * 2. IDENTIFICAR EL SERVICIO
         */
        $servicio = $this->resolverServicio($request);
        /*
         * Si la ruta está protegida con api.token pero no existe en api_servicios, detenemos la ejecución.
         * No podemos registrar api_consumos porque id_servicio  es NOT NULL.
         */
        if (!$servicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'El servicio solicitado no se encuentra registrado o está inactivo.',
                'request_id' => $requestId,
            ], 404);
        }
        /*
         * Dejamos disponible el servicio para controladores u otros middlewares.
         */
        $request->attributes->set('servicio_api',$servicio);
        /*
         * 3. OBTENER TOKEN
         */
        [$token, $authScheme] = $this->obtenerTokenDesdeRequest($request);
        if (empty($token)) {
            $response = response()->json([
                'status' => 'error',
                'message' =>
                    'No se envió un token válido en la cabecera X-Api-Token o Authorization',
                'accepted_formats' => [
                    'X-Api-Token: <token>',
                    'Authorization: Token <token>',
                    'Authorization: Bearer <token>',
                ],
                'request_id' => $requestId,
            ], 401);
            /*
             * El servicio sí existe, por lo que podemos auditar incluso el intento sin token.
             */
            $this->registrarConsumo(
                $request,
                $servicio,
                null,
                null,
                401,
                false,
                'No se envió un token válido.',
                $requestId,
                $inicio
            );
            return $response;
        }

        /*
         * 4. VALIDAR CLIENTE
         */
        $cliente = Cliente::where('token', $token)->where('activo', true)->first();

        if (!$cliente) {
            $response = response()->json([
                'status' => 'error',
                'message' => 'Token inválido o cliente inactivo',
                'request_id' => $requestId,
            ], 401);
            $this->registrarConsumo(
                $request,
                $servicio,
                null,
                $token,
                401,
                false,
                'Token inválido o cliente inactivo.',
                $requestId,
                $inicio
            );
            return $response;
        }

        /*
         * 5. VERIFICAR AUTORIZACIÓN CLIENTE <-> SERVICIO
         */
        $autorizado = DB::table('api_cliente_servicios')->where('id_cliente', $cliente->id)->where('id_servicio', $servicio->id)->where('activo', true)->exists();

        if (!$autorizado) {
            $response = response()->json([
                'status' => 'error',
                'message' =>
                    'El cliente no está autorizado para consumir este servicio',
                'request_id' => $requestId,
            ], 403);
            $this->registrarConsumo(
                $request,
                $servicio,
                $cliente,
                $token,
                403,
                false,
                'Cliente no autorizado para consumir el servicio.',
                $requestId,
                $inicio
            );
            return $response;
        }

        /*
         * 6. AGREGAR DATOS AL REQUEST
         */
        $request->attributes->set('cliente_api', $cliente);
        $request->attributes->set('token_api', $token);
        $request->attributes->set('auth_scheme', $authScheme);

        /*
         * 7. EJECUTAR CONTROLADOR
         */
        try {
            $response = $next($request);
            $codigoRespuesta = $response->getStatusCode();
            $exito = $codigoRespuesta >= 200 && $codigoRespuesta < 400;
            $mensajeError = null;
            if (!$exito) {
                $mensajeError =
                    $this->obtenerMensajeError(
                        $response,
                        $codigoRespuesta
                    );
            }
            /*
             * 8. REGISTRAR CONSUMO
             */
            $this->registrarConsumo(
                $request,
                $servicio,
                $cliente,
                $token,
                $codigoRespuesta,
                $exito,
                $mensajeError,
                $requestId,
                $inicio
            );
            /*
             * Si el controlador no colocó X-Request-Id, lo añadimos aquí.
             */
            if (!$response->headers->has('X-Request-Id')) {
                $response->headers->set(
                    'X-Request-Id',
                    $requestId
                );
            }
            return $response;
        } catch (Throwable $e) {
            /*
             * Si la excepción escapó del controlador también debemos dejar constancia en api_consumos.
             */
            $this->registrarConsumo(
                $request,
                $servicio,
                $cliente,
                $token,
                500,
                false,
                $e->getMessage(),
                $requestId,
                $inicio
            );
            /*
             * Dejamos que el Handler global de Lumen procese finalmente la excepción.
             */
            throw $e;
        }
    }
    /**
     * RESOLVER SERVICIO
     */
    private function resolverServicio(Request $request)
    {
        $endpoint = $this->normalizarEndpoint($request->path());
        $metodo = strtoupper($request->method());
        /*
         * Primero busco coincidencia exacta.
         */
        $servicio = DB::table('api_servicios')->where('activo', true)
            ->whereRaw(
                'UPPER(metodo_http) = ?',
                [$metodo]
            )->where('endpoint', $endpoint)
            ->first();

        if ($servicio) {
            return $servicio;
        }
        /*
         * Si no fue exacta buscamos rutas que tengan parámetros.
         */
        $servicios = DB::table('api_servicios')->where('activo', true)
            ->whereRaw(
                'UPPER(metodo_http) = ?',
                [$metodo]
            )
            ->get();

        foreach ($servicios as $candidato) {
            $plantilla = $this->normalizarEndpoint($candidato->endpoint);
            if ($this->endpointCoincide($plantilla, $endpoint)) {
                return $candidato;
            }
        }
        return null;
    }

    /**
     * Convierte los endpoints
     */
    private function endpointCoincide(string $plantilla,string $endpointReal): bool
    {
        $marcador ='__PARAMETRO_RUTA__';
        /*
         * Primero reemplazamos {parametro} por un marcador seguro.
         */
        $plantillaMarcada = preg_replace('/\{[^\/{}]+\}/',$marcador,$plantilla);
        /*
         * Escapamos el resto de la URL.
         */
        $regex =preg_quote($plantillaMarcada,'#');
        /*
         * Sustituimos el marcador por un segmento URL.
         */
        $regex = str_replace(preg_quote($marcador, '#'),'[^/]+',$regex);
        return preg_match('#^' . $regex . '$#i',$endpointReal) === 1;
    }

    /**
     * Normaliza una ruta.  request->path() devuelve:
     * api/proxy/documentos/convertir-pdf
     * mientras en BD tenemos:
     * /api/proxy/documentos/convertir-pdf
     */
    private function normalizarEndpoint(string $endpoint): string
    {
        $endpoint = '/' . ltrim($endpoint, '/');
        if ($endpoint !== '/')
        {
            $endpoint = rtrim($endpoint, '/');
        }
        return $endpoint;
    }

    /**
     * REGISTRAR CONSUMO
     */
    private function registrarConsumo(Request $request, $servicio, $cliente, ?string $token, int $codigoRespuesta, bool $exito, ?string $mensajeError, string $requestId, float $inicio): void
    {
        try {
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000
            );
            $tokenAuditoria = null;
            if (!empty($token)) {
                $tokenAuditoria = 'sha256:' . hash('sha256', $token);
            }
            DB::table('api_consumos')->insert([
                'id_servicio' => $servicio->id,
                'id_cliente' => $cliente ? $cliente->id : null,
                'token_recibido' => $tokenAuditoria,
                'login_usuario_header' => $this->obtenerPrimerHeader($request, ['X-Login-Usuario', 'Login-Usuario', 'X-Usuario',]),
                'nombre_usuario_header' => $this->obtenerPrimerHeader($request, ['X-Nombre-Usuario', 'Nombre-Usuario',]),
                'documento_usuario_header' => $this->obtenerPrimerHeader($request, ['X-Documento-Usuario', 'Documento-Usuario',]),
                'fecha_acceso' => now(),
                'ip_acceso' => $request->ip(),
                'dispositivo_acceso' => $this->limitarTexto($request->userAgent(), 500),
                'metodo_http' => strtoupper($request->method()),
                'endpoint' => $this->limitarTexto($this->normalizarEndpoint($request->path()), 255),
                'codigo_respuesta' => $codigoRespuesta,
                'tiempo_respuesta_ms' => $tiempoMs,
                'exito' => $exito,
                'mensaje_error' => $this->limitarTexto($mensajeError, 5000),
                'request_id' => $this->limitarTexto($requestId, 100),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            /* Un error de auditoría NO debe impedir que el servicio principal responda.*/
            report($e);
        }
    }
    /**
     * Extrae un mensaje útil de una respuesta JSON de error.
     */
    private function obtenerMensajeError($response,int $codigo): string
    {
        try {
            $contenido =$response->getContent();
            $json = json_decode($contenido,true);
            if (is_array($json) && !empty($json['message']))
            {
                return (string) $json['message'];
            }

        } catch (Throwable $e) {
            // No hacemos nada.
        }
        return
            'La solicitud terminó con HTTP ' . $codigo . '.';
    }
    /**
     * Lee el primer header disponible.
     */
    private function obtenerPrimerHeader(Request $request,array $nombres): ?string
    {
        foreach ($nombres as $nombre) {
            $valor = $request->header($nombre);
            if ($valor !== null && trim((string) $valor) !== '')
            {
                return $this->limitarTexto(trim((string) $valor),500);
            }
        }
        return null;
    }
    /**
     * Limita cadenas según la longitud de las columnas de BD.
     */
    private function limitarTexto(?string $texto,int $longitud): ?string
    {
        if ($texto === null) {
            return null;
        }
        return mb_substr($texto,0,$longitud);
    }
    /**
     * -------------------------------------------------------------
     * OBTENENGO EL TOKEN
     * -------------------------------------------------------------
     *
     * 1. X-Api-Token
     * 2. Authorization: Token <token>
     * 3. Authorization: Bearer <token>
     */
    private function obtenerTokenDesdeRequest(Request $request): array
    {
        $token = null;
        $scheme = null;
        /*
         * X-Api-Token
         */
        $headerToken = $request->header('X-Api-Token') ?? $request->header('x-api-token') ?? ($_SERVER['HTTP_X_API_TOKEN'] ?? null);
        if (!empty($headerToken)) {
            $token = trim($headerToken);
            $scheme = 'X-API-TOKEN';
        }
        /*
         * Authorization
         */
        if (empty($token)) {
            $authorization = $request->header('Authorization') ?? $request->header('authorization') ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
            if (!empty($authorization)) {
                $authorization = trim($authorization);
                /*
                 * Authorization: Token <token>
                 */
                if (preg_match('/^Token\s+(.+)$/i',$authorization,$matches))
                {
                    $token =trim($matches[1]);
                    $scheme ='TOKEN';
                }
                /*
                 * Authorization: Bearer <token>
                 */
                elseif (preg_match('/^Bearer\s+(.+)$/i',$authorization,$matches))
                {
                    $token = trim($matches[1]);
                    $scheme ='BEARER';
                }
            }
        }
        return [$token,$scheme];
    }
}
