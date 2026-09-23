<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreClientRequest;
use App\Http\Requests\Catalog\UpdateClientRequest;
use App\Models\Client;
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
                        ->orWhere('email', 'like', "%{$search}%");
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

        return Inertia::render('Clients/Form', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'address' => $client->address,
                'notes' => $client->notes,
                'is_active' => $client->is_active,
            ],
            'canManage' => $request->user()->can('clients.manage'),
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
