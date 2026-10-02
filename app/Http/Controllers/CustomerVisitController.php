<?php

namespace App\Http\Controllers;

use App\Http\Requests\Visits\StoreCustomerVisitRequest;
use App\Http\Requests\Visits\UpdateCustomerVisitRequest;
use App\Models\CustomerVisit;
use App\Models\CustomerVisitCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerVisitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerVisit::class);

        $search = $request->string('search')->trim()->toString();
        $city = $request->string('city')->trim()->toString();
        $district = $request->string('district')->trim()->toString();
        $categoryId = $request->integer('category');

        $visits = CustomerVisit::query()
            ->with('category:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($city !== '', fn ($query) => $query->where('city', $city))
            ->when($district !== '', fn ($query) => $query->where('district', $district))
            ->when($categoryId > 0, fn ($query) => $query->where('customer_visit_category_id', $categoryId))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CustomerVisit $visit) => $this->visitPayload($visit));

        return Inertia::render('CustomerVisits/Index', [
            'visits' => $visits,
            'categories' => $this->categoryOptions(),
            'cities' => CustomerVisit::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'districts' => CustomerVisit::query()->whereNotNull('district')->distinct()->orderBy('district')->pluck('district'),
            'filters' => [
                'search' => $search,
                'city' => $city,
                'district' => $district,
                'category' => $categoryId > 0 ? $categoryId : '',
            ],
            'canManage' => $request->user()->can('visits.manage'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CustomerVisit::class);

        return Inertia::render('CustomerVisits/Form', [
            'visit' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreCustomerVisitRequest $request): RedirectResponse
    {
        $visit = CustomerVisit::query()->create($request->validated());

        return redirect()
            ->route('customer-visits.edit', $visit)
            ->with('success', 'Ziyaret kaydedildi.');
    }

    public function edit(Request $request, CustomerVisit $customerVisit): Response
    {
        $this->authorize('view', $customerVisit);

        $customerVisit->load('category:id,name');

        return Inertia::render('CustomerVisits/Form', [
            'visit' => $this->visitPayload($customerVisit),
            'categories' => $this->categoryOptions(),
            'canManage' => $request->user()->can('visits.manage'),
        ]);
    }

    public function update(UpdateCustomerVisitRequest $request, CustomerVisit $customerVisit): RedirectResponse
    {
        $customerVisit->update($request->validated());

        return redirect()
            ->route('customer-visits.edit', $customerVisit)
            ->with('success', 'Ziyaret güncellendi.');
    }

    public function destroy(CustomerVisit $customerVisit): RedirectResponse
    {
        $this->authorize('delete', $customerVisit);

        $customerVisit->delete();

        return redirect()
            ->route('customer-visits.index')
            ->with('success', 'Ziyaret silindi.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerVisit::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'İş kolu adı zorunludur.',
        ]);

        CustomerVisitCategory::query()->create($data);

        return redirect()
            ->route('customer-visits.index')
            ->with('success', 'İş kolu eklendi.');
    }

    public function updateCategory(Request $request, CustomerVisitCategory $customerVisitCategory): RedirectResponse
    {
        $this->authorize('create', CustomerVisit::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'İş kolu adı zorunludur.',
        ]);

        $customerVisitCategory->update($data);

        return redirect()
            ->route('customer-visits.index')
            ->with('success', 'İş kolu güncellendi.');
    }

    public function destroyCategory(CustomerVisitCategory $customerVisitCategory): RedirectResponse
    {
        $this->authorize('create', CustomerVisit::class);

        $customerVisitCategory->delete();

        return redirect()
            ->route('customer-visits.index')
            ->with('success', 'İş kolu silindi.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return CustomerVisitCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CustomerVisitCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function visitPayload(CustomerVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'city' => $visit->city,
            'district' => $visit->district,
            'customer_visit_category_id' => $visit->customer_visit_category_id,
            'category_name' => $visit->category?->name,
            'customer_name' => $visit->customer_name,
            'contact_name' => $visit->contact_name,
            'phone' => $visit->phone,
            'visited_on' => $visit->visited_on?->toDateString(),
            'planned_on' => $visit->planned_on?->toDateString(),
            'address' => $visit->address,
            'notes' => $visit->notes,
        ];
    }
}
