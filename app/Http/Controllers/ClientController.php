<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreClientRequest;
use App\Http\Requests\Catalog\UpdateClientRequest;
use App\Models\Client;
use App\Models\CustomOrder;
use App\Models\Mold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $search = $request->string('search')->trim()->toString();

        $clients = Client::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->address,
                'is_active' => $client->is_active,
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('clients.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Form', [
            'client' => null,
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $client = Client::query()->create($data);

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Müşteri oluşturuldu.');
    }

    public function edit(Request $request, Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load(['customOrders.items:id,custom_order_id,product_name,due_on']);
        $canViewMolds = $request->user()->can('viewAny', Mold::class);
        if ($canViewMolds) {
            $client->load(['molds.sourceFactory:id,name']);
        }

        return Inertia::render('Clients/Form', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->address,
                'notes' => $client->notes,
                'is_active' => $client->is_active,
                'molds' => $canViewMolds
                    ? $client->molds->map(fn (Mold $mold) => [
                        'id' => $mold->id,
                        'number' => $mold->number,
                        'factory_name' => $mold->sourceFactory?->name,
                        'due_on' => $mold->due_on?->format('d.m.Y'),
                        'archived' => $mold->archived_at !== null,
                    ])->all()
                    : [],
                'custom_orders' => $client->customOrders->map(fn (CustomOrder $order) => [
                    'id' => $order->id,
                    'status_label' => $order->status->label(),
                    'delivery_label' => $order->delivery_method->label(),
                    'ordered_on' => $order->ordered_at?->toDateString(),
                    'notes' => $order->notes,
                    'items' => $order->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'due_on' => $item->due_on?->toDateString(),
                    ])->all(),
                ])->all(),
            ],
            'canManage' => $request->user()->can('clients.manage'),
            'canViewMolds' => $canViewMolds,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.edit', $client)
            ->with('success', 'Müşteri güncellendi.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', 'Müşteri silindi.');
    }
}
