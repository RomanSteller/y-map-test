<?php

namespace App\Services\Yandex\Exceptions;

/** Вставленная ссылка не похожа на URL карточки организации в Яндекс.Картах. */
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
