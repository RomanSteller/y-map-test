<?php

namespace App\Services\Yandex\Exceptions;

/**
 * Карточка нашлась и выглядит нормально, но отзывов пришло ноль, хотя счётчик
 * говорит, что они есть — верный признак, что сломалась подгрузка или эндпоинт
 * отзывов, а не то, что у организации и правда нет ни одного отзыва.
 */
class EmptyResultException extends ParserException
{
    public function reason(): string
    {
        return 'empty_result';
    }
}
