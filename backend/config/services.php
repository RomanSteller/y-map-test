<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Парсер отзывов Яндекс.Карт
    |--------------------------------------------------------------------------
    |
    | driver:        какую реализацию парсера использовать.
    |                  - "headless" : гоняем Node/Playwright-сервис-скрапер
    |                                 (боевой режим, устойчив к подписи запросов).
    |                  - "fixture"  : проигрываем сохранённый JSON-снимок реальной
    |                                 организации. Позволяет запустить и проверить
    |                                 всё приложение без живого доступа к Яндексу.
    | scraper_url:   адрес Node-сервиса-скрапера.
    | scraper_timeout: таймаут (сек) на запрос к скраперу. Вытянуть ~600 отзывов
    |                  с вежливыми паузами — дело небыстрое.
    | page_size:     отзывов на страницу в интерфейсе (по ТЗ — 50).
    | max_reviews:   предохранитель: сколько максимум отзывов тянем с одной орг.
    | fixture_path:  файл-фикстура для драйвера "fixture".
    |
    */
    'yandex' => [
        'driver' => env('YANDEX_PARSER_DRIVER', 'fixture'),
        'scraper_url' => env('YANDEX_SCRAPER_URL', 'http://scraper:3000'),
        'scraper_timeout' => (int) env('YANDEX_SCRAPER_TIMEOUT', 600),
        'page_size' => 50,
        'max_reviews' => (int) env('YANDEX_MAX_REVIEWS', 600),
        'fixture_path' => env('YANDEX_FIXTURE_PATH', storage_path('app/fixtures/twins_garden.json')),
    ],

];
