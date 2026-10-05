<?php

namespace Tests\Feature;

use App\Enums\CatalogImage;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CatalogItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['catalog.view', 'catalog.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create([
            'company_id' => $companyId,
        ]);
        $user->givePermissionTo(['catalog.view', 'catalog.manage']);

        return $user;
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function photos(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return [
            CatalogImage::Primary->value => UploadedFile::fake()->createWithContent('profil.png', $png),
            CatalogImage::Secondary->value => UploadedFile::fake()->createWithContent('kesit.png', $png),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'product_code' => 'LED-1',
            'description' => 'Koridor',
            'lines' => [
                [
                    'code' => 'A1',
                    'model' => 'Sıva üstü',
                    'quantity' => '6 METRE',
                    'price' => '120 TL',
                ],
                [
                    'code' => 'A2',
                    'model' => 'Sarkıt',
                    'quantity' => 'adet',
                    'price' => '',
                ],
            ],
            ...$this->photos(),
            ...$overrides,
        ];
    }

    public function test_catalog_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('catalog-items.index'))
            ->assertForbidden();
    }

    public function test_store_uses_the_session_company_and_keeps_the_photos(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->manager($company->id);

        $this->actingAs($user)
            ->post(route('catalog-items.store'), $this->payload([
                'company_id' => $other->id,
            ]))
            ->assertRedirect(route('catalog-items.index'));

        $items = CatalogItem::query()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertTrue($items->every(fn (CatalogItem $item): bool => $item->company_id === $company->id));
        $this->assertSame('LED-1', $items[0]->product_code);
        $this->assertSame('6 METRE', $items[0]->quantity);
        $this->assertSame('120 TL', $items[0]->price);
        $this->assertNull($items[1]->price);
        $this->assertSame($items[0]->sort_order, $items[1]->sort_order);
        $this->assertCount(2, $items[0]->media);
        $this->assertTrue($items[0]->media->every(fn (Media $media): bool => $media->isStored()));
        $this->assertTrue($items[1]->media->every(fn (Media $media): bool => $media->isStored()));
    }

    public function test_a_new_group_requires_the_product_code_model_and_photos(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->post(route('catalog-items.store'), [
                'lines' => [
                    ['code' => 'A1', 'quantity' => '6 METRE', 'price' => '120 TL'],
                ],
            ])
            ->assertSessionHasErrors([
                'product_code' => 'Ürün no zorunludur.',
                'lines.0.model' => 'Model zorunludur.',
                'image_1' => 'Birinci fotoğraf yüklenmelidir.',
                'image_2' => 'İkinci fotoğraf yüklenmelidir.',
            ]);

        $this->assertSame(0, CatalogItem::query()->count());
    }

    public function test_a_viewer_cannot_add_a_price(): void
    {
        Permission::findOrCreate('catalog.view');
        $user = User::factory()->create();
        $user->givePermissionTo('catalog.view');

        $this->actingAs($user)
            ->post(route('catalog-items.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, CatalogItem::query()->count());
    }

    public function test_update_rejects_a_line_from_another_product(): void
    {
        $user = $this->manager();
        $item = CatalogItem::factory()->create([
            'product_code' => 'LED-1',
            'model' => 'Sıva üstü',
        ]);
        $other = CatalogItem::factory()->create([
            'product_code' => 'LED-2',
            'model' => 'Sarkıt',
            'price' => '50 TL',
        ]);

        $this->actingAs($user)
            ->put(route('catalog-items.update', $item), [
                'product_code' => 'LED-1',
                'lines' => [
                    [
                        'id' => $other->id,
                        'model' => 'Değişti',
                        'price' => '1 TL',
                    ],
                ],
            ])
            ->assertSessionHasErrors([
                'lines' => 'Satır bu ürüne ait değil.',
            ]);

        $this->assertSame('Sarkıt', $other->fresh()->model);
        $this->assertSame('50 TL', $other->fresh()->price);
    }

    public function test_store_rejects_a_product_code_already_on_the_list(): void
    {
        Storage::fake('local');
        $user = $this->manager();
        CatalogItem::factory()->create(['product_code' => 'LED-1']);

        $this->actingAs($user)
            ->post(route('catalog-items.store'), $this->payload())
            ->assertSessionHasErrors([
                'product_code' => 'Bu ürün no zaten listede.',
            ]);
    }

    public function test_deleting_a_line_hides_only_that_line(): void
    {
        $user = $this->manager();
        $kept = CatalogItem::factory()->create(['product_code' => 'LED-1', 'model' => 'Sarkıt']);
        $removed = CatalogItem::factory()->create(['product_code' => 'LED-1', 'model' => 'Sıva üstü']);

        $this->actingAs($user)
            ->delete(route('catalog-items.destroy', $removed))
            ->assertRedirect(route('catalog-items.index'));

        $this->assertNotNull(CatalogItem::query()->find($kept->id));
        $this->assertNull(CatalogItem::query()->find($removed->id));
        $this->assertNotNull(CatalogItem::withTrashed()->find($removed->id));
    }

    public function test_deleting_a_group_hides_every_line_of_that_product(): void
    {
        $user = $this->manager();
        $first = CatalogItem::factory()->create(['product_code' => 'LED-1']);
        CatalogItem::factory()->create(['product_code' => 'LED-1']);
        $other = CatalogItem::factory()->create(['product_code' => 'LED-2']);

        $this->actingAs($user)
            ->delete(route('catalog-items.group.destroy', $first))
            ->assertRedirect(route('catalog-items.index'));

        $this->assertSame(1, CatalogItem::query()->count());
        $this->assertNotNull(CatalogItem::query()->find($other->id));
        $this->assertSame(2, CatalogItem::onlyTrashed()->count());
    }

    public function test_moving_a_group_swaps_its_place(): void
    {
        $user = $this->manager();
        $first = CatalogItem::factory()->create(['product_code' => 'LED-1', 'sort_order' => 1]);
        $second = CatalogItem::factory()->create(['product_code' => 'LED-2', 'sort_order' => 2]);

        $this->actingAs($user)
            ->post(route('catalog-items.move', $second), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
    }

    public function test_print_hides_prices_when_the_company_list_is_closed(): void
    {
        $company = Company::factory()->create(['price_list_visible' => false]);
        $user = $this->manager($company->id);
        CatalogItem::factory()->create([
            'product_code' => 'LED-1',
            'price' => '120 TL',
        ]);

        $this->actingAs($user)
            ->get(route('catalog-items.print'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pricesVisible', false)
                ->where('groups.0.lines.0.price', null));
    }

    public function test_the_company_can_hide_prices_on_the_printed_list(): void
    {
        $company = Company::factory()->create(['price_list_visible' => true]);
        $user = $this->manager($company->id);

        $this->actingAs($user)
            ->post(route('catalog-items.visibility'))
            ->assertRedirect();

        $this->assertFalse($company->fresh()->price_list_visible);
    }

    public function test_updating_a_group_adds_a_line_and_keeps_the_photos(): void
    {
        Storage::fake('local');
        $user = $this->manager();
        $item = CatalogItem::factory()->create([
            'product_code' => 'LED-1',
            'code' => 'A1',
            'model' => 'Sıva üstü',
            'price' => '100 TL',
            'sort_order' => 4,
        ]);
        $item->media()->create([
            'collection' => CatalogImage::Primary->value,
            'disk' => 'local',
            'path' => 'catalog-items/'.$item->id.'/bir.png',
            'file_name' => 'bir.png',
        ]);
        Storage::disk('local')->put('catalog-items/'.$item->id.'/bir.png', 'png');

        $this->actingAs($user)
            ->put(route('catalog-items.update', $item), [
                'product_code' => 'LED-1',
                'description' => 'Koridor',
                'lines' => [
                    [
                        'id' => $item->id,
                        'code' => 'A1',
                        'model' => 'Sıva üstü',
                        'quantity' => '6 METRE',
                        'price' => '120 TL',
                    ],
                    [
                        'code' => 'A2',
                        'model' => 'Sarkıt',
                        'quantity' => 'adet',
                        'price' => '90 TL',
                    ],
                ],
            ])
            ->assertRedirect(route('catalog-items.index'));

        $items = CatalogItem::query()->where('product_code', 'LED-1')->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame('120 TL', $items[0]->price);
        $this->assertSame(4, $items[1]->sort_order);
        $this->assertSame('bir.png', $items[1]->media()->where('collection', CatalogImage::Primary->value)->value('file_name'));
        $this->assertTrue($items[1]->media()->first()->isStored());
    }

    public function test_legacy_import_keeps_ids_prices_and_image_names(): void
    {
        $directory = storage_path('framework/testing/catalog-import');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/catalog.csv', implode("\n", [
            'id,product,code,model,quantity,price,image_1,image_2,description,created_at,is_deleted,sort_order',
            '15,LED-1,A1,Sıva üstü,6 METRE,120 TL,bir.jpg,iki.png,Koridor,1609459200,0,3',
            '16,LED-1,A2,Sarkıt,adet,90 TL,bir.jpg,iki.png,Koridor,1609459200,0,3',
            '17,ESKI,B1,Eski model,1,10 TL,eski.jpg,eski2.png,Eski,1609459200,1,9',
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'catalog',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'catalog',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $first = CatalogItem::query()->find(15);
        $second = CatalogItem::query()->find(16);
        $removed = CatalogItem::withTrashed()->find(17);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNull($first->company_id);
        $this->assertSame('120 TL', $first->price);
        $this->assertSame('6 METRE', $first->quantity);
        $this->assertSame('2021-01-01 00:00:00', $first->created_at?->format('Y-m-d H:i:s'));
        $this->assertSame(3, $first->sort_order);
        $this->assertSame('bir.jpg', $first->media()->where('collection', CatalogImage::Primary->value)->value('file_name'));
        $this->assertNull($first->media()->where('collection', CatalogImage::Primary->value)->value('path'));
        $this->assertSame(2, CatalogItem::query()->count());
        $this->assertNotNull($removed);
        $this->assertNotNull($removed->deleted_at);
        $this->assertSame(3, CatalogItem::withTrashed()->count());
    }
}
