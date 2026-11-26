<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('citizen', 'web');

        $this->admin = User::factory()->admin()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->citizen()->create()->each(fn ($u) => $u->assignRole('citizen'));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_show_user(): void
    {
        $user = User::factory()->citizen()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_admin_can_create_staff(): void
    {
        Role::findOrCreate('supervisor', 'web');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'مشرف جديد',
                'phone' => '07801234567',
                'password' => 'secret123',
                'role' => 'supervisor',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'مشرف جديد');
        $this->assertDatabaseHas('users', ['phone' => '07801234567', 'role' => 'supervisor']);
    }

    public function test_create_staff_rejects_citizen_role(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name' => 'x',
                'phone' => '07809999999',
                'password' => 'secret123',
                'role' => 'citizen',
                'status' => 'active',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/toggle-status");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'blocked']);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->citizen()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertOk();
    }

    public function test_citizen_cannot_access_admin_users(): void
    {
        $citizen = User::factory()->citizen()->create();
        $citizen->assignRole('citizen');

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}
