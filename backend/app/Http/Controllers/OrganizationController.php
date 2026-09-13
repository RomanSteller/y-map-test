<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOrganizationRequest;
use App\Models\Organization;
use App\Services\Yandex\YandexParser;
use App\Services\Yandex\YandexUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrganizationController extends Controller
{
    public function __construct(private readonly YandexParser $parser)
    {
    }

    /** Список организаций, новые сверху. */
    public function index(): JsonResponse
    {
        return response()->json(Organization::latest()->get());
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json($organization);
    }

    /**
     * Сохраняет ссылку с экрана настроек и сразу собирает данные.
     *
     * Пока парсим синхронно прямо в запросе: один GET страницы — это быстро.
     * Для «сети из 50 филиалов» это надо унести в очередь (job на каждую орг),
     * задел есть, но не доделал — подробно расписал в README.
     */
    public function store(SaveOrganizationRequest $request): JsonResponse
    {
        $url = YandexUrl::parse($request->validated('url'));
        $key = $url->orgId ?? 'url:'.sha1($url->normalized);

        if (Organization::where('yandex_id', $key)->exists()) {
            throw ValidationException::withMessages([
                'url' => 'Эта организация уже добавлена — она в списке ниже.',
            ]);
        }

        $organization = Organization::create([
            'yandex_id' => $key,
            'url' => $url->normalized,
            'slug' => $url->slug,
        ]);

        $this->collect($organization);

        return response()->json($organization->fresh(), 201);
    }

    /** Перепарсить уже добавленную организацию. */
    public function parse(Organization $organization): JsonResponse
    {
        $this->collect($organization);

        return response()->json($organization->fresh());
    }

    /** Тянет сводку и первую страницу отзывов, кладёт в БД. */
    private function collect(Organization $organization): void
    {
        try {
            $data = $this->parser->parse($organization->url);

            $organization->update([
                'title' => $data['title'],
                'address' => $data['address'],
                'rating' => $data['rating'],
                'ratings_count' => $data['ratings_count'],
                'reviews_count' => $data['reviews_count'],
                'parse_error' => null,
                'last_parsed_at' => now(),
            ]);

            foreach ($data['reviews'] as $r) {
                if ($r['external_id'] === '') {
                    continue;
                }
                $organization->reviews()->updateOrCreate(
                    ['external_id' => $r['external_id']],
                    ['author' => $r['author'], 'rating' => $r['rating'], 'text' => $r['text'], 'reviewed_at' => $r['reviewed_at']],
                );
            }
        } catch (Throwable $e) {
            // Причины ошибок пока не типизирую (капча / вёрстка / сеть) — только
            // сохраняю текст, чтобы показать на фронте. TODO см. README.
            $organization->update(['parse_error' => $e->getMessage()]);
            report($e);
        }
    }
}
