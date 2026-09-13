<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Заглушка под именем «login»: это чистое API, отдельной страницы входа нет.
// Нужна, чтобы неавторизованный запрос без заголовка Accept: application/json
// не падал с RouteNotFoundException при попытке Laravel редиректнуть на login,
// а получал понятный 401.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))
    ->name('login');
