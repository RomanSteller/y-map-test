<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Страница загрузилась, но нужной нам структуры (встроенного state-JSON /
 * ожидаемых полей) там не оказалось. Почти всегда значит, что Яндекс поменял
 * вёрстку — парсер должен править человек, так что повторять смысла нет.
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
