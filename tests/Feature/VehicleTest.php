<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithVehiclesPermission(?int $companyId = null): User
    {
        foreach (['vehicles.view', 'vehicles.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo(['vehicles.view', 'vehicles.manage']);

        return $user;
    }

    public function test_vehicles_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('vehicles.index'))
            ->assertForbidden();
    }

    public function test_vehicle_stores_the_signed_in_users_company_and_a_document(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = $this->userWithVehiclesPermission($company->id);

        $this->actingAs($user)
            ->post(route('vehicles.store'), [
                'name' => 'Iveco',
                'license_plate' => '34 MYR 025',
                'driver_name' => 'Hasan Çevik',
                'is_delivery_vehicle' => true,
                'casco_expires_on' => '2026-03-19',
                'casco' => UploadedFile::fake()->create('kasko.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $vehicle = Vehicle::query()->where('license_plate', '34 MYR 025')->first();

        $this->assertNotNull($vehicle);
        $this->assertSame($company->id, $vehicle->company_id);
        $this->assertTrue($vehicle->is_delivery_vehicle);
        $this->assertTrue($vehicle->media()->where('collection', 'casco')->whereNotNull('path')->exists());

        $document = $vehicle->media()->where('collection', 'casco')->first();

        $this->actingAs($user)
            ->get(route('vehicles.documents.download', [$vehicle, $document]))
            ->assertOk();
    }
}
