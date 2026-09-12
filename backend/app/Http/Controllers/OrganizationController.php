<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\SnapshotResource;
use App\Jobs\ParseOrganizationReviews;
use App\Models\Organization;
use App\Services\Yandex\YandexUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    /** Список подключённых организаций, новые сверху. */
    public function index(): AnonymousResourceCollection
    {
        $organizations = Organization::with('latestSnapshot')
            ->latest()
            ->get();

        return OrganizationResource::collection($organizations);
    }

    /**
     * Сохраняет ссылку с экрана настроек и запускает парсинг.
     *
     * Идемпотентно по задумке: если вставить ту же организацию ещё раз, мы
     * обновим существующую запись (и перепарсим), а не создадим дубль.
     */
    public function store(SaveOrganizationRequest $request): JsonResponse
    {
        $parsed = YandexUrl::parse($request->validated('url'));

        // У коротких ссылок id ещё нет — такие строки ключуем по нормализованному URL.
        $attributes = $parsed->orgId !== null
            ? ['yandex_id' => $parsed->orgId]
            : ['yandex_id' => 'url:'.sha1($parsed->normalized)];

        $organization = Organization::updateOrCreate($attributes, [
            'url' => $parsed->normalized,
            'slug' => $parsed->slug,
            'parse_status' => Organization::STATUS_QUEUED,
            'parse_progress' => 0,
            'parse_error' => null,
            'parse_error_reason' => null,
        ]);

        ParseOrganizationReviews::dispatch($organization->id);

        return (new OrganizationResource($organization->load('latestSnapshot')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Organization $organization): OrganizationResource
    {
        return new OrganizationResource($organization->load('latestSnapshot'));
    }

    /** Перепарсить уже подключённую организацию. */
    public function parse(Organization $organization): JsonResponse
    {
        if ($organization->isBusy()) {
            return response()->json([
                'message' => 'Парсинг уже выполняется.',
            ], 409);
        }

        $organization->update([
            'parse_status' => Organization::STATUS_QUEUED,
            'parse_progress' => 0,
            'parse_error' => null,
            'parse_error_reason' => null,
        ]);

        ParseOrganizationReviews::dispatch($organization->id);

        return (new OrganizationResource($organization->load('latestSnapshot')))
            ->response();
    }

    /** Лёгкий эндпоинт, который интерфейс опрашивает, пока идёт парсинг. */
    public function status(Organization $organization): JsonResponse
    {
        return response()->json([
            'status' => $organization->parse_status,
            'progress' => $organization->parse_progress,
            'is_busy' => $organization->isBusy(),
            'error_reason' => $organization->parse_error_reason,
            'error' => $organization->parse_error,
            'rating' => $organization->rating,
            'ratings_count' => $organization->ratings_count,
            'reviews_count' => $organization->reviews_count,
            'last_parsed_at' => $organization->last_parsed_at?->toIso8601String(),
        ]);
    }

    /** Агрегатная история — «было → стало» между парсингами. */
    public function snapshots(Organization $organization): AnonymousResourceCollection
    {
        return SnapshotResource::collection(
            $organization->snapshots()->latest()->limit(50)->get()
        );
    }
}
