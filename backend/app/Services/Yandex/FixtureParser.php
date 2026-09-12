<?php

namespace App\Services\Yandex;

use App\Services\Yandex\Contracts\ReviewParser;
use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\Exceptions\SourceUnavailableException;

/**
 * Проигрывает сохранённый JSON-снимок реальной организации (снятый с живой
 * карточки Яндекса). Нужен, чтобы всё приложение — авторизацию, очередь,
 * пагинацию, снимки, интерфейс — можно было прогнать целиком без живого доступа
 * к Яндексу и без запущенного headless-браузера. Выбирается через
 * YANDEX_PARSER_DRIVER.
 *
 * Прогоняется через тот же самый ResponseValidator, что и боевой парсер, так
 * что контракт данных одинаковый.
 */
final class FixtureParser implements ReviewParser
{
    public function __construct(
        private readonly ResponseValidator $validator,
        private readonly string $fixturePath,
    ) {
    }

    public function parse(YandexUrl $url, ?callable $onProgress = null): OrganizationData
    {
        $this->report($onProgress, 5, 'Открываю карточку организации…');

        if (! is_file($this->fixturePath)) {
            throw new SourceUnavailableException("Фикстура не найдена: {$this->fixturePath}");
        }

        $raw = json_decode((string) file_get_contents($this->fixturePath), true);
        if (! is_array($raw)) {
            throw new SourceUnavailableException('Не удалось прочитать фикстуру.');
        }

        // Изображаем постраничную подгрузку, чтобы прогресс-бар вёл себя так же,
        // как с настоящим скрапером.
        $total = max(1, (int) ($raw['organization']['reviews_count'] ?? count($raw['reviews'] ?? [])));
        $loaded = count($raw['reviews'] ?? []);
        for ($i = 1; $i <= 4; $i++) {
            $this->report($onProgress, (int) (10 + $i * 20), "Загружаю отзывы… ({$loaded} из ~{$total})");
        }

        $data = $this->validator->validate($raw);
        $this->report($onProgress, 100, 'Готово.');

        return $data;
    }

    private function report(?callable $cb, int $progress, string $message): void
    {
        if ($cb) {
            $cb($progress, $message);
        }
    }
}
