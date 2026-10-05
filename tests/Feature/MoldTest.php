<?php

namespace Tests\Feature;

use App\Enums\MoldDocument;
use App\Models\Client;
use App\Models\Company;
use App\Models\Factory;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderForm;
use App\Models\Media;
use App\Models\Mold;
use App\Models\MoldNumber;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MoldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['molds.view', 'molds.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create([
            'company_id' => $companyId,
        ]);
        $user->givePermissionTo(['molds.view', 'molds.manage']);

        return $user;
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function pdfs(): array
    {
        return [
            MoldDocument::FactoryApproval->value => UploadedFile::fake()->create('fabrika.pdf', 20, 'application/pdf'),
            MoldDocument::ClientApproval->value => UploadedFile::fake()->create('firma.pdf', 20, 'application/pdf'),
            MoldDocument::Contract->value => UploadedFile::fake()->create('sozlesme.pdf', 20, 'application/pdf'),
        ];
    }

    public function test_molds_require_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('molds.index'))
            ->assertForbidden();
    }

    public function test_store_uses_the_session_company_and_keeps_the_pdfs(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->manager($company->id);
        $client = Client::factory()->create();
        $factory = Factory::factory()->create();

        $this->actingAs($user)
            ->post(route('molds.store'), [
                'client_id' => $client->id,
                'factory_id' => $factory->id,
                'number' => '12-34',
                'client_offer_price' => '1000 EURO + KDV',
                'factory_offer_price' => '800',
                'due_on' => '2026-11-01',
                'contact_name' => 'Depo',
                'description' => '',
                'company_id' => $other->id,
                ...$this->pdfs(),
            ])
            ->assertRedirect(route('molds.index'));

        $mold = Mold::query()->first();
        $this->assertNotNull($mold);
        $this->assertSame($company->id, $mold->company_id);
        $this->assertNotSame($other->id, $mold->company_id);
        $this->assertSame($user->id, $mold->created_by_user_id);
        $this->assertSame('1000 EURO + KDV', $mold->client_offer_price);
        $this->assertNull($mold->description);
        $this->assertNull($mold->archived_at);
        $this->assertCount(3, $mold->media);
        $this->assertTrue($mold->media->every(fn (Media $media) => $media->isStored()));
    }

    public function test_a_new_mold_requires_its_number_and_pdfs(): void
    {
        $user = $this->manager();
        $client = Client::factory()->create();
        $factory = Factory::factory()->create();

        $this->actingAs($user)
            ->post(route('molds.store'), [
                'client_id' => $client->id,
                'factory_id' => $factory->id,
                'client_offer_price' => '1000',
                'factory_offer_price' => '800',
                'due_on' => '2026-11-01',
                'contact_name' => 'Depo',
            ])
            ->assertSessionHasErrors([
                'number' => 'Kalıp numarası zorunludur.',
                'factory_approval' => 'Fabrika PDF dosyası yüklenmelidir.',
            ]);

        $this->assertSame(0, Mold::query()->count());
    }

    public function test_archiving_hides_a_mold_until_it_is_restored(): void
    {
        $user = $this->manager();
        $mold = Mold::factory()->create(['number' => '12-34']);

        $this->actingAs($user)
            ->post(route('molds.archive', $mold))
            ->assertRedirect(route('molds.index'));

        $this->assertNotNull($mold->refresh()->archived_at);

        $this->actingAs($user)
            ->get(route('molds.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('molds.data', 0));

        $this->actingAs($user)
            ->get(route('molds.archived'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('molds.data.0.number', '12-34'));

        $this->actingAs($user)
            ->post(route('molds.unarchive', $mold))
            ->assertRedirect(route('molds.archived'));

        $this->assertNull($mold->refresh()->archived_at);
    }

    public function test_delete_soft_deletes_the_mold(): void
    {
        $user = $this->manager();
        $mold = Mold::factory()->create();

        $this->actingAs($user)
            ->delete(route('molds.destroy', $mold))
            ->assertRedirect(route('molds.index'));

        $this->assertSoftDeleted($mold);
    }

    public function test_product_update_stores_the_mold_number_for_its_factory(): void
    {
        Permission::findOrCreate('stock.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('stock.manage');
        $factory = Factory::factory()->create();
        $product = Product::factory()->create([
            'factory_id' => $factory->id,
            'name' => 'Profil 40',
        ]);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'category_id' => $product->category_id,
                'name' => 'Profil 40',
                'is_active' => true,
                'mold_number' => 'K-19',
            ])
            ->assertRedirect();

        $this->assertSame('K-19', MoldNumber::query()
            ->where('product_id', $product->id)
            ->where('factory_id', $factory->id)
            ->value('number'));
    }

    public function test_factory_order_print_shows_the_mold_number(): void
    {
        foreach (['factory_orders.view', 'factory_orders.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['factory_orders.view', 'factory_orders.manage']);
        $factory = Factory::factory()->create();
        $product = Product::factory()->create(['factory_id' => $factory->id]);
        $form = FactoryOrderForm::factory()->create(['factory_id' => $factory->id]);
        FactoryOrder::factory()->create([
            'factory_id' => $factory->id,
            'product_id' => $product->id,
            'factory_order_form_id' => $form->id,
            'product_name' => 'Profil 40',
        ]);
        MoldNumber::factory()->create([
            'product_id' => $product->id,
            'factory_id' => $factory->id,
            'number' => 'K-19',
        ]);

        $this->actingAs($user)
            ->get(route('factory-order-forms.show', $form))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('form.lines.0.mold_number', 'K-19'));
    }

    public function test_legacy_import_keeps_ids_prices_and_blank_dates(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $factory = Factory::factory()->create();
        $product = Product::factory()->create();
        $directory = storage_path('framework/testing/molds-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/molds.csv', implode("\n", [
            'id,client_id,number,factory_id,client_offer_price,factory_offer_price,due_date,factory_pdf,client_pdf,contract_pdf,contact_person,description,company_id,is_archived,is_deleted,created_by',
            "7,{$client->id},12-34,{$factory->id},1000 EURO + KDV,800,0000-00-00,fabrika.pdf,,,Depo,,{$company->id},1,0,0",
            "8,{$client->id},KD-1001,{$factory->id},900,700,2025-05-01,fabrika-2.pdf,,,Depo,,{$company->id},0,0,{$user->id}",
            "9,{$client->id},99,999999,1,1,2025-05-01,yok.pdf,,,Depo,,{$company->id},0,0,0",
        ]));
        File::put($directory.'/mold_numbers.csv', implode("\n", [
            'id,number,product_id,factory_id',
            "1,K-19,{$product->id},{$factory->id}",
            "2,K-2,999999,{$factory->id}",
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'molds',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'molds',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $archived = Mold::query()->find(7);
        $open = Mold::query()->find(8);

        $this->assertNotNull($archived);
        $this->assertNotNull($archived->archived_at);
        $this->assertNull($archived->due_on);
        $this->assertNull($archived->created_by_user_id);
        $this->assertSame($company->id, $archived->company_id);
        $this->assertSame('1000 EURO + KDV', $archived->client_offer_price);
        $this->assertSame('fabrika.pdf', $archived->media()->where('collection', MoldDocument::FactoryApproval->value)->value('file_name'));
        $this->assertNull($archived->media()->where('collection', MoldDocument::FactoryApproval->value)->value('path'));
        $this->assertNull(Mold::withTrashed()->find(9));

        $this->assertNotNull($open);
        $this->assertNull($open->archived_at);
        $this->assertSame($user->id, $open->created_by_user_id);
        $this->assertSame('2025-05-01', $open->due_on?->toDateString());
        $this->assertSame(2, Mold::query()->count());

        $this->assertSame('K-19', MoldNumber::query()->find(1)?->number);
        $this->assertNull(MoldNumber::query()->find(2));
        $this->assertSame(1, MoldNumber::query()->count());
    }
}
