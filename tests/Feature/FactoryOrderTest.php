<?php

namespace Tests\Feature;

use App\Enums\FactoryOrderStatus;
use App\Models\Company;
use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderForm;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FactoryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['factory_orders.view', 'factory_orders.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create([
            'company_id' => $companyId,
            'name' => 'Deneme Personel',
        ]);
        $user->givePermissionTo(['factory_orders.view', 'factory_orders.manage']);

        return $user;
    }

    public function test_factory_orders_require_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('factory-orders.index'))
            ->assertForbidden();
    }

    public function test_store_uses_the_session_company_and_the_current_user(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->manager($company->id);
        $factory = Factory::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create([
            'name' => 'Profil 40',
            'factory_id' => $factory->id,
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)
            ->post(route('factory-orders.store'), [
                'factory_id' => $factory->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'length' => '6.00',
                'pallet_count' => 1,
                'due_on' => '2026-11-01',
                'contact_name' => 'Depo',
                'company_id' => $other->id,
            ])
            ->assertRedirect();

        $order = FactoryOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame($company->id, $order->company_id);
        $this->assertNotSame($other->id, $order->company_id);
        $this->assertSame($user->id, $order->prepared_by_user_id);
        $this->assertSame('Profil 40', $order->product_name);
        $this->assertSame(FactoryOrderStatus::Open, $order->status);
    }

    public function test_quantity_is_required(): void
    {
        $user = $this->manager();
        $factory = Factory::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->from(route('factory-orders.create'))
            ->post(route('factory-orders.store'), [
                'factory_id' => $factory->id,
                'product_id' => $product->id,
                'quantity' => '',
            ])
            ->assertRedirect(route('factory-orders.create'))
            ->assertSessionHasErrors(['quantity' => 'Adet zorunludur.']);
    }

    public function test_receiving_an_order_adds_piece_stock_once(): void
    {
        $user = $this->manager();
        $product = Product::factory()->create([
            'quantity_piece' => 4,
            'quantity_pallet' => 0,
            'warehouse_quantity' => 0,
        ]);
        $order = FactoryOrder::factory()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 6,
            'status' => FactoryOrderStatus::Open,
            'prepared_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('factory-orders.receive', $order), [
                'destination' => 'piece',
                'quantity' => 6,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('factory-orders.receive', $order), [
                'destination' => 'piece',
                'quantity' => 6,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Bu sipariş zaten teslim alınmış.');

        $this->assertSame(10, $product->refresh()->quantity_piece);
        $this->assertSame(FactoryOrderStatus::Received, $order->refresh()->status);
    }

    public function test_receiving_into_the_warehouse_does_not_change_piece_stock(): void
    {
        $user = $this->manager();
        $product = Product::factory()->create([
            'quantity_piece' => 4,
            'warehouse_quantity' => 1,
        ]);
        $order = FactoryOrder::factory()->create([
            'product_id' => $product->id,
            'status' => FactoryOrderStatus::Open,
            'prepared_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('factory-orders.receive', $order), [
                'destination' => 'warehouse',
                'quantity' => 3,
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(4, $product->quantity_piece);
        $this->assertSame(4, $product->warehouse_quantity);
    }

    public function test_printing_a_form_attaches_only_unprinted_lines(): void
    {
        $user = $this->manager();
        $factory = Factory::factory()->create();
        $existing = FactoryOrderForm::factory()->create(['factory_id' => $factory->id]);
        $printed = FactoryOrder::factory()->create([
            'factory_id' => $factory->id,
            'factory_order_form_id' => $existing->id,
            'prepared_by_user_id' => $user->id,
        ]);
        $open = FactoryOrder::factory()->create([
            'factory_id' => $factory->id,
            'factory_order_form_id' => null,
            'contact_name' => 'Depo',
            'prepared_by_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('factory-order-forms.store'), ['factory_id' => $factory->id])
            ->assertRedirect();

        $open->refresh();
        $printed->refresh();
        $this->assertNotNull($open->factory_order_form_id);
        $this->assertNotSame($existing->id, $open->factory_order_form_id);
        $this->assertSame($existing->id, $printed->factory_order_form_id);
        $this->assertSame($user->id, $open->form?->prepared_by_user_id);
        $this->assertSame('Depo', $open->form?->contact_name);

        $this->actingAs($user)
            ->post(route('factory-order-forms.store'), ['factory_id' => $factory->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'Bu fabrikada forma girmemiş sipariş yok.');

        $this->assertSame(2, FactoryOrderForm::query()->count());
    }

    public function test_deleting_a_form_also_deletes_its_lines(): void
    {
        $user = $this->manager();
        $form = FactoryOrderForm::factory()->create();
        $line = FactoryOrder::factory()->create([
            'factory_id' => $form->factory_id,
            'factory_order_form_id' => $form->id,
        ]);

        $this->actingAs($user)
            ->delete(route('factory-order-forms.destroy', $form))
            ->assertRedirect();

        $this->assertSoftDeleted($form);
        $this->assertSoftDeleted($line);
    }

    public function test_legacy_import_stores_the_preparer_as_a_user_and_the_form_on_the_line(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['name' => 'Deneme Personel']);
        $factory = Factory::factory()->create();
        $product = Product::factory()->create(['name' => 'Profil 40']);
        $directory = storage_path('framework/testing/factory-orders-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/siparis.csv', implode("\n", [
            'siparis_id,hazirlayankisi,urun_fabrika_id,ilgilikisi,urun_id,urun_adi,urun_siparis_aded,siparisboy,palet,taslak,siparissaniye,terminsaniye,formda,sirketid,silik',
            "20,Kayitsiz Kisi,{$factory->id},Depo,{$product->id},Profil 40,4,6.00,1,0,1609459200,1609502400,1,{$company->id},1",
            "21,Deneme Personel,{$factory->id},Depo,{$product->id},Profil 40,8,6.50,0,1,1609459200,1609502400,1,{$company->id},0",
            "22,Deneme Personel,999999,Depo,{$product->id},Profil 40,1,6,0,1,1609459200,1609502400,0,{$company->id},0",
        ]));
        File::put($directory.'/siparisformlari.csv', implode("\n", [
            'formid,siparisler,fabrikaid,saniye,hazirlayankisi,ilgilikisi,sirketid,silik',
            "10,\"20,21\",{$factory->id},1609459200,,,{$company->id},0",
            "11,21,{$factory->id},1609459300,,,{$company->id},0",
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'factory-orders',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $received = FactoryOrder::withTrashed()->find(20);
        $open = FactoryOrder::query()->find(21);

        $this->assertNotNull($received);
        $this->assertNull($received->prepared_by_user_id);
        $this->assertSame(FactoryOrderStatus::Received, $received->status);
        $this->assertSame('2021-01-01', $received->due_on?->toDateString());
        $this->assertSame(1609459200, $received->created_at?->timestamp);
        $this->assertSoftDeleted($received);
        $this->assertSame(10, $received->factory_order_form_id);

        $this->assertNotNull($open);
        $this->assertSame($user->id, $open->prepared_by_user_id);
        $this->assertSame(FactoryOrderStatus::Open, $open->status);
        $this->assertSame($product->id, $open->product_id);
        $this->assertSame($company->id, $open->company_id);
        $this->assertSame(10, $open->factory_order_form_id);
        $this->assertNull(FactoryOrder::withTrashed()->find(22));

        $form = FactoryOrderForm::query()->find(10);
        $this->assertNotNull($form);
        $this->assertSame($user->id, $form->prepared_by_user_id);
        $this->assertSame('Depo', $form->contact_name);
        $this->assertSame(1609459200, $form->created_at?->timestamp);
    }
}
