<?php

namespace App\Services\Yandex;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Парсер карточки организации Яндекс.Карт.
 *
 * Как это устроено: страница /reviews/ рендерится сервером, и всё состояние
 * приложения лежит прямо в HTML — в <script class="state-view"> обычным JSON.
 * Там уже есть рейтинг, оба счётчика (оценки/отзывы) и первые ~50 отзывов,
 * причём отдаётся это по простому GET без капчи. Этого хватает для MVP: сводку
 * и первую страницу отзывов достаём отсюда.
 *
 * ВАЖНО (что не доделано): дальше первых ~50 отзывов Яндекс подгружает их
 * подписанным XHR fetchReviews (параметр s=, ротация csrfToken). Повторять
 * подпись на бэке хрупко, поэтому полный сбор задуман через headless-браузер
 * (набросок в /scraper), но к бэку он ещё не подключён. Подробности и план —
 * в README.
 */
class YandexParser
{
    private string $userAgent;

    public function __construct()
    {
        $this->userAgent = (string) config('services.yandex.user_agent');
    }

    /**
     * @return array{title:?string,address:?string,rating:?float,ratings_count:int,reviews_count:int,reviews:array<int,array>}
     */
    public function parse(string $url): array
    {
        $html = Http::withHeaders(['User-Agent' => $this->userAgent, 'Accept-Language' => 'ru'])
            ->timeout(20)
            ->get($this->reviewsUrl($url))
            ->body();

        $state = self::extractState($html);

        // TODO: тут нужна нормальная диагностика «Яндекс сменил вёрстку»:
        // отличать капчу от реально изменившейся разметки и слать алерт, а не
        // просто одинаковую ошибку. Пока по-простому — см. README, «Что не успел».
        if ($state === null) {
            throw new RuntimeException('Не удалось прочитать данные страницы (капча или изменилась вёрстка Яндекса).');
        }

        return self::mapState($state);
    }

    /** Вытаскивает и декодирует JSON из встроенного <script class="state-view">. */
    public static function extractState(string $html): ?array
    {
        if (! preg_match('#<script[^>]*class="state-view"[^>]*>(.*?)</script>#s', $html, $m)) {
            return null;
        }

        $state = json_decode(html_entity_decode($m[1]), true);

        return is_array($state) ? $state : null;
    }

    /** Собирает из состояния страницы сводку и первую пачку отзывов. */
    public static function mapState(array $state): array
    {
        $org = self::find($state, fn ($n) => isset($n['ratingData']) && (isset($n['title']) || isset($n['name'])));

        if ($org === null) {
            throw new RuntimeException('В данных страницы нет карточки организации — вероятно, изменилась вёрстка.');
        }

        $rating = $org['ratingData'];
        $block = self::find($state, fn ($n) => isset($n['reviewResults']['reviews']) && is_array($n['reviewResults']['reviews']));

        return [
            'title' => $org['title'] ?? $org['name'] ?? null,
            'address' => $org['fullAddress'] ?? $org['address'] ?? null,
            'rating' => isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
            'ratings_count' => (int) ($rating['ratingCount'] ?? 0),
            'reviews_count' => (int) ($rating['reviewCount'] ?? 0),
            'reviews' => array_map(self::mapReview(...), $block['reviewResults']['reviews'] ?? []),
        ];
    }

    private static function mapReview(array $r): array
    {
        return [
            'external_id' => (string) ($r['reviewId'] ?? $r['id'] ?? ''),
            'author' => $r['author']['name'] ?? null,
            'rating' => isset($r['rating']) ? (int) $r['rating'] : null,
            'text' => isset($r['text']) ? trim((string) $r['text']) : null,
            'reviewed_at' => $r['updatedTime'] ?? $r['time'] ?? null,
        ];
    }

    /** Рекурсивно ищет в дереве состояния первый узел, подходящий под условие. */
    private static function find(array $node, callable $match): ?array
    {
        if ($match($node)) {
            return $node;
        }
        foreach ($node as $child) {
            if (is_array($child) && ($found = self::find($child, $match)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private function reviewsUrl(string $url): string
    {
        $url = rtrim(explode('?', $url)[0], '/');

        return str_ends_with($url, '/reviews') ? $url.'/' : $url.'/reviews/';
    }
}
