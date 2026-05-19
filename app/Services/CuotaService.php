<?php

namespace App\Services;

use App\Models\CuotaDiaria;
use App\Models\Consumo;

class CuotaService
{
    public static function obtenerCuotaVigente($idServicio, $idCliente)
    {
        $fechaHoy = date('Y-m-d');

        $cuotaCliente = CuotaDiaria::where('id_servicio', $idServicio)
            ->where('id_cliente', $idCliente)
            ->where('activo', true)
            ->where(function ($q) use ($fechaHoy) {
                $q->whereNull('fecha_inicio')
                    ->orWhere('fecha_inicio', '<=', $fechaHoy);
            })
            ->where(function ($q) use ($fechaHoy) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fechaHoy);
            })
            ->first();

        if ($cuotaCliente) {
            return $cuotaCliente->cuota_diaria;
        }

        $cuotaGeneral = CuotaDiaria::where('id_servicio', $idServicio)
            ->whereNull('id_cliente')
            ->where('activo', true)
            ->where(function ($q) use ($fechaHoy) {
                $q->whereNull('fecha_inicio')
                    ->orWhere('fecha_inicio', '<=', $fechaHoy);
            })
            ->where(function ($q) use ($fechaHoy) {
                $q->whereNull('fecha_fin')
                    ->orWhere('fecha_fin', '>=', $fechaHoy);
            })
            ->first();

        return $cuotaGeneral ? $cuotaGeneral->cuota_diaria : null;
    }

    public static function puedeConsumir($idServicio, $idCliente)
    {
        $cuota = self::obtenerCuotaVigente($idServicio, $idCliente);

        if (!$cuota) {
            return true;
        }

        $cantidadHoy = Consumo::where('id_servicio', $idServicio)
            ->where('id_cliente', $idCliente)
            ->whereDate('fecha_acceso', date('Y-m-d'))
            ->count();

        return $cantidadHoy < $cuota;
    }
}
