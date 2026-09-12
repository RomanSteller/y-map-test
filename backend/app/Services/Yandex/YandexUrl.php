<?php

namespace App\Services\Yandex;

use App\Services\Yandex\Exceptions\InvalidUrlException;

/**
 * Parses and validates a Yandex.Maps organisation link.
 *
 * Accepts the common shapes, e.g.:
 *   https://yandex.ru/maps/org/twins_garden/192990200894/
 *   https://yandex.ru/maps/org/192990200894/reviews/
 *   https://yandex.com/maps/213/moscow/org/.../192990200894/reviews/
 *   https://yandex.ru/maps/-/CDf1
 */
final class YandexUrl
{
    private const ALLOWED_HOSTS = [
        'yandex.ru', 'yandex.com', 'yandex.by', 'yandex.kz', 'yandex.eu',
        'maps.yandex.ru', 'maps.yandex.com',
    ];

    public function __construct(
        public readonly string $normalized,
        public readonly ?string $orgId,
        public readonly ?string $slug,
    ) {
    }

    public static function parse(string $raw): self
    {
        $raw = trim($raw);
        $parts = parse_url($raw);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            throw new InvalidUrlException('Ссылка не похожа на URL.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidUrlException('Поддерживаются только http/https ссылки.');
        }

        $host = strtolower($parts['host']);
        if (! self::hostAllowed($host)) {
            throw new InvalidUrlException('Это не ссылка на Яндекс.Карты.');
        }

        $path = $parts['path'];
        if (! str_contains($path, '/maps')) {
            throw new InvalidUrlException('В ссылке нет раздела /maps.');
        }

        [$orgId, $slug] = self::extractOrgId($path);

        // A short link (/maps/-/xxxx) is valid but the id is only known after
        // following the redirect — leave the id null, the scraper resolves it.
        $isShort = (bool) preg_match('#/maps/-/[\w-]+#', $path);
        if ($orgId === null && ! $isShort) {
            throw new InvalidUrlException('Не удалось определить организацию в ссылке. Нужна ссылка на карточку организации (…/org/…).');
        }

        // Rebuild a clean canonical URL without tracking query params.
        $normalized = rtrim(sprintf('%s://%s%s', $parts['scheme'], $host, $path), '/');

        return new self($normalized, $orgId, $slug);
    }

    private static function hostAllowed(string $host): bool
    {
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: ?string, 1: ?string} [orgId, slug] */
    private static function extractOrgId(string $path): array
    {
        // .../org/<slug>/<digits>  or  .../org/<digits>
        if (preg_match('#/org/([^/]+)/(\d{6,})#', $path, $m)) {
            return [$m[2], $m[1]];
        }
        if (preg_match('#/org/(\d{6,})#', $path, $m)) {
            return [$m[1], null];
        }
        // Fallback: a long numeric id anywhere in the path.
        if (preg_match('#/(\d{9,})(?:/|$)#', $path, $m)) {
            return [$m[1], null];
        }

        return [null, null];
    }
}
