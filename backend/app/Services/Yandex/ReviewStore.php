<?php

namespace App\Services\Yandex;

use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Services\Yandex\DTO\OrganizationData;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентно сохраняет результат парсинга.
 *
 * Повторный парсинг той же организации не должен плодить дубли отзывов: делаем
 * upsert по (organization_id, external_id), а по хэшу содержимого отличаем
 * «новый» от «изменённого» и от «без изменений». Каждый удачный парсинг ещё и
 * пишет OrganizationSnapshot, чтобы можно было поднять агрегатную историю
 * (рейтинг и счётчики во времени, сколько отзывов появилось или поменялось) —
 * то самое требование «было → стало».
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
