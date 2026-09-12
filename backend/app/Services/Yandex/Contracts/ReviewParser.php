<?php

namespace App\Services\Yandex\Contracts;

use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\YandexUrl;

/**
 * Единственный стык между «нашим приложением» и «тем, как мы достаём данные
 * из Яндекса». Контроллеры и джобы завязаны только на этот интерфейс, поэтому
 * стратегию скрапинга (headless-браузер, внутренний JSON, фикстура) можно
 * менять, ничего вокруг не трогая. Это как раз требование ТЗ — «логика в
 * сервисе, а не в контроллере».
 */
interface ReviewParser
{
    /**
     * @param  callable(int $progress, string $message): void|null  $onProgress
     *         Необязательный колбэк: дёргается по мере вытягивания отзывов,
     *         чтобы фоновая джоба могла отдавать прогресс (0..100) в интерфейс.
     *
     * @throws \App\Services\Yandex\Exceptions\ParserException
     */
    public function parse(YandexUrl $url, ?callable $onProgress = null): OrganizationData;
}
