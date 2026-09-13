<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * Отзывы организации, по 50 на страницу (как в ТЗ).
     *
     * Отдаём из своей БД — результат парсинга там уже лежит, поэтому листание
     * страниц мгновенное и Яндекс на каждый клик не дёргаем.
     */
    public function index(Organization $organization): JsonResponse
    {
        $reviews = $organization->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($reviews);
    }
}
