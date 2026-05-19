<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    protected $table = 'api_servicios';

    protected $fillable = [
        'nombre',
        'descripcion',
        'endpoint',
        'url_destino',
        'metodo_http',
        'requiere_token',
        'token_destino',
        'controla_cuota',
        'activo'
    ];
}
