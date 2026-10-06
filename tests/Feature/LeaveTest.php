<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\UserAccessLevel;
use App\Models\Company;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function person(?int $companyId = null, bool $manage = false, array $overrides = []): User
    {
        Permission::findOrCreate('leaves.view');
        Permission::findOrCreate('leaves.manage');

        $user = User::factory()->create([
            'company_id' => $companyId,
            'hired_on' => '2020-01-01',
            'access_flags' => ['office' => false],
            ...$overrides,
        ]);
        $user->givePermissionTo($manage ? ['leaves.view', 'leaves.manage'] : ['leaves.view']);

        return $user;
    }

    public function test_staff_can_request_their_own_leave_inside_the_window(): void
    {
        $this->travelTo('2026-02-10');
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $staff = $this->person($company->id, overrides: [
            'access_flags' => ['office' => true],
        ]);

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
                'company_id' => $other->id,
            ])
            ->assertRedirect(route('leaves.index', ['year' => 2026]));

        $leave = Leave::query()->first();
        $this->assertNotNull($leave);
        $this->assertSame($staff->id, $leave->user_id);
        $this->assertSame($company->id, $leave->company_id);
        $this->assertSame(3, $leave->leave_days);
        $this->assertSame(LeaveStatus::Pending, $leave->status);
        $this->assertTrue($leave->in_office);
    }

    public function test_dates_are_required(): void
    {
        $this->travelTo('2026-02-10');
        $staff = $this->person();

        $this->actingAs($staff)
            ->post(route('leaves.store'), [])
            ->assertSessionHasErrors([
                'start_on' => 'Başlangıç tarihi zorunludur.',
                'return_on' => 'İşe dönüş tarihi zorunludur.',
            ]);
    }

    public function test_staff_cannot_request_leave_outside_january_through_march(): void
    {
        $this->travelTo('2026-10-06');
        $staff = $this->person(overrides: ['hired_on' => '2010-01-01']);

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'start_on' => '2026-10-12',
                'return_on' => '2026-10-16',
            ])
            ->assertSessionHasErrors([
                'start_on' => 'Sadece 1 Ocak ile 31 Mart tarihleri arasında izin girişi yapabilirsiniz. Bu tarihler dışında lütfen yöneticinizle iletişime geçiniz.',
            ]);

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_staff_cannot_book_another_person(): void
    {
        $this->travelTo('2026-02-10');
        $staff = $this->person();
        $other = User::factory()->create();

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'user_id' => $other->id,
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
            ])
            ->assertSessionHasErrors([
                'user_id' => 'İzin yalnızca kendi adınıza girilebilir.',
            ]);
    }

    public function test_a_request_longer_than_fourteen_days_is_rejected(): void
    {
        $this->travelTo('2026-02-10');
        $staff = $this->person(overrides: ['hired_on' => '2010-01-01']);

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-03-03',
            ])
            ->assertSessionHasErrors([
                'return_on' => 'Tek seferde en fazla 14 günlük izin girebilirsiniz.',
            ]);
    }

    public function test_new_hires_cannot_exceed_their_remaining_allowance(): void
    {
        $this->travelTo('2026-02-10');
        $staff = $this->person(overrides: ['hired_on' => '2025-08-01']);

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
            ])
            ->assertSessionHasErrors([
                'return_on' => 'Kalan izin hakkınız 0 gündür. Daha fazla izin talep edemezsiniz.',
            ]);
    }

    public function test_same_office_leaves_cannot_overlap(): void
    {
        $this->travelTo('2026-02-10');
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $office = $this->person($company->id, overrides: ['access_flags' => ['office' => true]]);
        $colleague = $this->person($company->id, overrides: ['access_flags' => ['office' => true]]);
        $floor = $this->person($company->id, overrides: ['access_flags' => ['office' => false]]);
        Leave::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => User::factory()->create(['company_id' => $otherCompany->id])->id,
            'start_on' => '2026-02-16',
            'return_on' => '2026-02-20',
            'leave_days' => 4,
            'status' => LeaveStatus::Approved,
            'in_office' => true,
        ]);

        $this->actingAs($floor)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
            ])
            ->assertRedirect();

        $this->actingAs($office)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-18',
                'return_on' => '2026-02-21',
            ])
            ->assertRedirect();

        $this->actingAs($colleague)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-18',
                'return_on' => '2026-02-21',
            ])
            ->assertSessionHasErrors([
                'start_on' => 'Sizin departmanınızda aynı tarihlerde izin alan başka bir çalışan var. Lütfen yıllık izin planından kontrol ediniz.',
            ]);
    }

    public function test_staff_must_keep_one_hundred_days_between_their_leaves(): void
    {
        $this->travelTo('2026-02-10');
        $staff = $this->person(overrides: ['hired_on' => '2010-01-01']);
        Leave::factory()->create([
            'user_id' => $staff->id,
            'company_id' => $staff->company_id,
            'start_on' => '2026-01-10',
            'return_on' => '2026-01-20',
            'leave_days' => 10,
            'status' => LeaveStatus::Approved,
        ]);

        $this->actingAs($staff)
            ->post(route('leaves.store'), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
            ])
            ->assertSessionHasErrors([
                'start_on' => 'İki izin arasında en az 100 gün olmak zorundadır.',
            ]);

        $manager = $this->person(manage: true, overrides: [
            'hired_on' => '2010-01-01',
            'access_level' => UserAccessLevel::Manager,
        ]);

        $this->actingAs($manager)
            ->post(route('leaves.store'), [
                'user_id' => $staff->id,
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
            ])
            ->assertRedirect();
    }

    public function test_a_manager_can_record_leave_outside_the_window_and_approve_it(): void
    {
        $this->travelTo('2026-10-06');
        $company = Company::factory()->create();
        $staff = User::factory()->create([
            'company_id' => $company->id,
            'hired_on' => '2010-01-01',
            'access_flags' => ['office' => false],
        ]);
        $manager = $this->person($company->id, true, [
            'hired_on' => '2010-01-01',
            'access_level' => UserAccessLevel::Manager,
        ]);

        $this->actingAs($manager)
            ->post(route('leaves.store'), [
                'user_id' => $staff->id,
                'start_on' => '2026-10-12',
                'return_on' => '2026-10-16',
            ])
            ->assertRedirect();

        $leave = Leave::query()->first();
        $this->assertSame(LeaveStatus::Pending, $leave->status);

        $this->actingAs($manager)
            ->put(route('leaves.update', $leave), [
                'start_on' => '2026-10-12',
                'return_on' => '2026-10-16',
                'status' => LeaveStatus::Approved->value,
            ])
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
    }

    public function test_staff_cannot_edit_or_open_leaves_without_permission(): void
    {
        $this->travelTo('2026-02-10');
        $company = Company::factory()->create();
        $staff = $this->person($company->id);
        $leave = Leave::factory()->create([
            'user_id' => $staff->id,
            'company_id' => $company->id,
        ]);
        $outsider = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($outsider)
            ->get(route('leaves.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('leaves.update', $leave), [
                'start_on' => '2026-02-16',
                'return_on' => '2026-02-19',
                'status' => LeaveStatus::Approved->value,
            ])
            ->assertForbidden();
    }

    public function test_a_manager_cannot_change_another_companys_leave(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $manager = $this->person($company->id, true);
        $leave = Leave::factory()->create([
            'company_id' => $other->id,
            'user_id' => User::factory()->create(['company_id' => $other->id])->id,
        ]);

        $this->actingAs($manager)
            ->get(route('leaves.edit', $leave))
            ->assertForbidden();
    }

    public function test_index_splits_the_year_and_shows_allowances(): void
    {
        $this->travelTo('2026-02-10');
        $company = Company::factory()->create();
        $manager = $this->person($company->id, true, [
            'name' => 'Yönetici',
            'access_level' => UserAccessLevel::Manager,
            'hired_on' => '2000-01-01',
        ]);
        $senior = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Kıdemli',
            'hired_on' => '2010-01-01',
        ]);
        $mid = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orta',
            'hired_on' => '2020-01-01',
        ]);
        Leave::factory()->create([
            'company_id' => $company->id,
            'user_id' => $senior->id,
            'start_on' => '2026-02-16',
            'return_on' => '2026-02-20',
            'leave_days' => 4,
            'status' => LeaveStatus::Pending,
        ]);
        Leave::factory()->create([
            'company_id' => $company->id,
            'user_id' => $mid->id,
            'start_on' => '2026-01-05',
            'return_on' => '2026-01-10',
            'leave_days' => 5,
            'status' => LeaveStatus::Approved,
        ]);
        Leave::factory()->create([
            'company_id' => $company->id,
            'user_id' => $mid->id,
            'start_on' => '2025-06-01',
            'return_on' => '2025-06-04',
            'leave_days' => 3,
            'status' => LeaveStatus::Approved,
        ]);
        Leave::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'start_on' => '2026-02-16',
            'return_on' => '2026-02-20',
            'leave_days' => 4,
        ]);

        $this->actingAs($manager)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('year', 2026)
                ->where('upcoming.0.user_name', 'Kıdemli')
                ->where('upcoming.0.start_label', '16 Şubat 2026 Pazartesi')
                ->where('past.0.user_name', 'Orta')
                ->where('balances', [
                    [
                        'name' => 'Kıdemli',
                        'hired_on' => '2010-01-01',
                        'entitlement' => 26,
                        'used' => 0,
                        'remaining' => 26,
                    ],
                    [
                        'name' => 'Orta',
                        'hired_on' => '2020-01-01',
                        'entitlement' => 20,
                        'used' => 5,
                        'remaining' => 15,
                    ],
                ])
                ->has('upcoming', 1)
                ->has('past', 1));

        $this->actingAs($manager)
            ->get(route('leaves.index', ['year' => 2025]))
            ->assertInertia(fn ($page) => $page
                ->has('past', 1)
                ->where('past.0.leave_days', 3)
                ->where('balances.1.entitlement', 20)
                ->where('balances.1.used', 3));
    }

    public function test_delete_hides_the_leave(): void
    {
        $company = Company::factory()->create();
        $manager = $this->person($company->id, true);
        $leave = Leave::factory()->create([
            'company_id' => $company->id,
            'user_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->delete(route('leaves.destroy', $leave))
            ->assertRedirect(route('leaves.index'));

        $this->assertSoftDeleted($leave);
    }

    public function test_legacy_import_keeps_ids_status_and_skips_unknown_staff(): void
    {
        $company = Company::factory()->create();
        $staff = User::factory()->create();
        $directory = storage_path('framework/testing/leave-import');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/leaves.csv', implode("\n", [
            'id,user_id,start_date,return_date,leave_days,status,office,company_id,is_deleted,time',
            "8,{$staff->id},2026-02-02,2026-02-06,4,1,1,{$company->id},0,1728901213",
            '9,99999,2026-02-02,2026-02-06,4,0,0,2,0,1728901213',
            "10,{$staff->id},2024-03-01,2024-03-06,5,3,0,99999,1,1700000000",
        ]));

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'leaves',
            ])->assertSuccessful();

            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'leaves',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $kept = Leave::query()->find(8);
        $removed = Leave::withTrashed()->find(10);

        $this->assertNotNull($kept);
        $this->assertSame(4, $kept->leave_days);
        $this->assertSame(LeaveStatus::Approved, $kept->status);
        $this->assertTrue($kept->in_office);
        $this->assertSame($company->id, $kept->company_id);
        $this->assertSame('2024-10-14 10:20:13', $kept->created_at->format('Y-m-d H:i:s'));
        $this->assertNull(Leave::withTrashed()->find(9));
        $this->assertNotNull($removed);
        $this->assertTrue($removed->trashed());
        $this->assertSame(LeaveStatus::Cancelled, $removed->status);
        $this->assertNull($removed->company_id);
        $this->assertSame(2, Leave::withTrashed()->count());
    }
}
