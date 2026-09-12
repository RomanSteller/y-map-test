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

        // Bind the ReviewParser interface to the concrete driver chosen in
        // config. Everything else depends on the interface, so switching from
        // the fixture to the real headless scraper is a one-line env change.
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
