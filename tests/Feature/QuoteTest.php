<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function userWithQuotesPermission(): User
    {
        foreach (['quotes.view', 'quotes.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.view', 'quotes.manage']);

        return $user;
    }

    public function test_quotes_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('quotes.index'))
            ->assertForbidden();
    }

    public function test_quotes_index_is_displayed(): void
    {
        $user = $this->userWithQuotesPermission();
        $client = Client::factory()->create();

        Quote::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'number' => 'Q-2026-0099',
        ]);

        $this->actingAs($user)
            ->get(route('quotes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotes/Index')
                ->has('quotes.data', 1)
                ->where('quotes.data.0.number', 'Q-2026-0099'));
    }

    public function test_quote_can_be_created_with_items_and_totals(): void
    {
        $user = $this->userWithQuotesPermission();
        $client = Client::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('quotes.store'), [
            'client_id' => $client->id,
            'status' => QuoteStatus::Draft->value,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'currency' => 'TRY',
            'tax_rate' => 20,
            'notes' => 'Test teklif',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity_piece' => 10,
                    'quantity_pallet' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $quote = Quote::query()->first();

        $this->assertNotNull($quote);
        $response->assertRedirect(route('quotes.edit', $quote));

        $this->assertSame(1000.0, (float) $quote->subtotal);
        $this->assertSame(200.0, (float) $quote->tax_amount);
        $this->assertSame(1200.0, (float) $quote->total);
        $this->assertCount(1, $quote->items);
    }

    public function test_legacy_import_keeps_quote_forms_loose_lines_and_named_offers(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $client = Client::factory()->create(['name' => 'Deneme Metal']);
        $product = Product::factory()->create(['name' => 'Profil 40']);
        $directory = storage_path('framework/testing/quotes-import');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/teklif.csv', implode("\n", [
            'teklifid,turunid,tverilenfirma,tadet,tsatisfiyati,unit_weight,tsaniye,formda,sirketid,silik',
            "20,{$product->id},{$client->id},4,10,1.5,1609459200,1,{$company->id},0",
            "21,{$product->id},{$client->id},2,5,1,1609459200,1,{$company->id},0",
            "22,{$product->id},{$client->id},3,8,1,1609459300,0,{$company->id},0",
            "23,{$product->id},999999,1,8,1,1609459300,0,{$company->id},0",
            "24,{$product->id},{$client->id},1,12,1,1609459400,1,{$company->id},1",
        ]));
        File::put($directory.'/teklifformlari.csv', implode("\n", [
            'tformid,tekliflistesi,withholding,explanation,firmaid,saniye,sirketid,silik',
            "10,\"20,21\",1,Kesim notu,{$client->id},1609459200,{$company->id},0",
            "12,21,0,,{$client->id},1609459250,{$company->id},0",
            "11,24,0,,{$client->id},1609459400,{$company->id},1",
            "13,20,0,,999999,1609459200,{$company->id},0",
        ]));
        File::put($directory.'/teklif_listesi.csv', implode("\n", [
            'teklifid,musteri,ilgilikisi,urunmiktar,fiyat,fabrika,fabrikafiyat,teklifveren,aciklama,tarih,silik',
            '7,deneme metal,Ahmet,100 boy,250,Profilhane,200,Depo,Arşiv notu,2021-02-01,1',
            '8,Olmayan Firma,,,,0,,,2021-02-02,0',
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'quotes',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'quotes',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $form = Quote::withTrashed()->with('items')->find(10);
        $this->assertNotNull($form);
        $this->assertSame('F-10', $form->number);
        $this->assertSame($client->id, $form->client_id);
        $this->assertSame($user->id, $form->user_id);
        $this->assertSame($company->id, $form->company_id);
        $this->assertSame(QuoteStatus::Sent, $form->status);
        $this->assertSame('6.00', $form->tax_rate);
        $this->assertSame('50.00', $form->subtotal);
        $this->assertSame('3.00', $form->tax_amount);
        $this->assertSame('53.00', $form->total);
        $this->assertSame('Kesim notu', $form->notes);
        $this->assertSame('2021-01-01', $form->quote_date->toDateString());
        $this->assertCount(2, $form->items);
        $this->assertSame($product->id, $form->items[0]->product_id);
        $this->assertSame(4, $form->items[0]->quantity_piece);
        $this->assertSame('10.00', $form->items[0]->unit_price);

        $laterForm = Quote::query()->with('items')->find(12);
        $this->assertNotNull($laterForm);
        $this->assertCount(0, $laterForm->items);

        $removed = Quote::withTrashed()->find(11);
        $this->assertNotNull($removed);
        $this->assertSame(QuoteStatus::Cancelled, $removed->status);
        $this->assertSoftDeleted($removed);
        $this->assertNull(Quote::withTrashed()->where('number', 'F-13')->first());

        $loose = Quote::query()->where('number', 'L-22')->with('items')->first();
        $this->assertNotNull($loose);
        $this->assertSame(QuoteStatus::Draft, $loose->status);
        $this->assertSame('24.00', $loose->subtotal);
        $this->assertSame('4.80', $loose->tax_amount);
        $this->assertNull(Quote::withTrashed()->where('number', 'L-23')->first());

        $named = Quote::query()->where('number', 'TL-7')->with('items')->first();
        $this->assertNotNull($named);
        $this->assertSame(QuoteStatus::Accepted, $named->status);
        $this->assertSame($client->id, $named->client_id);
        $this->assertSame('250.00', $named->subtotal);
        $this->assertSame('100 boy', $named->items->first()->description);
        $this->assertNull(Quote::withTrashed()->where('number', 'TL-8')->first());
        $this->assertSame(5, Quote::withTrashed()->count());
    }
}
