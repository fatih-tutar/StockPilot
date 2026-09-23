<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
