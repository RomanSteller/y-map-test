<?php

namespace App\Services\Yandex\DTO;

use Carbon\CarbonImmutable;

/**
 * Один распарсенный отзыв, приведённый к виду, который ждёт наша БД, и
 * отвязанный от того, как сегодня выглядит ответ Яндекса.
 */
final class ReviewData
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $author,
        public readonly ?int $rating,
        public readonly ?string $text,
        public readonly ?CarbonImmutable $reviewedAt,
    ) {
    }

    public static function fromArray(array $r): self
    {
        $date = $r['reviewed_at'] ?? $r['updatedTime'] ?? null;

        return new self(
            externalId: (string) ($r['external_id'] ?? $r['reviewId'] ?? ''),
            author: $r['author'] ?? null,
            rating: isset($r['rating']) ? (int) round((float) $r['rating']) : null,
            text: isset($r['text']) ? trim((string) $r['text']) : null,
            reviewedAt: $date ? CarbonImmutable::parse($date) : null,
        );
    }

    /** Отпечаток изменяемого содержимого — по нему ловим правки в отзыве. */
    public function contentHash(): string
    {
        return sha1(implode('|', [
            $this->author,
            $this->rating,
            $this->text,
            $this->reviewedAt?->toIso8601String(),
        ]));
    }
}
