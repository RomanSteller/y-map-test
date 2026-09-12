<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'yandex_id' => $this->yandex_id,
            'title' => $this->title,
            'address' => $this->address,
            'categories' => $this->categories ?? [],

            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,   // «оценки»
            'reviews_count' => $this->reviews_count,    // «отзывы»

            'parse' => [
                'status' => $this->parse_status,
                'progress' => $this->parse_progress,
                'error_reason' => $this->parse_error_reason,
                'error' => $this->parse_error,
                'is_busy' => $this->isBusy(),
                'last_parsed_at' => $this->last_parsed_at?->toIso8601String(),
            ],

            // The most recent snapshot's diff, so the UI can show "было → стало".
            'last_snapshot' => new SnapshotResource($this->whenLoaded('latestSnapshot')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
