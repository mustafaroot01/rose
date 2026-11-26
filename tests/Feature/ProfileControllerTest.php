<?php

namespace Tests\Feature;

use App\Models\Citizen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private Citizen $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = Citizen::factory()->create();
    }

    public function test_user_can_view_profile(): void
    {
        $response = $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/citizen/profile');

        $response->assertOk()
            ->assertJsonPath('data.id', $this->citizen->id);
    }

    public function test_user_can_update_profile(): void
    {
        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/citizen/profile', ['name' => 'اسم جديد']);

        $response->assertOk();
        $this->assertDatabaseHas('citizens', ['id' => $this->citizen->id, 'name' => 'اسم جديد']);
    }

    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->citizen, 'sanctum')
            ->putJson('/api/citizen/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertOk();
    }

    public function test_wrong_current_password_rejected(): void
    {
        $response = $this->actingAs($this->citizen, 'sanctum')
            ->putJson('/api/citizen/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/citizen/profile');

        $response->assertStatus(401);
    }
}
