<?php

namespace App\Services\Yandex\Exceptions;

/**
 * The page loaded but the structure we rely on (the embedded state JSON /
 * expected fields) was not there. Almost always means Yandex changed their
 * markup — a human needs to update the parser, so retrying is pointless.
 */
class MarkupChangedException extends ParserException
{
    public function reason(): string
    {
        return 'markup_changed';
    }

    public function isRetryable(): bool
    {
        return false;
    }
}
