<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_cliente_by_default(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'uuid', 'name', 'email', 'role'],
                'access_token',
                'token_type',
            ])
            ->assertJsonPath('user.role', 'cliente');

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => 'cliente',
        ]);
    }

    public function test_user_can_register_with_specific_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Proveedor S.A.',
            'email' => 'proveedor@example.com',
            'password' => 'password123',
            'role' => UserRole::Proveedor->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'proveedor');

        $this->assertDatabaseHas('users', [
            'email' => 'proveedor@example.com',
            'role' => 'proveedor',
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => bcrypt('secret123'),
            'role' => UserRole::Cliente->value,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'access_token'])
            ->assertJsonPath('user.email', 'cliente@example.com');
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::Cliente->value]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'cliente');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Sesión cerrada exitosamente');

        $this->assertCount(0, $user->tokens);
    }
}
