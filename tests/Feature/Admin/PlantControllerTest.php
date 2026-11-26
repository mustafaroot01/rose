<?php

namespace Tests\Feature\Admin;

use App\Models\Plant;
use App\Models\PlantCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlantControllerTest extends TestCase
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

    public function test_admin_can_list_plants(): void
    {
        PlantCategory::factory()->create();
        Plant::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/plants');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'stats']);
    }

    public function test_admin_can_show_plant(): void
    {
        $plant = Plant::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/plants/{$plant->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plant->id);
    }

    public function test_admin_can_update_plant(): void
    {
        $plant = Plant::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/plants/{$plant->id}", [
                'name' => 'نبتة معدّلة',
                'status' => 'approved',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'نبتة معدّلة');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'name' => 'نبتة معدّلة', 'status' => 'approved']);
    }

    public function test_admin_can_update_status(): void
    {
        $plant = Plant::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/plants/{$plant->id}/status", ['status' => 'approved']);

        $response->assertOk();
        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'status' => 'approved']);
    }

    public function test_admin_can_delete_plant(): void
    {
        $plant = Plant::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/plants/{$plant->id}");

        $response->assertOk();
        $this->assertSoftDeleted('plants', ['id' => $plant->id]);
    }

    public function test_admin_can_bulk_status(): void
    {
        $plants = Plant::factory()->count(3)->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/plants/bulk/status', [
                'ids' => $plants->pluck('id')->all(),
                'status' => 'approved',
            ]);

        $response->assertOk();
        $this->assertEquals(3, Plant::where('status', 'approved')->count());
    }

    public function test_admin_can_bulk_delete(): void
    {
        $plants = Plant::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/plants/bulk', [
                'ids' => $plants->pluck('id')->all(),
            ]);

        $response->assertOk();
        $this->assertEquals(2, Plant::onlyTrashed()->count());
    }

    public function test_map_returns_geojson(): void
    {
        Plant::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/plants/map');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(2, 'features');
    }

    public function test_citizen_cannot_access_admin_plants(): void
    {
        $citizen = User::factory()->citizen()->create();
        $citizen->assignRole('citizen');

        $response = $this->actingAs($citizen, 'sanctum')
            ->getJson('/api/admin/plants');

        $response->assertStatus(403);
    }
}
