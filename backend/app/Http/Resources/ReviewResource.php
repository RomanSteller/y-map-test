<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'author' => $this->author,
            'rating' => $this->rating,
            'text' => $this->text,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
