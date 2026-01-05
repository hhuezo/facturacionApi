<?php

use App\Http\Controllers\catalogo\ClienteController;
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

Route::get('cliente', [ClienteController::class, 'index']);
Route::get('cliente/create', [ClienteController::class, 'create']);
Route::post('cliente', [ClienteController::class, 'store']);
Route::get('cliente/{id}/edit', [ClienteController::class, 'edit']);
Route::put('cliente/{id}', [ClienteController::class, 'update']);

Route::get('factura', [FacturaController::class, 'index']);
Route::get('factura/create', [FacturaController::class, 'create']);
Route::post('factura', [FacturaController::class, 'store']);
