<?php

namespace Tests\Feature;

use App\Enums\OfferListStatus;
use App\Models\OfferListEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfferListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithQuotePermission(bool $manage = true): User
    {
        foreach (['quotes.view', 'quotes.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($manage ? ['quotes.view', 'quotes.manage'] : ['quotes.view']);

        return $user;
    }

    public function test_guest_is_redirected_from_the_offer_list(): void
    {
        $this->get(route('offer-lists.index'))
            ->assertRedirect(route('login'));
    }

    public function test_offer_list_requires_quote_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('offer-lists.index'))
            ->assertForbidden();
    }

    public function test_open_list_shows_the_typed_customer_and_hides_archived_rows(): void
    {
        $user = $this->userWithQuotePermission();

        OfferListEntry::factory()->create([
            'customer_name' => 'Elle Yazılan Firma',
            'status' => OfferListStatus::Open,
            'offered_by_user_id' => $user->id,
        ]);
        OfferListEntry::factory()->create([
            'customer_name' => 'Arşivdeki Firma',
            'status' => OfferListStatus::ArchivedPositive,
        ]);
        OfferListEntry::factory()->create([
            'customer_name' => 'Silinmiş Firma',
            'status' => OfferListStatus::Removed,
        ]);

        $this->actingAs($user)
            ->get(route('offer-lists.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OfferLists/Index')
                ->has('entries.data', 1)
                ->where('entries.data.0.customer_name', 'Elle Yazılan Firma')
                ->where('archived', false));

        $entry = OfferListEntry::query()->where('customer_name', 'Elle Yazılan Firma')->first();

        $this->actingAs($user)
            ->get(route('offer-lists.edit', $entry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OfferLists/Form')
                ->where('entry.customer_name', 'Elle Yazılan Firma'));
    }

    public function test_archive_shows_positive_and_negative_rows(): void
    {
        $user = $this->userWithQuotePermission(manage: false);

        OfferListEntry::factory()->create([
            'customer_name' => 'Olumlu',
            'status' => OfferListStatus::ArchivedPositive,
        ]);
        OfferListEntry::factory()->create([
            'customer_name' => 'Olumsuz',
            'status' => OfferListStatus::ArchivedNegative,
        ]);
        OfferListEntry::factory()->create([
            'customer_name' => 'Açık',
            'status' => OfferListStatus::Open,
        ]);

        $this->actingAs($user)
            ->get(route('offer-lists.archive'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('OfferLists/Index')
                ->has('entries.data', 2)
                ->where('archived', true)
                ->where('canManage', false));
    }

    public function test_a_typed_customer_name_can_be_stored(): void
    {
        $user = $this->userWithQuotePermission();

        $this->actingAs($user)
            ->post(route('offer-lists.store'), [
                'customer_name' => 'Serbest Müşteri',
                'contact_name' => 'Ayşe',
                'product_quantity' => '100 boy profil',
                'price' => '250',
                'factory_name' => 'Profilhane',
                'factory_price' => '',
                'notes' => 'Not',
                'offered_on' => '2021-02-01',
            ])
            ->assertRedirect();

        $entry = OfferListEntry::query()->first();

        $this->assertNotNull($entry);
        $this->assertSame('Serbest Müşteri', $entry->customer_name);
        $this->assertNull($entry->factory_price);
        $this->assertSame($user->id, $entry->offered_by_user_id);
        $this->assertSame(OfferListStatus::Open, $entry->status);
    }

    public function test_store_requires_a_customer_name(): void
    {
        $user = $this->userWithQuotePermission();

        $this->actingAs($user)
            ->from(route('offer-lists.create'))
            ->post(route('offer-lists.store'), [
                'customer_name' => '',
                'offered_on' => '2021-02-01',
            ])
            ->assertRedirect(route('offer-lists.create'))
            ->assertSessionHasErrors([
                'customer_name' => 'Müşteri adı zorunludur.',
            ]);
    }

    public function test_viewer_cannot_store_an_offer_list_row(): void
    {
        $user = $this->userWithQuotePermission(manage: false);

        $this->actingAs($user)
            ->post(route('offer-lists.store'), [
                'customer_name' => 'Serbest Müşteri',
                'offered_on' => '2021-02-01',
            ])
            ->assertForbidden();
    }

    public function test_archiving_moves_the_row_off_the_open_list(): void
    {
        $user = $this->userWithQuotePermission();
        $entry = OfferListEntry::factory()->create([
            'customer_name' => 'Taşınacak',
            'status' => OfferListStatus::Open,
        ]);

        $this->actingAs($user)
            ->post(route('offer-lists.archive-positive', $entry))
            ->assertRedirect(route('offer-lists.archive'));

        $this->assertSame(OfferListStatus::ArchivedPositive, $entry->refresh()->status);

        $this->actingAs($user)
            ->post(route('offer-lists.restore', $entry))
            ->assertRedirect(route('offer-lists.index'));

        $this->assertSame(OfferListStatus::Open, $entry->refresh()->status);
    }

    public function test_legacy_import_keeps_the_customer_text_without_a_client_match(): void
    {
        $user = User::factory()->create();
        $directory = storage_path('framework/testing/offer-list-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/teklif_listesi.csv', implode("\n", [
            'teklifid,musteri,ilgilikisi,urunmiktar,fiyat,fabrika,fabrikafiyat,teklifveren,aciklama,tarih,silik',
            "7,Elle Yazılan Firma,Ahmet,100 boy,250,Profilhane,200,{$user->id},Arşiv notu,1609459200,1",
            '8,Olmayan Firma,Veli,50 boy,81-83,,,999999,Not,1609545600,0',
            '9,Silinen,Ali,,,,,,,1609632000,3',
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'offer-lists',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'offer-lists',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $this->assertSame(3, OfferListEntry::query()->count());

        $archived = OfferListEntry::query()->find(7);
        $this->assertNotNull($archived);
        $this->assertSame('Elle Yazılan Firma', $archived->customer_name);
        $this->assertSame('100 boy', $archived->product_quantity);
        $this->assertSame('250', $archived->price);
        $this->assertSame($user->id, $archived->offered_by_user_id);
        $this->assertSame(OfferListStatus::ArchivedPositive, $archived->status);

        $open = OfferListEntry::query()->find(8);
        $this->assertNotNull($open);
        $this->assertSame('Olmayan Firma', $open->customer_name);
        $this->assertSame('81-83', $open->price);
        $this->assertNull($open->offered_by_user_id);
        $this->assertSame(OfferListStatus::Open, $open->status);

        $removed = OfferListEntry::query()->find(9);
        $this->assertNotNull($removed);
        $this->assertSame(OfferListStatus::Removed, $removed->status);
    }
}
