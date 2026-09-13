<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'url', 'yandex_id', 'slug', 'title', 'address',
        'rating', 'ratings_count', 'reviews_count', 'parse_error', 'last_parsed_at',
        'text',
        'reviewed_at',
        'author',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'last_parsed_at' => 'datetime',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
