<?php

namespace App\Services\Yandex;

use InvalidArgumentException;

/**
 * Разбор и проверка ссылки на организацию в Яндекс.Картах.
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
        $p = parse_url(trim($raw));

        if (! is_array($p) || empty($p['host']) || empty($p['path'])) {
            throw new InvalidArgumentException('Ссылка не похожа на URL.');
        }

        $host = strtolower($p['host']);
        $ok = false;
        foreach (self::HOSTS as $h) {
            if ($host === $h || str_ends_with($host, '.'.$h)) {
                $ok = true;
                break;
            }
        }
        if (! $ok || ! str_contains($p['path'], '/maps')) {
            throw new InvalidArgumentException('Это не ссылка на Яндекс.Карты.');
        }

        [$id, $slug] = self::extractId($p['path']);

        // Короткие ссылки (/maps/-/xxxx) пропускаем — id узнаем уже после редиректа.
        $isShort = (bool) preg_match('#/maps/-/[\w-]+#', $p['path']);
        if ($id === null && ! $isShort) {
            throw new InvalidArgumentException('Не удалось найти организацию в ссылке (нужна карточка …/org/…).');
        }

        $scheme = $p['scheme'] ?? 'https';
        $normalized = rtrim("{$scheme}://{$host}{$p['path']}", '/');

        return new self($normalized, $id, $slug);
    }

    /** @return array{0: ?string, 1: ?string} [id, slug] */
    private static function extractId(string $path): array
    {
        if (preg_match('#/org/([^/]+)/(\d{6,})#', $path, $m)) {
            return [$m[2], $m[1]];
        }
        if (preg_match('#/org/(\d{6,})#', $path, $m)) {
            return [$m[1], null];
        }

        return [null, null];
    }
}
