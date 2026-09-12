<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\Exceptions\EmptyResultException;
use App\Services\Yandex\Exceptions\MarkupChangedException;

/**
 * The parser's "am I still working?" self-check.
 *
 * Scrapers fail silently: Yandex changes a key, our selector returns null, and
 * we happily persist an empty/garbage result. This validator turns those quiet
 * failures into loud, typed exceptions by asserting the invariants a healthy
 * response must satisfy. See README → "How the parser knows it broke".
 */
final class ResponseValidator
{
    public function validate(array $raw): OrganizationData
    {
        $org = $raw['organization'] ?? null;

        if (! is_array($org)) {
            throw new MarkupChangedException('В ответе нет данных организации — вероятно, изменилась структура страницы Яндекса.');
        }

        // A valid card must expose an id and at least one recognisable counter.
        $id = $org['yandex_id'] ?? $org['id'] ?? null;
        if (empty($id)) {
            throw new MarkupChangedException('Не найден идентификатор организации в ответе парсера.');
        }

        $hasRating = array_key_exists('rating', $org);
        $hasCounts = array_key_exists('ratings_count', $org) || array_key_exists('reviews_count', $org);
        if (! $hasRating && ! $hasCounts) {
            throw new MarkupChangedException('Не найдены ни рейтинг, ни счётчики отзывов — разметка карточки изменилась.');
        }

        $data = OrganizationData::fromArray($raw);

        // Sanity range: a 5-star scale.
        if ($data->rating !== null && ($data->rating < 0 || $data->rating > 5)) {
            throw new MarkupChangedException("Рейтинг вне допустимого диапазона ({$data->rating}) — данные распарсились неверно.");
        }

        // If Yandex reports reviews but we extracted none, lazy-loading or the
        // reviews endpoint is broken — do not store a misleading "0 reviews".
        if ($data->reviewsCount > 0 && count($data->reviews) === 0) {
            throw new EmptyResultException("Яндекс сообщает о {$data->reviewsCount} отзывах, но не удалось получить ни одного.");
        }

        return $data;
    }
}
