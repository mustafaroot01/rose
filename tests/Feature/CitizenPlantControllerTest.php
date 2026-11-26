<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\Plant;
use App\Models\PlantCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitizenPlantControllerTest extends TestCase
{
    use RefreshDatabase;

    private Citizen $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = Citizen::factory()->create();
    }

    public function test_citizen_can_list_own_plants(): void
    {
        Plant::factory()->count(2)->create(['user_id' => null, 'citizen_id' => $this->citizen->id]);
        Plant::factory()->create(); // another owner's plant

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/citizen/my-plants');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_citizen_can_create_plant(): void
    {
        $category = PlantCategory::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/citizen/plants', [
                'category_id' => $category->id,
                'name' => 'شجرة زيتون',
                'latitude' => 33.3,
                'longitude' => 44.4,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('plants', ['name' => 'شجرة زيتون', 'citizen_id' => $this->citizen->id, 'status' => 'pending']);
    }

    public function test_citizen_can_view_own_plant(): void
    {
        $plant = Plant::factory()->create(['user_id' => null, 'citizen_id' => $this->citizen->id]);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->getJson("/api/citizen/plants/{$plant->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plant->id);
    }

    public function test_citizen_cannot_view_others_plant(): void
    {
        $otherCitizen = Citizen::factory()->create();
        $plant = Plant::factory()->create(['user_id' => null, 'citizen_id' => $otherCitizen->id]);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->getJson("/api/citizen/plants/{$plant->id}");

        $response->assertStatus(403);
    }

    public function test_citizen_can_update_own_plant(): void
    {
        $plant = Plant::factory()->create(['user_id' => null, 'citizen_id' => $this->citizen->id, 'status' => 'approved']);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->putJson("/api/citizen/plants/{$plant->id}", ['name' => 'اسم جديد']);

        $response->assertOk();
        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'name' => 'اسم جديد', 'status' => 'pending']);
    }

    public function test_citizen_can_delete_own_plant(): void
    {
        $plant = Plant::factory()->create(['user_id' => null, 'citizen_id' => $this->citizen->id]);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->deleteJson("/api/citizen/plants/{$plant->id}");

        $response->assertOk();
        $this->assertSoftDeleted('plants', ['id' => $plant->id]);
    }

    public function test_citizen_can_report_plant(): void
    {
        $plant = Plant::factory()->create();

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson("/api/citizen/plants/{$plant->id}/report", [
                'reason' => 'withered',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('plant_reports', ['plant_id' => $plant->id, 'citizen_reporter_id' => $this->citizen->id]);
    }

    public function test_citizen_can_water_plant(): void
    {
        $plant = Plant::factory()->create();

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson("/api/citizen/plants/{$plant->id}/water");

        $response->assertOk();
        $this->assertDatabaseHas('plant_waterings', ['plant_id' => $plant->id, 'citizen_id' => $this->citizen->id]);
    }

    public function test_guest_cannot_access_plants(): void
    {
        $response = $this->getJson('/api/citizen/my-plants');

        $response->assertStatus(401);
    }
}
