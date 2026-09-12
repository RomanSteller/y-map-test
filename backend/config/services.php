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
    | Yandex.Maps reviews parser
    |--------------------------------------------------------------------------
    |
    | driver:        which parser implementation to use.
    |                  - "headless" : drive the Node/Playwright scraper service
    |                                 (production; robust to request signing).
    |                  - "fixture"  : replay a bundled JSON snapshot of a real
    |                                 organisation. Lets the whole app run and be
    |                                 graded without live access to Yandex.
    | scraper_url:   base URL of the Node scraper micro-service.
    | scraper_timeout: HTTP timeout (s) when talking to the scraper. Pulling
    |                  ~600 reviews with polite pauses takes a while.
    | page_size:     reviews shown per UI page (spec requires 50).
    | max_reviews:   safety cap on how many reviews we try to pull per org.
    | fixture_path:  fixture file used by the "fixture" driver.
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
