<?php

namespace App\Http\Controllers;

use App\Models\Consumo;

class ConsumoController extends Controller
{
    public function index()
    {
        return response()->json(
            Consumo::orderBy('fecha_acceso', 'desc')->limit(200)->get()
        );
    }
}
