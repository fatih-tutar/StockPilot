<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Enums\UserAccessLevel;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Http\Requests\Leave\UpdateLeaveRequest;
use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveRules;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Leave::class);

        $actor = $request->user();
        $year = (int) $request->integer('year', today()->year);
        if ($year < 2019 || $year > today()->year + 1) {
            $year = today()->year;
        }

        $leaves = Leave::query()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->when($actor->company_id !== null, fn ($query) => $query->where('company_id', $actor->company_id))
            ->whereYear('start_on', $year)
            ->get();

        $upcoming = $leaves
            ->filter(fn (Leave $leave): bool => $leave->return_on->gt(today()))
            ->sortBy(fn (Leave $leave): string => $leave->start_on->toDateString())
            ->values()
            ->map(fn (Leave $leave): array => $this->present($leave));

        $past = $leaves
            ->filter(fn (Leave $leave): bool => $leave->return_on->lte(today()))
            ->sortByDesc(fn (Leave $leave): string => $leave->start_on->toDateString())
            ->values()
            ->map(fn (Leave $leave): array => $this->present($leave));

        return Inertia::render('Leaves/Index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'balances' => $this->balances($actor, $year),
            'year' => $year,
            'years' => range(today()->year, 2019),
            'canManage' => $actor->can('leaves.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Leave::class);

        return Inertia::render('Leaves/Form', [
            'leave' => null,
            'staff' => $this->staffOptions($request->user()),
            'statuses' => $this->statuses(),
            'canManage' => $request->user()->can('leaves.manage'),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $subject = $request->subject();
        $start = $request->date('start_on');
        $return = $request->date('return_on');

        Leave::query()->create([
            'user_id' => $subject->id,
            'company_id' => $subject->company_id,
            'start_on' => $start->toDateString(),
            'return_on' => $return->toDateString(),
            'leave_days' => LeaveRules::dayCount($start, $return),
            'status' => LeaveStatus::Pending,
            'in_office' => (bool) $subject->in_office,
        ]);

        return redirect()
            ->route('leaves.index', ['year' => $start->year])
            ->with('success', 'İzin talebi kaydedildi.');
    }

    public function edit(Request $request, Leave $leave): Response
    {
        $this->authorize('update', $leave);

        $leave->load(['user' => fn ($query) => $query->withTrashed()]);

        return Inertia::render('Leaves/Form', [
            'leave' => [
                ...$this->present($leave),
                'user_name' => $leave->user?->name ?? 'Silinmiş personel',
            ],
            'staff' => [],
            'statuses' => $this->statuses(),
            'canManage' => $request->user()->can('leaves.manage'),
        ]);
    }

    public function update(UpdateLeaveRequest $request, Leave $leave): RedirectResponse
    {
        $start = $request->date('start_on');
        $return = $request->date('return_on');

        $leave->update([
            'start_on' => $start->toDateString(),
            'return_on' => $return->toDateString(),
            'leave_days' => LeaveRules::dayCount($start, $return),
            'status' => LeaveStatus::from((int) $request->integer('status')),
        ]);

        return redirect()
            ->route('leaves.index', ['year' => $start->year])
            ->with('success', 'İzin güncellendi.');
    }

    public function destroy(Leave $leave): RedirectResponse
    {
        $this->authorize('delete', $leave);

        $leave->delete();

        return redirect()
            ->route('leaves.index')
            ->with('success', 'İzin silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Leave $leave): array
    {
        return [
            'id' => $leave->id,
            'user_id' => $leave->user_id,
            'user_name' => $leave->user?->name ?? 'Silinmiş personel',
            'start_on' => $leave->start_on->toDateString(),
            'return_on' => $leave->return_on->toDateString(),
            'start_label' => $this->turkishDate($leave->start_on),
            'return_label' => $this->turkishDate($leave->return_on),
            'leave_days' => $leave->leave_days,
            'status' => $leave->status->value,
            'status_label' => $leave->status->label(),
            'is_pending' => $leave->status === LeaveStatus::Pending,
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function statuses(): array
    {
        return collect([LeaveStatus::Pending, LeaveStatus::Approved, LeaveStatus::Rejected])
            ->map(fn (LeaveStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function staffOptions(User $actor): array
    {
        if (! $actor->can('leaves.manage')) {
            return [];
        }

        return User::query()
            ->when($actor->company_id !== null, fn ($query) => $query->where('company_id', $actor->company_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $person): array => [
                'id' => $person->id,
                'name' => $person->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, hired_on: ?string, entitlement: int, used: int, remaining: int}>
     */
    private function balances(User $actor, int $year): array
    {
        if (! $actor->can('leaves.manage')) {
            return [];
        }

        $asOf = $year === today()->year
            ? today()
            : today()->copy()->setDate($year, 12, 31);

        return User::query()
            ->where(function ($query): void {
                $query->whereNull('access_level')
                    ->orWhere('access_level', '!=', UserAccessLevel::Manager->value);
            })
            ->when($actor->company_id !== null, fn ($query) => $query->where('company_id', $actor->company_id))
            ->orderBy('hired_on')
            ->orderBy('name')
            ->get(['id', 'name', 'hired_on'])
            ->map(function (User $person) use ($asOf, $year): array {
                $entitlement = LeaveRules::entitlement($person->hired_on, $asOf);
                $used = LeaveRules::usedDays($person->id, $year);

                return [
                    'name' => $person->name,
                    'hired_on' => $person->hired_on?->toDateString(),
                    'entitlement' => $entitlement,
                    'used' => $used,
                    'remaining' => max(0, $entitlement - $used),
                ];
            })
            ->all();
    }

    private function turkishDate(CarbonInterface $date): string
    {
        $months = [
            1 => 'Ocak',
            2 => 'Şubat',
            3 => 'Mart',
            4 => 'Nisan',
            5 => 'Mayıs',
            6 => 'Haziran',
            7 => 'Temmuz',
            8 => 'Ağustos',
            9 => 'Eylül',
            10 => 'Ekim',
            11 => 'Kasım',
            12 => 'Aralık',
        ];
        $days = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

        return $date->day.' '.$months[$date->month].' '.$date->year.' '.$days[$date->dayOfWeek];
    }
}
