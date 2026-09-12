<?php

namespace App\Http\Resources;

use App\Models\OrganizationSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrganizationSnapshot */
class SnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'reviews_scraped' => $this->reviews_scraped,
            'reviews_added' => $this->reviews_added,
            'reviews_updated' => $this->reviews_updated,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
