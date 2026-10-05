<?php

namespace Tests\Feature;

use App\Enums\UserAccessLevel;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_update_saves_contact_details_and_ignores_access_level(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'access_level' => UserAccessLevel::Staff,
            'is_active' => true,
        ]);
        $photo = UploadedFile::fake()->createWithContent(
            'portre.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                'title' => 'Usta',
                'phone' => '555',
                'address' => 'Depo',
                'hired_on' => '2020-01-15',
                'access_level' => 'manager',
                'is_active' => false,
                'photo' => $photo,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Usta', $user->title);
        $this->assertSame('555', $user->phone);
        $this->assertSame('Depo', $user->address);
        $this->assertSame('2020-01-15', $user->hired_on?->toDateString());
        $this->assertSame(UserAccessLevel::Staff, $user->access_level);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->media()->where('collection', 'photo')->whereNotNull('path')->first());
    }

    public function test_user_cannot_download_another_persons_document(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = UploadedFile::fake()->createWithContent(
            'portre.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $this->actingAs($owner)->patch('/profile', [
            'name' => $owner->name,
            'email' => $owner->email,
            'photo' => $photo,
        ])->assertRedirect('/profile');

        $medium = Media::query()->where('model_id', $owner->id)->where('collection', 'photo')->first();
        $this->assertNotNull($medium);

        $this->actingAs($owner)
            ->get(route('profile.documents.download', $medium))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('profile.documents.download', $medium))
            ->assertNotFound();
    }
}
