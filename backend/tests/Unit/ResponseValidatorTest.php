<?php

namespace Tests\Unit;

use App\Services\Yandex\Exceptions\EmptyResultException;
use App\Services\Yandex\Exceptions\MarkupChangedException;
use App\Services\Yandex\ResponseValidator;
use PHPUnit\Framework\TestCase;

class ResponseValidatorTest extends TestCase
{
    private ResponseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ResponseValidator();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'organization' => [
                'yandex_id' => '1',
                'title' => 'Test',
                'rating' => 4.5,
                'ratings_count' => 100,
                'reviews_count' => 2,
            ],
            'reviews' => [
                ['external_id' => 'a', 'author' => 'X', 'rating' => 5, 'text' => 'ok', 'reviewed_at' => '2024-01-01T00:00:00Z'],
                ['external_id' => 'b', 'author' => 'Y', 'rating' => 4, 'text' => 'fine', 'reviewed_at' => '2024-01-02T00:00:00Z'],
            ],
        ], $overrides);
    }

    public function test_valid_payload_passes(): void
    {
        $data = $this->validator->validate($this->validPayload());
        $this->assertSame('1', $data->yandexId);
        $this->assertCount(2, $data->reviews);
    }

    public function test_missing_organization_is_treated_as_markup_change(): void
    {
        $this->expectException(MarkupChangedException::class);
        $this->validator->validate(['reviews' => []]);
    }

    public function test_missing_id_is_markup_change(): void
    {
        $this->expectException(MarkupChangedException::class);
        $payload = $this->validPayload();
        unset($payload['organization']['yandex_id']);
        $this->validator->validate($payload);
    }

    public function test_rating_out_of_range_is_markup_change(): void
    {
        $this->expectException(MarkupChangedException::class);
        $this->validator->validate($this->validPayload(['organization' => ['rating' => 42]]));
    }

    public function test_counted_reviews_but_none_returned_is_empty_result(): void
    {
        $this->expectException(EmptyResultException::class);
        $this->validator->validate([
            'organization' => ['yandex_id' => '1', 'reviews_count' => 500, 'rating' => 4.0],
            'reviews' => [],
        ]);
    }

    public function test_genuinely_zero_reviews_is_allowed(): void
    {
        $data = $this->validator->validate([
            'organization' => ['yandex_id' => '1', 'reviews_count' => 0, 'ratings_count' => 0, 'rating' => null],
            'reviews' => [],
        ]);
        $this->assertCount(0, $data->reviews);
    }
}
