<?php

namespace App\Services\Yandex\DTO;

/**
 * Everything the parser extracts for one organisation card.
 *
 * @property-read ReviewData[] $reviews
 */
final class OrganizationData
{
    /** @param ReviewData[] $reviews */
    public function __construct(
        public readonly string $yandexId,
        public readonly ?string $title,
        public readonly ?string $address,
        public readonly array $categories,
        public readonly ?float $rating,
        public readonly int $ratingsCount,
        public readonly int $reviewsCount,
        public readonly array $reviews,
    ) {
    }

    /** @param array<int, array> $raw */
    public static function fromArray(array $raw): self
    {
        $org = $raw['organization'] ?? $raw;

        $reviews = array_map(
            fn (array $r) => ReviewData::fromArray($r),
            $raw['reviews'] ?? []
        );

        return new self(
            yandexId: (string) ($org['yandex_id'] ?? $org['id'] ?? ''),
            title: $org['title'] ?? null,
            address: $org['address'] ?? null,
            categories: array_values($org['categories'] ?? []),
            rating: isset($org['rating']) ? round((float) $org['rating'], 2) : null,
            ratingsCount: (int) ($org['ratings_count'] ?? 0),
            reviewsCount: (int) ($org['reviews_count'] ?? 0),
            reviews: $reviews,
        );
    }

    /** Order-independent fingerprint of the whole review set. */
    public function reviewsHash(): string
    {
        $hashes = array_map(fn (ReviewData $r) => $r->externalId.':'.$r->contentHash(), $this->reviews);
        sort($hashes);

        return sha1(implode("\n", $hashes));
    }
}
