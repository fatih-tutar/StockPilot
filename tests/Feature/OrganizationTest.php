<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrganizationTest extends TestCase
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

    public function test_organization_chart_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('organization.index'))
            ->assertForbidden();
    }

    public function test_chart_update_keeps_the_company_and_stores_a_photo(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->userWithPermission('organizations.manage', $company->id);
        $member = OrganizationMember::factory()->create([
            'company_id' => $company->id,
            'position' => 1,
            'name' => 'Eski ad',
            'title' => 'Eski unvan',
        ]);

        $this->actingAs($user)
            ->post(route('organization.update'), [
                'people' => [[
                    'id' => $member->id,
                    'name' => 'Osman Türkyılmaz',
                    'title' => 'Yönetim Kurulu Başkanı',
                    'company_id' => $other->id,
                    'photo' => UploadedFile::fake()->createWithContent(
                        'portre.png',
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
                    ),
                ]],
            ])
            ->assertRedirect(route('organization.index'));

        $member->refresh();

        $this->assertSame('Osman Türkyılmaz', $member->name);
        $this->assertSame('Yönetim Kurulu Başkanı', $member->title);
        $this->assertSame($company->id, $member->company_id);
        $this->assertTrue($member->media()->where('collection', 'photo')->whereNotNull('path')->exists());

        $photo = $member->media()->where('collection', 'photo')->first();

        $this->actingAs($user)
            ->get(route('organization.photo', $member))
            ->assertOk();

        $this->assertNotNull($photo);
    }

    public function test_view_permission_cannot_edit_the_chart(): void
    {
        $user = $this->userWithPermission('organizations.view');
        $member = OrganizationMember::factory()->create([
            'position' => 1,
            'name' => 'Osman Türkyılmaz',
        ]);

        $this->actingAs($user)
            ->post(route('organization.update'), [
                'people' => [[
                    'id' => $member->id,
                    'name' => 'Değişmesin',
                    'title' => 'Ünvan',
                ]],
            ])
            ->assertForbidden();

        $this->assertSame('Osman Türkyılmaz', $member->fresh()->name);
    }
}
