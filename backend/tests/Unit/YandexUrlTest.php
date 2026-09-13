<?php

namespace Tests\Unit;

use App\Services\Yandex\YandexUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class YandexUrlTest extends TestCase
{
    public function test_extracts_id_and_slug_and_strips_query(): void
    {
        $url = YandexUrl::parse('https://yandex.ru/maps/org/twins_garden/192990200894/reviews/?foo=bar');

        $this->assertSame('192990200894', $url->orgId);
        $this->assertSame('twins_garden', $url->slug);
        $this->assertSame('https://yandex.ru/maps/org/twins_garden/192990200894/reviews', $url->normalized);
    }

    public function test_extracts_id_without_slug(): void
    {
        $url = YandexUrl::parse('https://yandex.com/maps/213/moscow/org/192990200894/');
        $this->assertSame('192990200894', $url->orgId);
    }

    public function test_rejects_non_yandex_host(): void
    {
        $this->expectException(InvalidArgumentException::class);
        YandexUrl::parse('https://maps.google.com/maps/org/x/192990200894/');
    }

    public function test_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        YandexUrl::parse('not a url');
    }
}
