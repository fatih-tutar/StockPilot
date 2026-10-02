<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomerVisit;
use App\Models\CustomerVisitCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['visits.view', 'visits.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo(['visits.view', 'visits.manage']);

        return $user;
    }

    public function test_visits_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('customer-visits.index'))
            ->assertForbidden();
    }

    public function test_visit_stores_the_signed_in_users_company_and_sector(): void
    {
        $company = Company::factory()->create();
        $user = $this->manager($company->id);
        $category = CustomerVisitCategory::factory()->create(['name' => 'Aydınlatma']);

        $this->actingAs($user)
            ->post(route('customer-visits.store'), [
                'city' => 'İstanbul',
                'district' => 'Kağıthane',
                'customer_visit_category_id' => $category->id,
                'customer_name' => 'Akbudak Aydınlatma',
                'contact_name' => 'Yusuf',
                'phone' => '0544 578 79 82',
                'visited_on' => '2023-05-16',
            ])
            ->assertRedirect();

        $visit = CustomerVisit::query()->where('customer_name', 'Akbudak Aydınlatma')->first();

        $this->assertNotNull($visit);
        $this->assertSame($company->id, $visit->company_id);
        $this->assertSame($category->id, $visit->customer_visit_category_id);
    }

    public function test_sector_can_be_added_from_the_visit_list(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->post(route('customer-visits.categories.store'), [
                'name' => 'Reklam',
            ])
            ->assertRedirect(route('customer-visits.index'));

        $this->assertDatabaseHas('customer_visit_categories', [
            'name' => 'Reklam',
        ]);
    }
}
