<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
| Все маршруты API. Авторизация по кукам (Sanctum SPA): фронт сначала берёт
| /sanctum/csrf-cookie, а потом ходит сюда под сессионной кукой.
*/

// Публичные.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

// Только для авторизованных.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::post('/organizations/{organization}/parse', [OrganizationController::class, 'parse']);
    Route::get('/organizations/{organization}/status', [OrganizationController::class, 'status']);
    Route::get('/organizations/{organization}/snapshots', [OrganizationController::class, 'snapshots']);
    Route::get('/organizations/{organization}/reviews', [ReviewController::class, 'index']);
});
