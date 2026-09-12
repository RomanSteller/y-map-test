<?php

namespace App\Services\Yandex\Exceptions;

/** The pasted link is not a recognisable Yandex.Maps organisation URL. */
class InvalidUrlException extends ParserException
{
    public function reason(): string
    {
        return 'invalid_url';
    }

    public function isRetryable(): bool
    {
        return false;
    }
}
