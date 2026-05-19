<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaDiaria extends Model
{
    protected $table = 'api_cuotas_diarias';

    protected $fillable = [
        'id_servicio',
        'id_cliente',
        'cuota_diaria',
        'fecha_inicio',
        'fecha_fin',
        'activo'
    ];
}
