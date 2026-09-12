<?php

namespace App\Services\Yandex;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Services\Yandex\DTO\OrganizationData;
use Illuminate\Support\Facades\DB;

/**
 * Persists a parse result idempotently.
 *
 * Re-parsing the same organisation must not create duplicate reviews: we upsert
 * on (organization_id, external_id) and use each review's content hash to tell
 * "new" from "changed" from "unchanged". Every successful parse also writes an
 * OrganizationSnapshot, so the aggregate history (rating / counts over time,
 * how many reviews appeared or changed) is queryable — the "было → стало"
 * requirement.
 */
final class ReviewStore
{
    public function persist(Organization $organization, OrganizationData $data): OrganizationSnapshot
    {
        return DB::transaction(function () use ($organization, $data) {
            $organization->fill([
                'title' => $data->title ?? $organization->title,
                'address' => $data->address ?? $organization->address,
                'categories' => $data->categories,
                'rating' => $data->rating,
                'ratings_count' => $data->ratingsCount,
                'reviews_count' => $data->reviewsCount,
                'last_parsed_at' => now(),
            ])->save();

            $added = 0;
            $updated = 0;

            foreach ($data->reviews as $review) {
                if ($review->externalId === '') {
                    continue;
                }

                $existing = $organization->reviews()
                    ->where('external_id', $review->externalId)
                    ->first();

                $hash = $review->contentHash();

                if ($existing === null) {
                    $organization->reviews()->create([
                        'external_id' => $review->externalId,
                        'author' => $review->author,
                        'rating' => $review->rating,
                        'text' => $review->text,
                        'reviewed_at' => $review->reviewedAt,
                        'content_hash' => $hash,
                    ]);
                    $added++;
                } elseif ($existing->content_hash !== $hash) {
                    $existing->update([
                        'author' => $review->author,
                        'rating' => $review->rating,
                        'text' => $review->text,
                        'reviewed_at' => $review->reviewedAt,
                        'content_hash' => $hash,
                    ]);
                    $updated++;
                }
            }

            return $organization->snapshots()->create([
                'rating' => $data->rating,
                'ratings_count' => $data->ratingsCount,
                'reviews_count' => $data->reviewsCount,
                'reviews_scraped' => count($data->reviews),
                'reviews_added' => $added,
                'reviews_updated' => $updated,
                'reviews_hash' => $data->reviewsHash(),
            ]);
        });
    }
}
