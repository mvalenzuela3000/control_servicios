<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiudadaniaAprobacion extends Model
{
    protected $table = 'ciudadania_aprobaciones';

    protected $fillable = [
        'id_tramite',
        'sistema',
        'modulo',
        'referencia_id',
        'tipo',
        'estado',
        'link_aprobacion',
        'codigo_operacion',
        'transaction_id',
        'uuid_blockchain',
        'ci',
        'aceptado',
        'introducido',
        'mensaje',
        'request_payload',
        'response_payload',
        'callback_payload',
    ];

    protected $casts = [
        'aceptado' => 'boolean',
        'introducido' => 'boolean',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'callback_payload' => 'array',
    ];

    public function documentos()
    {
        return $this->hasMany(CiudadaniaAprobacionDocumento::class, 'aprobacion_id');
    }
}
