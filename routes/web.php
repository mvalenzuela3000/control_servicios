<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () {
    return response()->json([
        'aplicacion' => 'control_servicios',
        'estado' => 'ok'
    ]);
});
//rutas para registrar servicios
$router->group(['prefix' => 'api'], function () use ($router) {

    $router->get('servicios', 'ServicioController@index');
    $router->get('servicios/{id}', 'ServicioController@show');
    $router->post('servicios', 'ServicioController@store');

    $router->get('clientes', 'ClienteController@index');
    $router->post('clientes', 'ClienteController@store');

    $router->get('consumos', 'ConsumoController@index');
});
//rutaas para consumir servicios
$router->group(['prefix' => 'api', 'middleware' => 'api.token'], function () use ($router) {
    $router->get('proxy/sin/verifica-comunicacion', 'ProxyController@verificaComunicacionSIN');
    $router->post('proxy/sin/actos-impugnados', 'ProxyController@actosImpugnadosSIN');
    $router->get('proxy/seprec/verifica-comunicacion', 'ProxyController@verificaComunicacionSEPREC');
    $router->get('proxy/seprec/matriculas-por-nit/{nit}', 'ProxyController@matriculasPorNitSEPREC');
    $router->get('proxy/seprec/matricula/{matricula}', 'ProxyController@matriculaPorNumeroSEPREC');
    $router->get('proxy/seprec/matricula/representantes/{matricula}', 'ProxyController@representantesPorMatriculaSEPREC');
    $router->get('proxy/jukumari/status', 'ProxyController@statusJukumari');
    $router->post('proxy/jukumari/impuestos/alzada', 'ProxyController@impuestosAlzadaJukumari');
    $router->post('proxy/jukumari/impuestos/jerarquico', 'ProxyController@impuestosJerarquicoJukumari');
    $router->post('proxy/jukumari/aduana/alzada', 'ProxyController@aduanaAlzadaJukumari');
    $router->post('proxy/jukumari/aduana/jerarquico', 'ProxyController@aduanaJerarquicoJukumari');
});
$router->get('/test-headers', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'headers' => $request->headers->all(),
        'http_x_api_token' => $_SERVER['HTTP_X_API_TOKEN'] ?? null,
        'http_authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    ]);
});
