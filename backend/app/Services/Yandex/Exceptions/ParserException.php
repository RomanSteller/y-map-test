<?php

namespace App\Services\Yandex\Exceptions;

use RuntimeException;

/**
 * Базовый класс для всех способов, которыми парсер может сломаться. У каждого
 * наследника есть короткая машинная причина `reason`, чтобы API, логика
 * ретраев в очереди и интерфейс могли реагировать по-разному (сетевой сбой
 * стоит повторить, а изменившуюся вёрстку — нет).
 */
abstract class ParserException extends RuntimeException
{
    abstract public function reason(): string;

    /** Есть ли смысл перезапускать джобу позже — вдруг получится. */
    public function isRetryable(): bool
    {
        return true;
    }
}
