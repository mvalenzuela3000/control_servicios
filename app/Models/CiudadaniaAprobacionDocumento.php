<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiudadaniaAprobacionDocumento extends Model
{
    protected $table = 'ciudadania_aprobacion_documentos';

    protected $fillable = [
        'aprobacion_id',
        'uuid_documento',
        'tipo_documento',
        'hash_documento',
        'descripcion',
        'introducido',
        'codigo_operacion',
        'transaction_id',
        'mensaje',
    ];
}
