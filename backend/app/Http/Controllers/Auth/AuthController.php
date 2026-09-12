<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Логин по кукам (Sanctum SPA). Фронт сначала дёргает /sanctum/csrf-cookie,
     * потом постит сюда; при успехе ставится сессионная кука.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный логин или пароль.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Вы вышли из системы.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
