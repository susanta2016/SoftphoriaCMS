<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Widgets\BannedSendersWidget;
use App\Models\ContactBan;
use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use App\Shared\Support\Spam\FormTimeTrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Ban senders" bulk action on Contact → Submissions, the Banned Senders
 * list, and enforcement in ContactController::store().
 */
class ContactBanTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_action_bans_by_ip_and_email_and_deletes_submissions(): void
    {
        $spam = $this->createSubmission(['ip_address' => '198.51.100.7', 'email' => 'Spam@Example.com']);
        $keep = $this->createSubmission(['ip_address' => '203.0.113.1', 'email' => 'real@example.com']);

        Livewire::actingAs($admin = $this->admin())
            ->test(ListContactSubmissions::class)
            ->callTableBulkAction('banSenders', [$spam], [
                'criteria' => ['ip', 'email'],
                'reason' => 'Spam',
                'delete_submissions' => true,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertDispatched('contact-bans-updated');

        $this->assertDatabaseHas('contact_bans', ['type' => 'ip', 'value' => '198.51.100.7', 'banned_by' => $admin->id]);
        $this->assertDatabaseHas('contact_bans', ['type' => 'email', 'value' => 'spam@example.com']);
        $this->assertDatabaseMissing('contact_bans', ['type' => 'name']);
        $this->assertModelMissing($spam);
        $this->assertModelExists($keep);
    }

    public function test_bulk_action_can_keep_submissions_and_skips_duplicates(): void
    {
        $a = $this->createSubmission(['name' => 'Spam  Bot']);
        $b = $this->createSubmission(['name' => 'spam bot']);

        Livewire::actingAs($this->admin())
            ->test(ListContactSubmissions::class)
            ->callTableBulkAction('banSenders', [$a, $b], [
                'criteria' => ['name'],
                'delete_submissions' => false,
            ]);

        $this->assertSame(1, ContactBan::query()->where('type', 'name')->where('value', 'spam bot')->count());
        $this->assertModelExists($a);
    }

    public function test_banned_senders_are_silently_discarded(): void
    {
        Mail::fake();
        ContactBan::query()->create(['type' => 'ip', 'value' => '198.51.100.7']);
        ContactBan::query()->create(['type' => 'email', 'value' => 'SPAM@example.com']);
        ContactBan::query()->create(['type' => 'name', 'value' => 'Spam Bot']);

        $this->submit([], '198.51.100.7')->assertRedirect(route('contact.index'))->assertSessionHas('status');
        $this->submit(['email' => ' spam@EXAMPLE.com'])->assertSessionHas('status');
        $this->submit(['name' => 'spam   BOT'])->assertSessionHas('status');

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingSent();

        $this->submit();
        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_admin_can_add_and_lift_a_ban(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(BannedSendersWidget::class)
            ->callTableAction('addBan', data: ['type' => 'email', 'value' => ' Bad@Example.com ', 'reason' => 'manual']);

        $ban = ContactBan::query()->firstOrFail();
        $this->assertSame('bad@example.com', $ban->value);

        Livewire::actingAs($admin)
            ->test(BannedSendersWidget::class)
            ->assertCanSeeTableRecords([$ban])
            ->callTableAction('delete', $ban);

        $this->assertModelMissing($ban);
    }

    public function test_banned_senders_section_is_on_the_submissions_page(): void
    {
        ContactBan::query()->create(['type' => 'email', 'value' => 'listed@example.com']);

        $this->actingAs($this->admin())->get('/admin/contact-submissions')
            ->assertOk()
            ->assertSee('Banned senders')
            ->assertSee('listed@example.com');

        // No separate menu item / page for bans.
        $this->actingAs($this->admin())->get('/admin/contact-bans')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [], string $ip = '203.0.113.10'): TestResponse
    {
        Carbon::setTestNow(now()->subSeconds(30));
        $token = app(FormTimeTrap::class)->issue();
        Carbon::setTestNow();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('contact.submit'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'message' => 'Hello, I have a question.',
                FormTimeTrap::FIELD => $token,
                ...$overrides,
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSubmission(array $overrides = []): ContactSubmission
    {
        return ContactSubmission::query()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'A message.',
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
