<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function seedUser(): User
    {
        return User::create([
            'name' => 'Demo',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $this->seedUser();

        // Mimic the SPA: an Origin from a stateful domain makes Sanctum start
        // the session, exactly as it does for the real front-end.
        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@example.com');

        $this->assertAuthenticated();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->seedUser();

        $this->postJson('/api/login', ['email' => 'demo@example.com', 'password' => 'nope'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/organizations')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_self(): void
    {
        $user = $this->seedUser();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@example.com');
    }
}
