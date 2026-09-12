<?php

namespace App\Services\Yandex\Contracts;

use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\YandexUrl;

/**
 * The single seam between "our application" and "how we get data out of
 * Yandex". Controllers and jobs depend only on this interface, so the scraping
 * strategy (headless browser, internal JSON, fixture) can be swapped without
 * touching anything else. This is the "logic lives in a service, not the
 * controller" requirement from the spec.
 */
interface ReviewParser
{
    /**
     * @param  callable(int $progress, string $message): void|null  $onProgress
     *         Optional callback invoked as reviews are pulled, so a queued job
     *         can surface progress (0..100) to the UI.
     *
     * @throws \App\Services\Yandex\Exceptions\ParserException
     */
    public function parse(YandexUrl $url, ?callable $onProgress = null): OrganizationData;
}
