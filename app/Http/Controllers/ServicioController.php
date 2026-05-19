<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function index()
    {
        return response()->json(Servicio::orderBy('id')->get());
    }

    public function show($id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'status' => 'error',
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json($servicio);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'nombre' => 'required|max:150|unique:api_servicios,nombre',
            'descripcion' => 'nullable',
            'endpoint' => 'required|max:255',
            'metodo_http' => 'required|max:10',
            'requiere_token' => 'required|boolean',
            'controla_cuota' => 'required|boolean',
            'activo' => 'required|boolean'
        ]);

        $servicio = Servicio::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Servicio registrado correctamente',
            'data' => $servicio
        ], 201);
    }
}
