<?php

namespace App\Providers;

use App\Services\Yandex\Contracts\ReviewParser;
use App\Services\Yandex\FixtureParser;
use App\Services\Yandex\HeadlessBrowserParser;
use App\Services\Yandex\ResponseValidator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class YandexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResponseValidator::class);

        // Привязываем интерфейс ReviewParser к конкретному драйверу из конфига.
        // Всё остальное завязано на интерфейс, поэтому переключение с фикстуры
        // на боевой headless-скрапер — это правка одной переменной окружения.
        $this->app->bind(ReviewParser::class, function ($app) {
            $config = $app['config']->get('services.yandex');

            return match ($config['driver']) {
                'headless' => new HeadlessBrowserParser(
                    $app->make(ResponseValidator::class),
                    $config['scraper_url'],
                    $config['scraper_timeout'],
                    $config['max_reviews'],
                ),
                'fixture' => new FixtureParser(
                    $app->make(ResponseValidator::class),
                    $config['fixture_path'],
                ),
                default => throw new InvalidArgumentException(
                    "Unknown Yandex parser driver [{$config['driver']}]."
                ),
            };
        });
    }
}
