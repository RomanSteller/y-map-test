<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Jobs\ParseOrganizationReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizationParsingTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create([
            'name' => 'Demo',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($user);

        return $user;
    }

    public function test_saving_a_valid_url_dispatches_a_parse_job(): void
    {
        $this->actingUser();
        Queue::fake();

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/',
        ])->assertCreated()
            ->assertJsonPath('data.yandex_id', '192990200894')
            ->assertJsonPath('data.parse.status', 'queued');

        Queue::assertPushed(ParseOrganizationReviews::class);
        $this->assertDatabaseHas('organizations', ['yandex_id' => '192990200894']);
    }

    public function test_saving_an_invalid_url_is_rejected(): void
    {
        $this->actingUser();

        $this->postJson('/api/organizations', ['url' => 'https://example.com/foo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_saving_an_already_added_organization_is_rejected(): void
    {
        $this->actingUser();
        $url = 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/';

        $this->postJson('/api/organizations', ['url' => $url])->assertCreated();

        // Та же организация во второй раз — ошибка, дубль не создаётся.
        $this->postJson('/api/organizations', ['url' => $url])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['url']);

        $this->assertSame(1, Organization::count());
    }

    public function test_parsing_stores_reviews_counts_and_rating(): void
    {
        // В тестах очередь синхронная, так что POST реально парсит через фикстуру.
        $this->actingUser();

        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/',
        ])->assertCreated();

        $org = Organization::firstOrFail();
        $this->assertSame(Organization::STATUS_COMPLETED, $org->parse_status);
        $this->assertSame(4.8, $org->rating);
        $this->assertSame(3292, $org->ratings_count);   // «оценки»
        $this->assertSame(1472, $org->reviews_count);   // «отзывы»
        $this->assertSame(300, $org->reviews()->count());
        $this->assertSame(1, $org->snapshots()->count());
    }

    public function test_reviews_are_paginated_50_per_page(): void
    {
        $this->actingUser();
        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/',
        ])->assertCreated();

        $org = Organization::firstOrFail();

        $this->getJson("/api/organizations/{$org->id}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'data')
            ->assertJsonStructure(['data' => [['author', 'rating', 'text', 'reviewed_at']]]);
    }

    public function test_reparse_is_idempotent_and_records_history(): void
    {
        $this->actingUser();
        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/',
        ])->assertCreated();

        $org = Organization::firstOrFail();
        $this->assertSame(300, $org->reviews()->count());

        // Парсим ту же организацию ещё раз.
        $this->postJson("/api/organizations/{$org->id}/parse")->assertOk();

        $org->refresh();
        // Дублей отзывов не появилось...
        $this->assertSame(300, $org->reviews()->count());
        // ...но второй снимок зафиксировал (нулевую) разницу — история сохраняется.
        $this->assertSame(2, $org->snapshots()->count());
        $this->assertSame(0, $org->latestSnapshot->reviews_added);
    }

    public function test_status_endpoint_reports_progress(): void
    {
        $this->actingUser();
        $this->postJson('/api/organizations', [
            'url' => 'https://yandex.ru/maps/org/twins_garden/192990200894/reviews/',
        ])->assertCreated();

        $org = Organization::firstOrFail();

        $this->getJson("/api/organizations/{$org->id}/status")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('reviews_count', 1472);
    }
}
