<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function userWithPermission(string $permission, ?int $companyId = null): User
    {
        Permission::findOrCreate($permission);

        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_staff_list_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_empty_staff_form_asks_for_name_email_level_and_password(): void
    {
        $user = $this->userWithPermission('users.manage');

        $this->actingAs($user)
            ->post(route('staff.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Ad zorunludur.',
                'email' => 'E-posta zorunludur.',
                'access_level' => 'Yetki düzeyi zorunludur.',
                'password' => 'Şifre zorunludur.',
            ]);
    }

    public function test_store_keeps_the_company_and_stores_a_photo(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->userWithPermission('users.manage', $company->id);
        $photo = UploadedFile::fake()->createWithContent(
            'portre.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $this->actingAs($user)
            ->post(route('staff.store'), [
                'name' => 'Deneme Personel',
                'email' => 'deneme@example.com',
                'access_level' => 'staff',
                'password' => 'password1',
                'password_confirmation' => 'password1',
                'company_id' => $other->id,
                'is_active' => true,
                'photo' => $photo,
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'deneme@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame($company->id, $created->company_id);
        $this->assertTrue(Hash::check('password1', $created->password));
        $this->assertNotNull($created->media()->where('collection', 'photo')->whereNotNull('path')->first());
    }

    public function test_signed_in_user_cannot_delete_themselves(): void
    {
        $user = $this->userWithPermission('users.manage');

        $this->actingAs($user)
            ->delete(route('staff.destroy', $user))
            ->assertSessionHas('error');

        $this->assertNotNull($user->fresh());
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => 'Bu hesap pasif.']);

        $this->assertGuest();
    }

    public function test_legacy_import_keeps_ids_and_does_not_copy_passwords(): void
    {
        $company = Company::factory()->create();
        $demo = User::factory()->create([
            'id' => 1,
            'email' => 'admin@stockpilot.test',
        ]);
        $legacyPassword = str_repeat('a', 32);
        $directory = sys_get_temp_dir().'/stockpilot-users-'.uniqid();
        File::makeDirectory($directory);

        $users = fopen($directory.'/users.csv', 'wb');
        fputcsv($users, ['id', 'name', 'email', 'phone', 'phone_2', 'address', 'title', 'password', 'company_id', 'type', 'permissions', 'hire_date', 'identity_card', 'application_form', 'residence_certificate', 'health_report', 'is_passive', 'is_deleted', 'photo']);
        fputcsv($users, ['1', 'Deneme Personel', 'deneme@example.com', '555', '5552', 'Adres', 'Usta', $legacyPassword, (string) $company->id, '0', '1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0', '01-06-2019', 'kimlik.pdf', '', '', '', '0', '0', 'portre.jpg']);
        fputcsv($users, ['3', 'Bos Eposta', '', '', '', '', '', str_repeat('b', 32), (string) $company->id, '1', '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0', '', '', '', '', '', '0', '0', '']);
        fputcsv($users, ['4', 'Ayni Eposta', 'deneme@example.com', '', '', '', '', str_repeat('d', 32), (string) $company->id, '0', '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0', '', '', '', '', '', '0', '0', '']);
        fputcsv($users, ['9', 'Silinen', 'silinen@example.com', '', '', '', '', str_repeat('c', 32), (string) $company->id, '2', '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0', '', '', '', '', '', '0', '1', '']);
        fclose($users);
        $organizations = fopen($directory.'/organizations.csv', 'wb');
        fputcsv($organizations, ['id', 'name', 'title', 'user_id', 'photo', 'created_at']);
        fputcsv($organizations, ['5', '', 'Usta', '1', '', '']);
        fclose($organizations);

        try {
            $this->artisan('stockpilot:import-legacy', [
                'path' => $directory,
                '--only' => 'users',
            ])->assertSuccessful();
        } finally {
            File::deleteDirectory($directory);
        }

        $imported = User::query()->find(1);
        $this->assertNotNull($imported);
        $this->assertSame('Deneme Personel', $imported->name);
        $this->assertSame($company->id, $imported->company_id);
        $this->assertSame('2019-06-01', $imported->hired_on?->toDateString());
        $this->assertTrue($imported->hasPermissionTo('columns.purchase'));
        $this->assertFalse($imported->in_office);
        $this->assertNotSame($legacyPassword, $imported->password);
        $this->assertFalse(Hash::check($legacyPassword, $imported->password));
        $this->assertNotNull($imported->media()->where('collection', 'photo')->whereNull('path')->first());

        $this->assertNull(User::withTrashed()->find(3));
        $this->assertNull(User::withTrashed()->find(4));

        $this->assertSoftDeleted('users', ['id' => 9]);

        $moved = User::query()->where('email', 'admin@stockpilot.test')->first();
        $this->assertNotNull($moved);
        $this->assertNotSame($demo->id, $moved->id);
        $this->assertTrue(Hash::check('password', $moved->password));

        $this->assertSame(1, OrganizationMember::query()->find(5)?->user_id);
    }

    public function test_saving_staff_flags_grants_and_removes_direct_permissions(): void
    {
        $actor = $this->userWithPermission('users.manage');

        $this->actingAs($actor)
            ->post(route('staff.store'), [
                'name' => 'Yetkili Personel',
                'email' => 'yetkili@example.com',
                'access_level' => 'staff',
                'password' => 'password1',
                'password_confirmation' => 'password1',
                'is_active' => true,
                'access_flags' => [
                    'piece_quantity' => true,
                    'office' => true,
                ],
            ])
            ->assertRedirect();

        $staff = User::query()->where('email', 'yetkili@example.com')->first();
        $this->assertNotNull($staff);
        $this->assertTrue($staff->in_office);
        $this->assertTrue($staff->hasPermissionTo('columns.piece'));
        $this->assertFalse(Schema::hasColumn('users', 'access_flags'));

        $this->actingAs($actor)
            ->put(route('staff.update', $staff), [
                'name' => 'Yetkili Personel',
                'email' => 'yetkili@example.com',
                'access_level' => 'staff',
                'is_active' => true,
                'access_flags' => [
                    'piece_quantity' => false,
                    'office' => false,
                ],
            ])
            ->assertRedirect();

        $staff->refresh();
        $this->assertFalse($staff->in_office);
        $this->assertFalse($staff->hasPermissionTo('columns.piece'));
    }
}
