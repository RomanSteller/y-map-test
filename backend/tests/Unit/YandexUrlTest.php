<?php

namespace Tests\Unit;

use App\Services\Yandex\Exceptions\InvalidUrlException;
use App\Services\Yandex\YandexUrl;
use PHPUnit\Framework\TestCase;

class YandexUrlTest extends TestCase
{
    public function test_extracts_id_and_slug_from_full_org_url(): void
    {
        $url = YandexUrl::parse('https://yandex.ru/maps/org/twins_garden/192990200894/reviews/?foo=bar');

        $this->assertSame('192990200894', $url->orgId);
        $this->assertSame('twins_garden', $url->slug);
        // Tracking query string is stripped.
        $this->assertSame('https://yandex.ru/maps/org/twins_garden/192990200894/reviews', $url->normalized);
    }

    public function test_extracts_id_from_org_url_without_slug(): void
    {
        $url = YandexUrl::parse('https://yandex.com/maps/213/moscow/org/192990200894/');
        $this->assertSame('192990200894', $url->orgId);
    }

    public function test_accepts_short_link_without_id(): void
    {
        $url = YandexUrl::parse('https://yandex.ru/maps/-/CDf1abcd');
        $this->assertNull($url->orgId);
    }

    public function test_rejects_non_yandex_host(): void
    {
        $this->expectException(InvalidUrlException::class);
        YandexUrl::parse('https://maps.google.com/maps/org/x/192990200894/');
    }

    public function test_rejects_yandex_url_without_org(): void
    {
        $this->expectException(InvalidUrlException::class);
        YandexUrl::parse('https://yandex.ru/maps/213/moscow/');
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(InvalidUrlException::class);
        YandexUrl::parse('not a url');
    }
}
