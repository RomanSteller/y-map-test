<?php

namespace App\Services\Yandex;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use InvalidArgumentException;

/**
 * Разбор и проверка ссылки на организацию в Яндекс.Картах.
 * Парсинг URL отдан классу Illuminate\Support\Uri (поверх league/uri).
 *
 * Понимает основные варианты:
 *   https://yandex.ru/maps/org/twins_garden/192990200894/
 *   https://yandex.ru/maps/org/192990200894/reviews/
 *   https://yandex.com/maps/213/moscow/org/.../192990200894/
 */
final class YandexUrl
{
    private const HOSTS = ['yandex.ru', 'yandex.com', 'yandex.by', 'yandex.kz'];

    public function __construct(
        public readonly string $normalized,
        public readonly ?string $orgId,
        public readonly ?string $slug,
    ) {
    }

    public static function parse(string $raw): self
    {
        try {
            $uri = Uri::of(trim($raw));
        } catch (\Throwable) {
            throw new InvalidArgumentException('Ссылка не похожа на URL.');
        }

        $host = Str::lower((string) $uri->host());
        $segments = $uri->pathSegments();

        $hostOk = collect(self::HOSTS)->contains(fn ($h) => $host === $h || Str::endsWith($host, ".$h"));
        if (! $hostOk || ! $segments->contains('maps')) {
            throw new InvalidArgumentException('Это не ссылка на Яндекс.Карты.');
        }

        [$id, $slug] = self::orgFrom($segments);

        // Короткие ссылки (/maps/-/xxxx) валидны — id узнаем уже после редиректа.
        if ($id === null && ! $segments->contains('-')) {
            throw new InvalidArgumentException('Не удалось найти организацию в ссылке (нужна карточка …/org/…).');
        }

        $normalized = rtrim("{$uri->scheme()}://{$host}/{$uri->path()}", '/');

        return new self($normalized, $id, $slug);
    }

    /** @return array{0: ?string, 1: ?string} [id, slug] из сегментов …/org/<slug?>/<id> */
    private static function orgFrom(Collection $segments): array
    {
        $i = $segments->search('org');
        if ($i === false) {
            return [null, null];
        }

        $rest = $segments->slice($i + 1)->values();
        $id = $rest->first(fn ($s) => Str::isMatch('/^\d{6,}$/', $s));
        $slug = ($rest->first() !== $id) ? $rest->first() : null;

        return [$id, $slug];
    }
}
