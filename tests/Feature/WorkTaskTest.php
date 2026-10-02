<?php

namespace Tests\Feature;

use App\Enums\WorkTaskStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(?int $companyId = null): User
    {
        foreach (['work_tasks.view', 'work_tasks.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo(['work_tasks.view', 'work_tasks.manage']);

        return $user;
    }

    public function test_work_tasks_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('work-tasks.index'))
            ->assertForbidden();
    }

    public function test_task_stores_the_signed_in_users_company(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->manager($company->id);

        $this->actingAs($user)
            ->post(route('work-tasks.store'), [
                'title' => 'Vergi ödemesi',
                'due_on' => '2026-11-01',
                'repeats_monthly' => true,
                'company_id' => $other->id,
            ])
            ->assertRedirect(route('work-tasks.index'));

        $task = WorkTask::query()->where('title', 'Vergi ödemesi')->first();

        $this->assertNotNull($task);
        $this->assertSame($company->id, $task->company_id);
        $this->assertTrue($task->repeats_monthly);
        $this->assertSame(WorkTaskStatus::Open, $task->status);
    }

    public function test_store_requires_a_title(): void
    {
        $user = $this->manager();

        $this->actingAs($user)
            ->from(route('work-tasks.index'))
            ->post(route('work-tasks.store'), [
                'title' => '',
                'due_on' => '2026-11-01',
            ])
            ->assertRedirect(route('work-tasks.index'))
            ->assertSessionHasErrors(['title' => 'Görev tanımı zorunludur.']);
    }

    public function test_open_task_can_be_marked_completed(): void
    {
        $user = $this->manager();
        $task = WorkTask::factory()->create([
            'title' => 'Çöp vergisi',
            'due_on' => '2026-11-01',
            'status' => WorkTaskStatus::Open,
        ]);

        $this->actingAs($user)
            ->put(route('work-tasks.update', $task), [
                'title' => 'Çöp vergisi ikinci taksit',
                'due_on' => '2026-11-15',
                'repeats_monthly' => false,
                'status' => WorkTaskStatus::Completed->value,
            ])
            ->assertRedirect(route('work-tasks.index'));

        $task->refresh();

        $this->assertSame('Çöp vergisi ikinci taksit', $task->title);
        $this->assertSame('2026-11-15', $task->due_on->toDateString());
        $this->assertSame(WorkTaskStatus::Completed, $task->status);
    }

    public function test_monthly_task_from_last_month_rolls_forward_on_the_list(): void
    {
        $user = $this->manager();
        $dueOn = now()->subMonth()->startOfMonth()->toDateString();
        $task = WorkTask::factory()->create([
            'title' => 'Aylık beyan',
            'due_on' => $dueOn,
            'repeats_monthly' => true,
            'status' => WorkTaskStatus::Open,
        ]);

        $this->actingAs($user)
            ->get(route('work-tasks.index'))
            ->assertOk();

        $task->refresh();
        $next = WorkTask::query()->where('title', 'Aylık beyan')->where('id', '!=', $task->id)->first();

        $this->assertFalse($task->repeats_monthly);
        $this->assertNotNull($next);
        $this->assertTrue($next->repeats_monthly);
        $this->assertSame(WorkTaskStatus::Open, $next->status);
        $this->assertSame(
            now()->subMonth()->startOfMonth()->addMonthNoOverflow()->toDateString(),
            $next->due_on->toDateString(),
        );
    }
}
