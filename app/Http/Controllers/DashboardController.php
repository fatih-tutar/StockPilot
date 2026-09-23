<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Enums\ShipmentStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Shipment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $canViewStock = $user->can('stock.view') || $user->can('stock.manage');
        $canViewClients = $user->can('clients.view') || $user->can('clients.manage');
        $canViewQuotes = $user->can('quotes.view') || $user->can('quotes.manage');
        $canViewShipments = $user->can('shipments.view') || $user->can('shipments.manage');

        $stats = [
            'low_stock_count' => $canViewStock ? $this->lowStockQuery()->count() : null,
            'active_clients_count' => $canViewClients
                ? Client::query()->where('is_active', true)->count()
                : null,
            'open_quotes_count' => $canViewQuotes
                ? Quote::query()->whereIn('status', [
                    QuoteStatus::Draft,
                    QuoteStatus::Sent,
                ])->count()
                : null,
            'active_shipments_count' => $canViewShipments
                ? Shipment::query()->whereIn('status', [
                    ShipmentStatus::Scheduled,
                    ShipmentStatus::InTransit,
                ])->count()
                : null,
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'lowStockProducts' => $canViewStock
                ? $this->lowStockQuery()
                    ->orderBy('quantity_piece')
                    ->limit(8)
                    ->get(['id', 'name', 'sku', 'quantity_piece', 'quantity_pallet', 'low_stock_threshold'])
                    ->map(fn (Product $product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'quantity_piece' => $product->quantity_piece,
                        'quantity_pallet' => $product->quantity_pallet,
                        'low_stock_threshold' => $product->low_stock_threshold,
                    ])
                    ->values()
                : [],
            'openQuotes' => $canViewQuotes
                ? Quote::query()
                    ->with('client:id,name')
                    ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
                    ->latest('quote_date')
                    ->latest('id')
                    ->limit(8)
                    ->get()
                    ->map(fn (Quote $quote) => [
                        'id' => $quote->id,
                        'number' => $quote->number,
                        'status' => $quote->status->value,
                        'status_label' => $quote->status->label(),
                        'quote_date' => $quote->quote_date?->toDateString(),
                        'total' => (float) $quote->total,
                        'currency' => $quote->currency,
                        'client_name' => $quote->client?->name,
                    ])
                    ->values()
                : [],
            'activeShipments' => $canViewShipments
                ? Shipment::query()
                    ->with('client:id,name')
                    ->whereIn('status', [ShipmentStatus::Scheduled, ShipmentStatus::InTransit])
                    ->orderBy('ship_date')
                    ->limit(8)
                    ->get()
                    ->map(fn (Shipment $shipment) => [
                        'id' => $shipment->id,
                        'number' => $shipment->number,
                        'status' => $shipment->status->value,
                        'status_label' => $shipment->status->label(),
                        'ship_date' => $shipment->ship_date?->toDateString(),
                        'vehicle_plate' => $shipment->vehicle_plate,
                        'client_name' => $shipment->client?->name,
                    ])
                    ->values()
                : [],
            'recentMovements' => $canViewStock
                ? StockMovement::query()
                    ->with(['product:id,name', 'user:id,name'])
                    ->latest('id')
                    ->limit(8)
                    ->get()
                    ->map(fn (StockMovement $movement) => [
                        'id' => $movement->id,
                        'type' => $movement->type,
                        'quantity_piece_delta' => $movement->quantity_piece_delta,
                        'quantity_pallet_delta' => $movement->quantity_pallet_delta,
                        'note' => $movement->note,
                        'created_at' => $movement->created_at?->toDateTimeString(),
                        'product_name' => $movement->product?->name,
                        'user_name' => $movement->user?->name,
                    ])
                    ->values()
                : [],
            'can' => [
                'stock' => $canViewStock,
                'clients' => $canViewClients,
                'quotes' => $canViewQuotes,
                'shipments' => $canViewShipments,
            ],
        ]);
    }

    private function lowStockQuery()
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('quantity_piece', '<=', 'low_stock_threshold');
    }
}
