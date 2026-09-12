<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'url',
        'yandex_id',
        'slug',
        'title',
        'address',
        'categories',
        'rating',
        'ratings_count',
        'reviews_count',
        'parse_status',
        'parse_progress',
        'parse_error_reason',
        'parse_error',
        'last_parsed_at',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'parse_progress' => 'integer',
            'last_parsed_at' => 'datetime',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(OrganizationSnapshot::class)->latestOfMany();
    }

    public function isBusy(): bool
    {
        return in_array($this->parse_status, [self::STATUS_QUEUED, self::STATUS_PARSING], true);
    }
}
