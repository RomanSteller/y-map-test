<?php

namespace App\Services\Yandex\DTO;

use Carbon\CarbonImmutable;

/**
 * A single parsed review, normalised into the shape our DB expects and
 * decoupled from whatever Yandex's payload happens to look like today.
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

    /** Fingerprint of the mutable content, used to detect edited reviews. */
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
