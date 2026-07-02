<?php

namespace Tests\Feature\Settings;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_email_is_rejected_without_a_valid_current_password()
    {
        $user = Member::factory()->create(['email' => 'old@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'new@example.com',
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/profile');

        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_changing_email_is_rejected_when_current_password_is_missing()
    {
        $user = Member::factory()->create(['email' => 'old@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'new@example.com',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/profile');

        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_changing_email_succeeds_with_a_valid_current_password()
    {
        $user = Member::factory()->create(['email' => 'old@example.com']);

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => 'new@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertSame('new@example.com', $user->refresh()->email);
    }

    public function test_changing_name_without_the_email_does_not_require_a_password()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Renamed',
                'last_name' => 'Member',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertSame('Renamed', $user->refresh()->first_name);
    }

    public function test_saving_without_an_email_change_tolerates_the_empty_current_password_field()
    {
        // The real form always submits current_password (default ''), and
        // ConvertEmptyStringsToNull rewrites '' to null — which a bare current_password
        // rule treats as present-and-wrong. Guards against that regression: a non-email
        // save carrying an empty current_password must still succeed, not silently fail
        // on a hidden field.
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Renamed',
                'last_name' => $user->last_name,
                'email' => $user->email,
                'current_password' => '',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertSame('Renamed', $user->refresh()->first_name);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_contact_and_address_fields_can_be_updated()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => '416-555-0100',
                'alternate_phone' => '416-555-0101',
                'business_phone' => '416-555-0102',
                'address_street' => '100 Queens Park',
                'address_city' => 'Toronto',
                'address_province' => 'ON',
                'address_postal_code' => 'M5S 2C6',
                'address_country' => 'Canada',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('416-555-0100', $user->phone);
        $this->assertSame('416-555-0101', $user->alternate_phone);
        $this->assertSame('416-555-0102', $user->business_phone);
        $this->assertSame('100 Queens Park', $user->address_street);
        $this->assertSame('Toronto', $user->address_city);
        $this->assertSame('ON', $user->address_province);
        $this->assertSame('M5S 2C6', $user->address_postal_code);
        $this->assertSame('Canada', $user->address_country);
    }

    public function test_contact_fields_are_optional()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'first_name' => 'Nameless',
                'last_name' => 'Contact',
                'email' => $user->email,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Nameless', $user->refresh()->first_name);
    }

    public function test_identity_first_name_is_required()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->patch('/settings/profile', [
                'first_name' => '',
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasErrors('first_name')
            ->assertRedirect('/settings/profile');
    }

    public function test_invalid_postal_code_is_rejected()
    {
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->patch('/settings/profile', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'address_postal_code' => 'not-a-code',
            ]);

        $response
            ->assertSessionHasErrors('address_postal_code')
            ->assertRedirect('/settings/profile');
    }

    public function test_uploading_a_photo_stores_a_square_webp_and_sets_photo_path()
    {
        Storage::fake('public');
        $user = Member::factory()->create();

        // Mirror the browser: a File forces multipart, which PHP parses only on POST,
        // so the form submits POST + _method spoof rather than a real PATCH.
        $response = $this
            ->actingAs($user)
            ->post('/settings/profile', [
                '_method' => 'PATCH',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->image('avatar.jpg', 800, 600),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $path = $user->refresh()->photo_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('profile-photos/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_heic_upload_is_rejected()
    {
        Storage::fake('public');
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->post('/settings/profile', [
                '_method' => 'PATCH',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()->create('iphone.heic', 200, 'image/heic'),
            ]);

        $response
            ->assertSessionHasErrors('photo')
            ->assertRedirect('/settings/profile');

        $this->assertNull($user->refresh()->photo_path);
    }

    public function test_oversized_upload_is_rejected()
    {
        Storage::fake('public');
        $user = Member::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->post('/settings/profile', [
                '_method' => 'PATCH',
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                // A real JPEG (passes the format allowlist) reporting 6 MB — trips the
                // size cap specifically, not the type check.
                'photo' => UploadedFile::fake()->image('huge.jpg')->size(6 * 1024),
            ]);

        $response
            ->assertSessionHasErrors('photo')
            ->assertRedirect('/settings/profile');

        $this->assertNull($user->refresh()->photo_path);
    }
}
