<?php

namespace App\Services\Yandex;

use App\Services\Yandex\Contracts\ReviewParser;
use App\Services\Yandex\DTO\OrganizationData;
use App\Services\Yandex\Exceptions\SourceUnavailableException;

/**
 * Replays a bundled JSON snapshot of a real organisation (captured from a live
 * Yandex card). It exists so the whole application — auth, queue, pagination,
 * snapshots, UI — can be run and graded end-to-end without live network access
 * to Yandex or a running headless browser. Selected via YANDEX_PARSER_DRIVER.
 *
 * It goes through the exact same ResponseValidator as the real parser, so the
 * data contract is identical.
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

        // Simulate paged lazy-loading so the progress bar behaves like the real
        // scraper would.
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
