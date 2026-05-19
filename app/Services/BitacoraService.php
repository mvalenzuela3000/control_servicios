<?php

namespace App\Services;

use App\Models\Consumo;

class BitacoraService
{
    public static function registrar(array $data)
    {
        return Consumo::create([
            'id_servicio' => $data['id_servicio'] ?? null,
            'id_cliente' => $data['id_cliente'] ?? null,
            'token_recibido' => $data['token_recibido'] ?? null,
            'login_usuario_header' => $data['login_usuario_header'] ?? null,
            'nombre_usuario_header' => $data['nombre_usuario_header'] ?? null,
            'fecha_acceso' => date('Y-m-d H:i:s'),
            'ip_acceso' => $data['ip_acceso'] ?? null,
            'dispositivo_acceso' => $data['dispositivo_acceso'] ?? null,
            'metodo_http' => $data['metodo_http'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'codigo_respuesta' => $data['codigo_respuesta'] ?? null,
            'tiempo_respuesta_ms' => $data['tiempo_respuesta_ms'] ?? null,
            'exito' => $data['exito'] ?? true,
            'mensaje_error' => $data['mensaje_error'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'documento_usuario_header' => $data['documento_usuario_header'] ?? null
        ]);
    }
}
