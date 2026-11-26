<?php

namespace Tests\Feature\Admin;

use App\Models\PlantReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlantReportControllerTest extends TestCase
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

    public function test_admin_can_list_reports(): void
    {
        PlantReport::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/plant-reports');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'stats']);
    }

    public function test_admin_can_show_report(): void
    {
        $report = PlantReport::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/plant-reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id);
    }

    public function test_admin_can_update_report_status(): void
    {
        $report = PlantReport::factory()->create(['status' => 'new']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/plant-reports/{$report->id}/status", [
                'status' => 'in_review',
                'admin_note' => 'قيد المتابعة',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('plant_reports', [
            'id' => $report->id,
            'status' => 'in_review',
            'admin_note' => 'قيد المتابعة',
        ]);
    }

    public function test_admin_can_delete_report(): void
    {
        $report = PlantReport::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/plant-reports/{$report->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('plant_reports', ['id' => $report->id]);
    }

    public function test_admin_can_bulk_status(): void
    {
        $reports = PlantReport::factory()->count(3)->create(['status' => 'new']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/plant-reports/bulk/status', [
                'ids' => $reports->pluck('id')->all(),
                'status' => 'action_taken',
            ]);

        $response->assertOk();
        $this->assertEquals(3, PlantReport::where('status', 'action_taken')->count());
    }

    public function test_date_filter_works(): void
    {
        PlantReport::factory()->create(['created_at' => now()->subDays(10)]);
        PlantReport::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/plant-reports?date_from='.now()->subDay()->toDateString());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
