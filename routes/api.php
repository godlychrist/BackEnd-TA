<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\MessageController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Autenticación (Basado en la estructura de Cris)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/verify-email', [AuthController::class, 'verifyEmail']);
Route::get('/check-cedula/{cedula}', [AuthController::class, 'checkCedula']);

// Google OAuth (Flujo Manual por Requerimientos de Bladimir)
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Vehículos (ESTO ES EXACTAMENTE COMO LO TIENE CRIS)
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehicles/{id}', [VehicleController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    Route::post('/vehicles', [VehicleController::class, 'createVehicle']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'editVehicle']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'deleteVehicle']);
    
    // Conversaciones
    
    Route::post('/conversations', [MessageController::class, 'createConversation']);
    Route::get('/conversations', [MessageController::class, 'getConversations']);
    Route::get('/conversations/{id}', [MessageController::class, 'getConversation']);

    // Mensajes

    Route::post('/messages', [MessageController::class, 'store']);

});

