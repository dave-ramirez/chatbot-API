<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;


// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });



Route::get('paquetes_cantidad', [ApiController::class, 'paquetes_cantidad']);
Route::get('paquetes', [ApiController::class, 'paquetes']);
Route::get('cliente', [ApiController::class, 'cliente']);
Route::get('sucursal', [ApiController::class, 'sucursal']);
