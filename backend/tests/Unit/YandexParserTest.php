<?php

namespace Tests\Unit;

use App\Services\Yandex\YandexParser;
use PHPUnit\Framework\TestCase;

class YandexParserTest extends TestCase
{
    /** Мини-слепок состояния страницы той же формы, что отдаёт Яндекс. */
    private function sampleState(): array
    {
        return [
            'stuff' => [
                'org' => [
                    'id' => '192990200894',
                    'title' => 'Twins Garden',
                    'fullAddress' => 'Москва, ул. Строителей, 6',
                    'ratingData' => ['ratingValue' => 4.7, 'ratingCount' => 3200, 'reviewCount' => 610],
                ],
                'reviewResults' => [
                    'reviews' => [
                        ['reviewId' => 'a1', 'author' => ['name' => 'Иван'], 'rating' => 5, 'text' => ' Отлично ', 'updatedTime' => '2026-09-01T10:00:00Z'],
                        ['reviewId' => 'a2', 'author' => ['name' => 'Мария'], 'rating' => 4, 'text' => 'Хорошо'],
                    ],
                ],
            ],
        ];
    }

    public function test_maps_org_summary_and_reviews(): void
    {
        $data = YandexParser::mapState($this->sampleState());

        $this->assertSame('Twins Garden', $data['title']);
        $this->assertSame(4.7, $data['rating']);
        $this->assertSame(3200, $data['ratings_count']); // оценки
        $this->assertSame(610, $data['reviews_count']);   // отзывы — это разные числа
        $this->assertCount(2, $data['reviews']);
        $this->assertSame('a1', $data['reviews'][0]['external_id']);
        $this->assertSame('Отлично', $data['reviews'][0]['text']); // текст обрезается
    }

    public function test_extracts_state_from_html(): void
    {
        $html = '<html><body><script type="application/json" class="state-view">'
            .json_encode($this->sampleState())
            .'</script></body></html>';

        $this->assertSame('Twins Garden', YandexParser::mapState(YandexParser::extractState($html))['title']);
    }

    public function test_missing_state_returns_null(): void
    {
        $this->assertNull(YandexParser::extractState('<html>нет данных</html>'));
    }
}
