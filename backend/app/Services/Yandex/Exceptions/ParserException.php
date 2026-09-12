<?php

namespace App\Services\Yandex\Exceptions;

use RuntimeException;

/**
 * Base class for every failure mode of the parser. Each subclass carries a
 * short machine-readable `reason` so the API, the queue's retry logic and the
 * UI can react differently (a transient network blip is worth retrying, a
 * changed markup is not).
 */
abstract class ParserException extends RuntimeException
{
    abstract public function reason(): string;

    /** Whether re-running the job later has any chance of succeeding. */
    public function isRetryable(): bool
    {
        return true;
    }
}
