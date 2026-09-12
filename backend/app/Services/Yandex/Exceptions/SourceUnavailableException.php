<?php

namespace App\Services\Yandex\Exceptions;

/** The page could not be fetched (network error, timeout, 5xx). Retryable. */
class SourceUnavailableException extends ParserException
{
    public function reason(): string
    {
        return 'source_unavailable';
    }
}
