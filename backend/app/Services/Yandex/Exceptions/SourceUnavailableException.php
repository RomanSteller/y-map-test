<?php

namespace App\Services\Yandex\Exceptions;

/** Страницу не удалось загрузить (сеть, таймаут, 5xx). Можно повторить. */
class SourceUnavailableException extends ParserException
{
    public function reason(): string
    {
        return 'source_unavailable';
    }
}
