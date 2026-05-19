<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consumo extends Model
{
    protected $table = 'api_consumos';

    public $timestamps = false;

    protected $fillable = [
        'id_servicio',
        'id_cliente',
        'token_recibido',
        'login_usuario_header',
        'nombre_usuario_header',
        'fecha_acceso',
        'ip_acceso',
        'dispositivo_acceso',
        'metodo_http',
        'endpoint',
        'codigo_respuesta',
        'tiempo_respuesta_ms',
        'exito',
        'mensaje_error',
        'request_id',
        'created_at',
        'documento_usuario_header',
    ];
}
