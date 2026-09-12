<?php

namespace App\Services\Yandex\Exceptions;

/** Yandex served a captcha / anti-bot wall. Retryable, but only after backoff. */
class BlockedException extends ParserException
{
    public function reason(): string
    {
        return 'blocked';
    }
}
