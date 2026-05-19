<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        return response()->json(Cliente::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'codigo_cliente' => 'required|max:100|unique:api_clientes,codigo_cliente',
            'nombre_cliente' => 'required|max:200',
            'token' => 'required|max:255|unique:api_clientes,token',
            'descripcion' => 'nullable',
            'activo' => 'required|boolean'
        ]);

        $cliente = Cliente::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Cliente registrado correctamente',
            'data' => $cliente
        ], 201);
    }
}
