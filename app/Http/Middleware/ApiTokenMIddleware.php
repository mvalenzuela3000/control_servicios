<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        [$token, $authScheme] = $this->obtenerTokenDesdeRequest($request);

        if (empty($token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se envió un token válido en la cabecera X-Api-Token o Authorization',
                'accepted_formats' => [
                    'X-Api-Token: <token>',
                    'Authorization: Token <token>',
                    'Authorization: Bearer <token>'
                ]
            ], 401);
        }

        $cliente = Cliente::where('token', $token)
            ->where('activo', true)
            ->first();

        if (!$cliente) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido o cliente inactivo'
            ], 401);
        }

        $request->attributes->set('cliente_api', $cliente);
        $request->attributes->set('token_api', $token);
        $request->attributes->set('auth_scheme', $authScheme);

        return $next($request);
    }

    /**
     * Obtiene el token desde:
     * 1. X-Api-Token
     * 2. Authorization: Token <token>
     * 3. Authorization: Bearer <token>
     *
     * Retorna: [token, esquema]
     */
    private function obtenerTokenDesdeRequest(Request $request): array
    {
        $token = null;
        $scheme = null;

        // 1. Header personalizado X-Api-Token
        $headerToken = $request->header('X-Api-Token')
            ?? $request->header('x-api-token')
            ?? ($_SERVER['HTTP_X_API_TOKEN'] ?? null);

        if (!empty($headerToken)) {
            $token = trim($headerToken);
            $scheme = 'X-API-TOKEN';
        }

        // 2. Header Authorization
        if (empty($token)) {
            $authorization = $request->header('Authorization')
                ?? $request->header('authorization')
                ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

            if (!empty($authorization)) {
                $authorization = trim($authorization);

                // Authorization: Token <token>
                if (preg_match('/^Token\s+(.+)$/i', $authorization, $matches)) {
                    $token = trim($matches[1]);
                    $scheme = 'TOKEN';
                }
                // Authorization: Bearer <token>
                elseif (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
                    $token = trim($matches[1]);
                    $scheme = 'BEARER';
                }
            }
        }

        return [$token, $scheme];
    }
}
