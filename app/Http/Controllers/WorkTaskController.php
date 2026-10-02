<?php

namespace App\Http\Controllers;

use App\Enums\WorkTaskStatus;
use App\Http\Requests\WorkTasks\StoreWorkTaskRequest;
use App\Http\Requests\WorkTasks\UpdateWorkTaskRequest;
use App\Models\WorkTask;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkTaskController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', WorkTask::class);

        WorkTask::rollMonthly();

        $open = WorkTask::query()
            ->where('status', WorkTaskStatus::Open)
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkTask $task) => $this->payload($task))
            ->values();

        $completed = WorkTask::query()
            ->where('status', WorkTaskStatus::Completed)
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkTask $task) => $this->payload($task))
            ->values();

        return Inertia::render('WorkTasks/Index', [
            'open_tasks' => $open,
            'completed_tasks' => $completed,
            'canManage' => request()->user()?->can('create', WorkTask::class) ?? false,
        ]);
    }

    public function store(StoreWorkTaskRequest $request): RedirectResponse
    {
        WorkTask::query()->create([
            'title' => $request->validated('title'),
            'due_on' => $request->validated('due_on'),
            'repeats_monthly' => $request->boolean('repeats_monthly'),
            'status' => WorkTaskStatus::Open,
        ]);

        return redirect()->route('work-tasks.index');
    }

    public function update(UpdateWorkTaskRequest $request, WorkTask $workTask): RedirectResponse
    {
        $workTask->update([
            'title' => $request->validated('title'),
            'due_on' => $request->validated('due_on'),
            'repeats_monthly' => $request->boolean('repeats_monthly'),
            'status' => $request->validated('status'),
        ]);

        return redirect()->route('work-tasks.index');
    }

    public function destroy(WorkTask $workTask): RedirectResponse
    {
        $this->authorize('delete', $workTask);

        $workTask->delete();

        return redirect()->route('work-tasks.index');
    }

    /**
     * @return array{id: int, title: string, due_on: string|null, repeats_monthly: bool, status: string, is_overdue: bool}
     */
    private function payload(WorkTask $task): array
    {
        $dueOn = $task->due_on?->toDateString();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'due_on' => $dueOn,
            'repeats_monthly' => $task->repeats_monthly,
            'status' => $task->status->value,
            'is_overdue' => $task->status === WorkTaskStatus::Open
                && $dueOn !== null
                && $dueOn < now()->toDateString(),
        ];
    }
}
