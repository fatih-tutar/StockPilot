<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreFactoryRequest;
use App\Http\Requests\Catalog\UpdateFactoryRequest;
use App\Models\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FactoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Factory::class);

        $search = $request->string('search')->trim()->toString();

        $factories = Factory::query()
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
            ->through(fn (Factory $factory) => [
                'id' => $factory->id,
                'name' => $factory->name,
                'phone' => $factory->phone,
                'email' => $factory->email,
                'labor_cost' => $factory->labor_cost,
                'is_active' => $factory->is_active,
            ]);

        return Inertia::render('Factories/Index', [
            'factories' => $factories,
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->can('factories.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Factory::class);

        return Inertia::render('Factories/Form', [
            'factory' => null,
        ]);
    }

    public function store(StoreFactoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['labor_cost'] = $data['labor_cost'] ?? 0;
        $data['fine_labor_cost'] = $data['fine_labor_cost'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        $factory = Factory::query()->create($data);

        return redirect()
            ->route('factories.edit', $factory)
            ->with('success', 'Fabrika oluşturuldu.');
    }

    public function edit(Request $request, Factory $factory): Response
    {
        $this->authorize('view', $factory);

        return Inertia::render('Factories/Form', [
            'factory' => [
                'id' => $factory->id,
                'name' => $factory->name,
                'phone' => $factory->phone,
                'email' => $factory->email,
                'address' => $factory->address,
                'labor_cost' => $factory->labor_cost,
                'fine_labor_cost' => $factory->fine_labor_cost,
                'is_active' => $factory->is_active,
            ],
            'canManage' => $request->user()->can('factories.manage'),
        ]);
    }

    public function update(UpdateFactoryRequest $request, Factory $factory): RedirectResponse
    {
        $data = $request->validated();
        $data['labor_cost'] = $data['labor_cost'] ?? 0;
        $data['fine_labor_cost'] = $data['fine_labor_cost'] ?? 0;

        $factory->update($data);

        return redirect()
            ->route('factories.edit', $factory)
            ->with('success', 'Fabrika güncellendi.');
    }

    public function destroy(Factory $factory): RedirectResponse
    {
        $this->authorize('delete', $factory);

        $factory->delete();

        return redirect()
            ->route('factories.index')
            ->with('success', 'Fabrika silindi.');
    }
}
