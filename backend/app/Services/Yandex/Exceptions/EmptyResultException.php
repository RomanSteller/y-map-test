<?php

namespace App\Services\Yandex\Exceptions;

/**
 * The card was found and looked valid, but zero reviews came back while the
 * counter says there should be some — a strong signal that lazy-loading or the
 * reviews endpoint broke rather than the org genuinely having no reviews.
 */
class EmptyResultException extends ParserException
{
    public function reason(): string
    {
        return 'empty_result';
    }
}
