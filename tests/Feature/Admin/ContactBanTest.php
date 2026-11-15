<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ContactRequests\Pages\ListContactRequests;
use App\Filament\Resources\ContactRequests\Widgets\BannedSendersWidget;
use App\Models\AuditLog;
use App\Models\ContactBan;
use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use App\Shared\Support\Spam\FormTimeTrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * "Ban senders" + "Delete selected" bulk actions on the admin Contact
 * Requests page, the "Banned senders" section below its table, and ban
 * enforcement in ContactController::store().
 */
class ContactBanTest extends TestCase
{
    use PassesFormTimeTrap;
    use RefreshDatabase;

    public function test_bulk_action_bans_by_ip_and_email_and_deletes_requests(): void
    {
        $spam = $this->createContactRequest(['email' => 'Spam@Example.com'], '198.51.100.7');
        $keep = $this->createContactRequest(['email' => 'real@example.com'], '203.0.113.1');

        Livewire::actingAs($admin = $this->admin())
            ->test(ListContactRequests::class)
            ->callTableBulkAction('banSenders', [$spam], [
                'criteria' => ['ip', 'email'],
                'reason' => 'Spam',
                'delete_requests' => true,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertDispatched('contact-bans-updated');

        $this->assertDatabaseHas('contact_bans', ['type' => 'ip', 'value' => '198.51.100.7', 'banned_by' => $admin->id]);
        $this->assertDatabaseHas('contact_bans', ['type' => 'email', 'value' => 'spam@example.com']);
        $this->assertDatabaseMissing('contact_bans', ['type' => 'name']);
        $this->assertSoftDeleted($spam);
        $this->assertNotSoftDeleted($keep);
        $this->assertTrue(AuditLog::query()->where('action', 'contact_ban.created')->exists());
    }

    public function test_bulk_action_can_keep_requests_and_skips_duplicates(): void
    {
        $a = $this->createContactRequest(['name' => 'Spam  Bot']);
        $b = $this->createContactRequest(['name' => 'spam bot']);

        Livewire::actingAs($this->admin())
            ->test(ListContactRequests::class)
            ->callTableBulkAction('banSenders', [$a, $b], [
                'criteria' => ['name'],
                'delete_requests' => false,
            ]);

        $this->assertSame(1, ContactBan::query()->where('type', 'name')->where('value', 'spam bot')->count());
        $this->assertNotSoftDeleted($a);
    }

    public function test_bulk_delete_soft_deletes_with_an_audit_entry(): void
    {
        $a = $this->createContactRequest();
        $b = $this->createContactRequest();
        $keep = $this->createContactRequest();

        Livewire::actingAs($this->admin())
            ->test(ListContactRequests::class)
            ->callTableBulkAction('deleteSelected', [$a, $b])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($keep);
        $this->assertSame(2, AuditLog::query()->where('action', 'contact_request.deleted')->count());
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
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->postJson(route('contact.submit'), $this->payload())
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSame(0, ContactRequest::query()->count());
        Mail::assertNothingSent();

        $this->submit();
        $this->assertSame(1, ContactRequest::query()->count());
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

    public function test_banned_senders_section_is_on_the_contact_requests_page(): void
    {
        ContactBan::query()->create(['type' => 'email', 'value' => 'listed@example.com']);

        $this->actingAs($this->admin())->get('/admin/contact-requests')
            ->assertOk()
            ->assertSee('Banned senders')
            ->assertSee('listed@example.com');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello, I have a question.',
            FormTimeTrap::FIELD => $this->formStartedToken(),
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [], string $ip = '203.0.113.10'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('contact.submit'), $this->payload($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createContactRequest(array $overrides = [], ?string $ip = null): ContactRequest
    {
        $contactRequest = new ContactRequest;
        $contactRequest->fill([
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
            ...$overrides,
        ]);
        $contactRequest->ip_address = $ip;
        $contactRequest->save();

        return $contactRequest->refresh();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
