<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // REGISTER

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'João',
            'email'    => 'joao@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'name', 'email'], 'message'])
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['email' => 'joao@test.com']);
    }

    public function test_register_hashes_password(): void
    {
        $this->postJson('/api/register', [
            'name'     => 'João',
            'email'    => 'joao@test.com',
            'password' => 'password123',
        ]);

        $user = User::where('email', 'joao@test.com')->first();
        $this->assertNotEquals('password123', $user->password);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@test.com']);

        $response = $this->postJson('/api/register', [
            'name'     => 'Outro',
            'email'    => 'joao@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_register_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'João',
            'email'    => 'nao-e-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name'     => 'João',
            'email'    => 'joao@test.com',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    // LOGIN

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email'    => 'joao@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'joao@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'joao@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'joao@test.com',
            'password' => 'errada',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_login_fails_when_user_not_found(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'nao@existe.com',
            'password' => 'qualquer',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_login_requires_authentication_on_protected_routes(): void
    {
        $response = $this->postJson('/api/deposit', ['amount' => 100]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }
}
