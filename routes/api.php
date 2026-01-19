<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\catalogo\ClienteController;
use App\Http\Controllers\catalogo\ProductoController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\FacturaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [AuthController::class, 'login']);

Route::get('home/{empresaId}', [AuthController::class, 'home']);

Route::get('user/{id}/empresa', [AuthController::class, 'getEmpresas']);
Route::get('empresa/{id}/sucursal', [AuthController::class, 'getSucursales']);


Route::get('cliente', [ClienteController::class, 'index']);
Route::get('cliente/create', [ClienteController::class, 'create']);
Route::post('cliente', [ClienteController::class, 'store']);
Route::get('cliente/{id}/edit', [ClienteController::class, 'edit']);
Route::put('cliente/{id}', [ClienteController::class, 'update']);


Route::get('producto', [ProductoController::class, 'index']);
Route::get('producto/create', [ProductoController::class, 'create']);
Route::post('producto', [ProductoController::class, 'store']);
Route::get('producto/{id}/edit', [ProductoController::class, 'edit']);
Route::put('producto/{id}', [ProductoController::class, 'update']);


Route::get('factura', [FacturaController::class, 'index']);
Route::get('factura/create', [FacturaController::class, 'create']);
Route::post('factura', [FacturaController::class, 'store']);
Route::get('factura/{id}/edit', [FacturaController::class, 'edit']);
Route::put('factura/{id}', [FacturaController::class, 'update']);
Route::post('factura/emitir/{id}', [FacturaController::class, 'emitir']);


Route::get('factura/reporte-pdf/{id}', [FacturaController::class, 'reportePdf']);
Route::get('facturas/{id}/ticket',[FacturaController::class, 'ticketJson']);

Route::post('facturacion/emitir/{facturaId}', [FacturacionController::class, 'generarFacturaElectronica']);
Route::get('facturacion/emitir/{id}', [FacturacionController::class, 'generarFacturaElectronica']);
