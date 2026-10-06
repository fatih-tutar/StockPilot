<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GoodsFlow;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GoodsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function person(?int $companyId = null, bool $manage = true): User
    {
        Permission::findOrCreate('goods_flows.view');
        Permission::findOrCreate('goods_flows.manage');

        $user = User::factory()->create([
            'company_id' => $companyId,
        ]);
        $user->givePermissionTo($manage ? ['goods_flows.view', 'goods_flows.manage'] : ['goods_flows.view']);

        return $user;
    }

    public function test_a_day_can_be_recorded_with_a_comma_decimal(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->person($company->id);

        $this->actingAs($user)
            ->post(route('goods-flows.store'), [
                'recorded_on' => '2026-03-02',
                'store_outgoing' => '1,5',
                'store_incoming' => '',
                'warehouse_outgoing' => '2',
                'warehouse_incoming' => '0',
                'company_id' => $other->id,
            ])
            ->assertRedirect(route('goods-flows.index'));

        $flow = GoodsFlow::query()->first();
        $this->assertNotNull($flow);
        $this->assertSame($company->id, $flow->company_id);
        $this->assertSame('2026-03-02', $flow->recorded_on->toDateString());
        $this->assertSame('1.500', $flow->store_outgoing);
        $this->assertSame('0.000', $flow->store_incoming);
        $this->assertSame('2.000', $flow->warehouse_outgoing);
    }

    public function test_the_date_is_required_and_cannot_repeat(): void
    {
        $user = $this->person();
        GoodsFlow::factory()->create([
            'company_id' => $user->company_id,
            'recorded_on' => '2026-03-02',
        ]);

        $this->actingAs($user)
            ->post(route('goods-flows.store'), [])
            ->assertSessionHasErrors([
                'recorded_on' => 'Tarih zorunludur.',
            ]);

        $this->actingAs($user)
            ->post(route('goods-flows.store'), [
                'recorded_on' => '2026-03-02',
                'store_outgoing' => '-1',
            ])
            ->assertSessionHasErrors([
                'recorded_on' => 'Bu tarihle zaten bir kayıt var, onu düzenleyebilirsiniz.',
                'store_outgoing' => 'Çağlayan giden negatif olamaz.',
            ]);

        $company = Company::factory()->create();
        GoodsFlow::factory()->create([
            'company_id' => $company->id,
            'recorded_on' => '2026-04-01',
        ]);

        $this->actingAs($user)
            ->post(route('goods-flows.store'), [
                'recorded_on' => '2026-04-01',
                'store_outgoing' => '3',
            ])
            ->assertSessionHasErrors([
                'recorded_on' => 'Bu tarihle zaten bir kayıt var, onu düzenleyebilirsiniz.',
            ]);
        $this->assertSame(2, GoodsFlow::withTrashed()->count());
    }

    public function test_a_day_can_be_updated_and_removed(): void
    {
        $company = Company::factory()->create();
        $user = $this->person($company->id);
        $flow = GoodsFlow::factory()->create([
            'company_id' => $company->id,
            'recorded_on' => '2026-03-02',
            'store_outgoing' => 4,
        ]);

        $this->actingAs($user)
            ->put(route('goods-flows.update', $flow), [
                'recorded_on' => '2026-03-04',
                'store_outgoing' => '8',
                'store_incoming' => '1',
                'warehouse_outgoing' => '0',
                'warehouse_incoming' => '0',
            ])
            ->assertRedirect(route('goods-flows.index'));

        $this->assertSame('2026-03-04', $flow->fresh()->recorded_on->toDateString());
        $this->assertSame('8.000', $flow->fresh()->store_outgoing);

        $this->actingAs($user)
            ->delete(route('goods-flows.destroy', $flow))
            ->assertRedirect(route('goods-flows.index'));

        $this->assertSoftDeleted($flow);
    }

    public function test_a_user_cannot_open_or_change_another_companys_day(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->person($company->id);
        $outsider = User::factory()->create(['company_id' => $company->id]);
        $flow = GoodsFlow::factory()->create([
            'company_id' => $other->id,
            'recorded_on' => '2026-03-02',
        ]);

        $this->actingAs($outsider)
            ->get(route('goods-flows.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('goods-flows.update', $flow), [
                'recorded_on' => '2026-03-05',
                'store_outgoing' => '1',
            ])
            ->assertForbidden();
    }

    public function test_the_list_shows_stock_weight_and_the_week_total(): void
    {
        $company = Company::factory()->create();
        $user = $this->person($company->id);
        Product::factory()->create([
            'company_id' => $company->id,
            'quantity_piece' => 10,
            'quantity_pallet' => 1,
            'warehouse_quantity' => 3,
            'unit_weight_kg' => 2,
        ]);
        GoodsFlow::factory()->create([
            'company_id' => $company->id,
            'recorded_on' => '2026-03-03',
            'store_outgoing' => 5,
        ]);
        GoodsFlow::factory()->create([
            'company_id' => $company->id,
            'recorded_on' => '2026-03-02',
            'store_outgoing' => 10,
        ]);
        GoodsFlow::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'recorded_on' => '2026-03-02',
            'store_outgoing' => 99,
        ]);

        $this->actingAs($user)
            ->get(route('goods-flows.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('weights.store', '20 kg')
                ->where('weights.warehouse', '8 kg')
                ->where('weights.total', '28 kg')
                ->where('rows', function ($rows) {
                    $week = collect($rows)->first(fn ($row) => $row['kind'] === 'week' && $row['label'] === '10. hafta 2026');

                    return $week !== null
                        && $week['store_outgoing'] === '15 kg'
                        && collect($rows)->contains(fn ($row) => $row['kind'] === 'day' && $row['label'] === '03.03.2026');
                }));

        $this->actingAs($user)
            ->get(route('goods-flows.index', ['view' => 'weekly']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('weekly', true)
                ->where('rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['kind'] !== 'day')));

        $this->actingAs($user)
            ->get(route('goods-flows.report'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('companyName', $company->name)
                ->where('rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['kind'] !== 'day')));
    }

    public function test_legacy_import_keeps_ids_and_skips_blank_dates(): void
    {
        $company = Company::factory()->create();
        $directory = storage_path('framework/testing/movement-import');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/movements.csv', implode("\n", [
            'id,incoming,outgoing,warehouse_incoming,warehouse_outgoing,date,is_deleted,company_id',
            "8,1.5,2,0,3.25,2026-03-02,0,{$company->id}",
            '9,1,1,1,1,0000-00-00,0,2',
            '10,4,5,6,7,2024-01-08,1,99999',
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'movements',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'movements',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $kept = GoodsFlow::query()->find(8);
        $removed = GoodsFlow::withTrashed()->find(10);

        $this->assertNotNull($kept);
        $this->assertSame('1.500', $kept->store_incoming);
        $this->assertSame('3.250', $kept->warehouse_outgoing);
        $this->assertSame($company->id, $kept->company_id);
        $this->assertNull(GoodsFlow::withTrashed()->find(9));
        $this->assertNotNull($removed);
        $this->assertTrue($removed->trashed());
        $this->assertNull($removed->company_id);
        $this->assertSame(2, GoodsFlow::withTrashed()->count());
    }
}
