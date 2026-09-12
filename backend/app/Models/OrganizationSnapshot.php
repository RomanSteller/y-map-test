<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSnapshot extends Model
{
    protected $fillable = [
        'organization_id',
        'rating',
        'ratings_count',
        'reviews_count',
        'reviews_scraped',
        'reviews_added',
        'reviews_updated',
        'reviews_hash',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'reviews_scraped' => 'integer',
            'reviews_added' => 'integer',
            'reviews_updated' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
