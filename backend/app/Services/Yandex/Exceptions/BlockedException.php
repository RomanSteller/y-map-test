<?php

namespace App\Services\Yandex\Exceptions;

/** Яндекс показал капчу / антибот-заслон. Повторить можно, но только после паузы. */
class BlockedException extends ParserException
{
    public function reason(): string
    {
        return 'blocked';
    }
}
