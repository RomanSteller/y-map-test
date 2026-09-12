<?php

namespace App\Services\Yandex;

use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\Exceptions\EmptyResultException;
use App\Services\Yandex\Exceptions\MarkupChangedException;

/**
 * Самопроверка парсера в духе «а я вообще ещё живой?».
 *
 * Скраперы ломаются молча: Яндекс переименовал ключ, наш селектор вернул null,
 * а мы радостно сохранили пустой/мусорный результат. Этот валидатор превращает
 * такие тихие сбои в громкие типизированные исключения, проверяя инварианты,
 * которым обязан удовлетворять здоровый ответ. См. README → «Как парсер
 * понимает, что сломался».
 */
final class ResponseValidator
{
    public function validate(array $raw): OrganizationData
    {
        $org = $raw['organization'] ?? null;

        if (! is_array($org)) {
            throw new MarkupChangedException('В ответе нет данных организации — вероятно, изменилась структура страницы Яндекса.');
        }

        // У нормальной карточки должны быть id и хотя бы один вменяемый счётчик.
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

        // Проверка на здравый смысл: шкала-то пятибалльная.
        if ($data->rating !== null && ($data->rating < 0 || $data->rating > 5)) {
            throw new MarkupChangedException("Рейтинг вне допустимого диапазона ({$data->rating}) — данные распарсились неверно.");
        }

        // Если Яндекс говорит, что отзывы есть, а мы не достали ни одного —
        // сломалась подгрузка или эндпоинт отзывов. Не сохраняем обманчивый «0».
        if ($data->reviewsCount > 0 && count($data->reviews) === 0) {
            throw new EmptyResultException("Яндекс сообщает о {$data->reviewsCount} отзывах, но не удалось получить ни одного.");
        }

        return $data;
    }
}
