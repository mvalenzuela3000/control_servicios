<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'api_clientes';

    protected $fillable = [
        'codigo_cliente',
        'nombre_cliente',
        'token',
        'descripcion',
        'activo'
    ];
}
