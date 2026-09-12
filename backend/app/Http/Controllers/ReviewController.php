<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    /**
     * Отзывы одной организации с пагинацией — по 50 на страницу, как в ТЗ.
     *
     * Отзывы отдаём из своей БД (результат парсинга там закэширован), а не
     * скрапим заново на каждый переход по странице. Поэтому листание — это
     * дешёвый запрос по индексу, и фронт переключает страницы мгновенно, не
     * долбя Яндекс. Почему выбран подход «сначала закэшировать, потом листать» —
     * расписано в README.
     */
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        $perPage = (int) config('services.yandex.page_size', 50);

        $reviews = $organization->reviews()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReviewResource::collection($reviews);
    }
}
