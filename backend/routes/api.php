<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Авторизация по кукам (Sanctum SPA): фронт берёт /sanctum/csrf-cookie,
// затем ходит сюда под сессионной кукой.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::get('/organizations', [OrganizationController::class, 'index']);
    Route::post('/organizations', [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::post('/organizations/{organization}/parse', [OrganizationController::class, 'parse']);
    Route::get('/organizations/{organization}/reviews', [ReviewController::class, 'index']);
});
