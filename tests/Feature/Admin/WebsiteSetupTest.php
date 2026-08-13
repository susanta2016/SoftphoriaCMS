<?php

namespace Tests\Feature\Admin;

use App\Actions\Page\CreatePageAction;
use App\Enums\PageTemplate;
use App\Filament\Clusters\WebsiteSetup\Pages\Settings;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Shared\Services\Settings\MailSettingsApplier;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Website Setup (docs/ARCHITECTURE.md §16) — General Settings, Email
 * Settings, and the Maintenance Mode middleware. Priority 1 of the approved
 * requirement; Email Templates (Priority 2) has its own test file.
 */
class WebsiteSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_website_setup(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/website-setup/settings');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_settings_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertSuccessful();
    }

    public function test_admin_can_save_general_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'general' => [
                    'site_name' => 'Softphoria',
                    'tagline' => 'Light is our nature.',
                    'site_url' => 'https://softphoria.test',
                    'maintenance_mode' => false,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertSame('Softphoria', $settings->get('general', 'site_name'));
        $this->assertSame('Light is our nature.', $settings->get('general', 'tagline'));
        $this->assertSame('https://softphoria.test', $settings->get('general', 'site_url'));
    }

    public function test_maintenance_page_is_required_when_maintenance_mode_is_enabled(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'general' => [
                    'site_name' => 'Softphoria',
                    'site_url' => 'https://softphoria.test',
                    'maintenance_mode' => true,
                    'maintenance_page_id' => null,
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['general.maintenance_page_id' => 'required']);
    }

    public function test_admin_can_enable_maintenance_mode_with_a_published_page(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Maintenance', 'slug' => 'maintenance-test', 'template' => PageTemplate::Standard->value,
        ], $admin);
        $page->update(['status' => 'published']);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm([
                'general' => [
                    'site_name' => 'Softphoria',
                    'site_url' => 'https://softphoria.test',
                    'maintenance_mode' => true,
                    'maintenance_page_id' => $page->id,
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertTrue($settings->get('general', 'maintenance_mode'));
        $this->assertSame($page->id, $settings->get('general', 'maintenance_page_id'));
    }

    public function test_smtp_password_is_encrypted_at_rest_and_never_returned_in_plaintext(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'general' => ['site_name' => 'Softphoria', 'site_url' => 'https://softphoria.test'],
                'email' => ['smtp_password' => 'super-secret-password'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $raw = Setting::query()->where('group', 'email')->where('key', 'smtp_password')->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('super-secret-password', (string) $raw->value);

        $this->assertSame('super-secret-password', app(SettingsRepository::class)->get('email', 'smtp_password'));

        // Reopening the form must never re-display the saved password.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertSet('data.email.smtp_password', null);
    }

    public function test_leaving_the_password_field_blank_on_save_keeps_the_previously_saved_password(): void
    {
        $admin = $this->admin();
        app(SettingsRepository::class)->set('email', 'smtp_password', 'original-password', 'encrypted');

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm([
                'general' => ['site_name' => 'Softphoria', 'site_url' => 'https://softphoria.test'],
                'email' => ['smtp_password' => null],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('original-password', app(SettingsRepository::class)->get('email', 'smtp_password'));
    }

    public function test_settings_repository_round_trips_boolean_and_string_values(): void
    {
        $settings = app(SettingsRepository::class);

        $settings->set('general', 'maintenance_mode', true, 'boolean');
        $this->assertTrue($settings->get('general', 'maintenance_mode'));

        $settings->set('general', 'maintenance_mode', false, 'boolean');
        $this->assertFalse($settings->get('general', 'maintenance_mode'));

        $settings->set('general', 'site_name', 'Softphoria');
        $this->assertSame('Softphoria', $settings->get('general', 'site_name'));

        $this->assertNull($settings->get('general', 'missing_key'));
        $this->assertSame('default', $settings->get('general', 'missing_key', 'default'));
    }

    public function test_mail_settings_applier_forces_the_log_driver_when_email_is_disabled(): void
    {
        app(MailSettingsApplier::class)->applyFromArray(['enabled' => false]);

        $this->assertSame('log', config('mail.default'));
    }

    public function test_mail_settings_applier_applies_smtp_configuration_when_enabled(): void
    {
        app(MailSettingsApplier::class)->applyFromArray([
            'enabled' => true,
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'user@example.com',
            'smtp_password' => 'secret',
            'from_name' => 'Softphoria',
            'from_email' => 'hello@softphoria.test',
        ]);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('hello@softphoria.test', config('mail.from.address'));
        $this->assertSame('Softphoria', config('mail.from.name'));
    }

    public function test_mail_settings_applier_uses_the_implicit_tls_scheme_for_ssl_encryption(): void
    {
        app(MailSettingsApplier::class)->applyFromArray([
            'enabled' => true,
            'smtp_encryption' => 'ssl',
        ]);

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_maintenance_mode_off_serves_the_normal_site(): void
    {
        app(SettingsRepository::class)->set('general', 'maintenance_mode', false, 'boolean');

        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_maintenance_mode_on_serves_the_selected_published_page_with_a_503_status(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Down For Maintenance', 'slug' => 'maintenance-page', 'template' => PageTemplate::Standard->value,
        ], $admin);
        $page->update(['status' => 'published']);

        $settings = app(SettingsRepository::class);
        $settings->set('general', 'maintenance_mode', true, 'boolean');
        $settings->set('general', 'maintenance_page_id', $page->id);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('Down For Maintenance');
    }

    public function test_maintenance_mode_never_recurses_and_falls_through_when_the_page_is_missing(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('general', 'maintenance_mode', true, 'boolean');
        $settings->set('general', 'maintenance_page_id', 999999);

        $response = $this->get('/');

        $response->assertOk();
    }

    /**
     * Regression coverage for the exact bug caught in browser verification:
     * Livewire 4 does not use a fixed "/livewire/*" path — it derives a
     * per-installation prefix from APP_KEY. Before this was fixed, enabling
     * Maintenance Mode broke every Livewire interaction in the admin panel,
     * including the Settings page's own Save button.
     */
    public function test_maintenance_mode_does_not_block_the_livewire_update_endpoint(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Maintenance', 'slug' => 'maintenance-livewire-test', 'template' => PageTemplate::Standard->value,
        ], $admin);
        $page->update(['status' => 'published']);

        $settings = app(SettingsRepository::class);
        $settings->set('general', 'maintenance_mode', true, 'boolean');
        $settings->set('general', 'maintenance_page_id', $page->id);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm(['general' => ['site_name' => 'Still Works', 'site_url' => 'https://softphoria.test']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Still Works', $settings->get('general', 'site_name'));
    }

    public function test_maintenance_mode_does_not_block_the_admin_panel(): void
    {
        $admin = $this->admin();
        $page = app(CreatePageAction::class)->handle([
            'title' => 'Maintenance', 'slug' => 'maintenance-admin-test', 'template' => PageTemplate::Standard->value,
        ], $admin);
        $page->update(['status' => 'published']);

        $settings = app(SettingsRepository::class);
        $settings->set('general', 'maintenance_mode', true, 'boolean');
        $settings->set('general', 'maintenance_page_id', $page->id);

        $response = $this->actingAs($admin)->get('/admin/website-setup/settings');

        $response->assertOk();
    }

    public function test_test_email_sending_failure_is_caught_and_does_not_crash_the_page(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['email' => ['test_recipient_email' => 'someone@example.com']])
            ->callFormComponentAction('sendTestEmailActions', 'sendTestEmail')
            ->assertSuccessful();
    }

    public function test_test_email_requires_a_recipient(): void
    {
        Mail::shouldReceive('raw')->never();

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['email' => ['test_recipient_email' => null]])
            ->callFormComponentAction('sendTestEmailActions', 'sendTestEmail')
            ->assertSuccessful();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
