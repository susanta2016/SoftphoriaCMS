<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Modules\Newsletter\Actions\Subscribers\ImportSubscribersAction;
use App\Modules\Newsletter\Actions\Subscribers\ManageSubscriptionAction;
use App\Modules\Newsletter\Enums\SuppressionReason;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\CreateNewsletterGroup;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\EditNewsletterGroup;
use App\Modules\Newsletter\Filament\Resources\Groups\Pages\ListNewsletterGroups;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\CreateNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\EditNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ListNewsletterSubscribers;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ViewNewsletterSubscriber;
use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Modules\Newsletter\Models\NewsletterSuppression;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\SuppressionList;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Subscribers, groups, consent and the suppression list.
 */
class SubscriberManagementTest extends TestCase
{
    use InteractsWithNewsletter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enable();
    }

    public function test_an_admin_creates_an_active_subscriber_with_consent_and_groups(): void
    {
        $general = $this->group();
        $aws = $this->group('AWS & Cloud');

        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterSubscriber::class)
            ->fillForm([
                'email' => 'Ada@Example.COM',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'groups' => [$general->id, $aws->id],
                'source' => 'event',
                'consent_confirmed' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscriber = NewsletterSubscriber::query()->sole();
        $this->assertSame('ada@example.com', $subscriber->email);
        $this->assertSame('subscribed', $subscriber->status);
        $this->assertSame('event', $subscriber->source);
        $this->assertNotNull($subscriber->consented_at);
        $this->assertEqualsCanonicalizing([$general->id, $aws->id], $subscriber->groups()->pluck('newsletter_groups.id')->all());
        $this->assertTrue(AuditLog::query()->where('action', 'newsletter_subscriber.created')->exists());
    }

    public function test_consent_is_required_to_create_a_subscriber(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterSubscriber::class)
            ->fillForm(['email' => 'no-consent@example.com', 'consent_confirmed' => false])
            ->call('create')
            ->assertHasFormErrors(['consent_confirmed']);

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_the_same_email_cannot_be_added_twice(): void
    {
        $this->subscriber('dup@example.com');

        $this->expectException(NewsletterException::class);

        app(ManageSubscriptionAction::class)->create($this->admin(), 'DUP@example.com', null, null, [], true);
    }

    public function test_a_hard_suppressed_address_cannot_be_re_added(): void
    {
        app(SuppressionList::class)->suppress('bounced@example.com', SuppressionReason::HardBounce);

        $this->expectException(NewsletterException::class);

        app(ManageSubscriptionAction::class)->create($this->admin(), 'bounced@example.com', null, null, [], true);
    }

    public function test_one_subscriber_can_be_in_several_groups_without_duplicates(): void
    {
        $a = $this->group('A');
        $b = $this->group('B');
        $subscriber = $this->subscriber('multi@example.com', groups: [$a]);

        Livewire::actingAs($this->admin())
            ->test(ListNewsletterSubscribers::class)
            ->callTableBulkAction('addToGroup', [$subscriber], data: ['group_id' => $b->id]);

        $this->assertSame(1, NewsletterSubscriber::query()->count());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $subscriber->groups()->pluck('newsletter_groups.id')->all());
    }

    public function test_the_edit_form_cannot_change_email_or_status(): void
    {
        $subscriber = $this->subscriber('fixed@example.com', ['status' => 'unsubscribed']);

        Livewire::actingAs($this->admin())
            ->test(EditNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->fillForm(['first_name' => 'Renamed', 'email' => 'other@example.com', 'status' => 'subscribed'])
            ->call('save');

        $subscriber->refresh();
        $this->assertSame('Renamed', $subscriber->first_name);
        $this->assertSame('fixed@example.com', $subscriber->email);
        $this->assertSame('unsubscribed', $subscriber->status);
    }

    public function test_unsubscribe_and_resubscribe_with_recorded_consent(): void
    {
        $subscriber = $this->subscriber('flip@example.com');
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->callAction('unsubscribe');

        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
        $this->assertSame(SuppressionReason::Unsubscribed, NewsletterSuppression::query()->sole()->reason);

        Livewire::actingAs($admin)
            ->test(ViewNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->callAction('resubscribe', data: ['consent_note' => 'Asked by email on 30 Oct'])
            ->assertHasNoActionErrors();

        $this->assertSame('subscribed', $subscriber->fresh()->status);
        $this->assertDatabaseCount('newsletter_suppressions', 0);
        $this->assertTrue(AuditLog::query()->where('action', 'newsletter_subscriber.resubscribed')->exists());
    }

    public function test_a_bounced_subscriber_cannot_be_resubscribed_until_the_suppression_is_lifted(): void
    {
        $subscriber = $this->subscriber('hard@example.com');
        app(SuppressionList::class)->suppress('hard@example.com', SuppressionReason::HardBounce, 'mailbox does not exist');
        $admin = $this->admin();
        $manage = app(ManageSubscriptionAction::class);

        try {
            $manage->resubscribe($subscriber->fresh(), $admin, 'They asked');
            $this->fail('A hard-bounced address must not be resubscribed directly.');
        } catch (NewsletterException) {
        }

        $manage->liftSuppression($subscriber->fresh(), $admin, 'They fixed their mailbox');

        // Lifting is not consent.
        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
        $this->assertSame(0, app(RecipientResolver::class)->count([$this->group()->id]));

        $manage->resubscribe($subscriber->fresh(), $admin, 'Confirmed by phone');
        $this->assertSame('subscribed', $subscriber->fresh()->status);
    }

    public function test_admin_suppression_blocks_sending_whatever_the_groups(): void
    {
        $group = $this->group();
        $subscriber = $this->subscriber('vip@example.com', groups: [$group]);

        Livewire::actingAs($this->admin())
            ->test(ViewNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->callAction('suppress', data: ['note' => 'Asked never to be contacted']);

        $this->assertSame('suppressed', $subscriber->fresh()->status);
        $this->assertSame(0, app(RecipientResolver::class)->count([$group->id]));
    }

    public function test_an_unsubscribe_never_downgrades_a_complaint(): void
    {
        $list = app(SuppressionList::class);
        $list->suppress('angry@example.com', SuppressionReason::Complaint);
        $list->suppress('angry@example.com', SuppressionReason::Unsubscribed);

        $this->assertSame(SuppressionReason::Complaint, NewsletterSuppression::query()->sole()->reason);
        $this->assertTrue($list->blocksResubscribe('angry@example.com'));
    }

    public function test_the_footer_signup_respects_the_suppression_list_and_double_opt_in_lifts_an_unsubscribe(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        $list = app(SuppressionList::class);

        // Hard bounce with no subscriber row: the form refuses it.
        $list->suppress('ghost@example.com', SuppressionReason::HardBounce);
        $this->post('/newsletter/subscribe', ['email' => 'ghost@example.com'])->assertSessionHas('newsletter_status', fn (string $message): bool => str_contains($message, 'unable'));
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'ghost@example.com']);

        // A plain unsubscribe: the form allows it and confirming lifts it.
        $this->subscriber('back@example.com', ['status' => 'unsubscribed']);
        $list->suppress('back@example.com', SuppressionReason::Unsubscribed);
        $this->post('/newsletter/subscribe', ['email' => 'back@example.com']);
        $subscriber = NewsletterSubscriber::query()->where('email', 'back@example.com')->sole();
        $this->assertSame('pending', $subscriber->status);

        $subscriber->forceFill(['confirmation_token_hash' => hash('sha256', 'raw-token'), 'confirmation_expires_at' => now()->addHour()])->save();
        $this->get('/newsletter/confirm/raw-token')->assertOk();

        $this->assertSame('subscribed', $subscriber->fresh()->status);
        $this->assertFalse($list->isSuppressed('back@example.com'));
    }

    public function test_csv_import_creates_updates_and_never_resubscribes(): void
    {
        $group = $this->group();
        $this->subscriber('existing@example.com', ['first_name' => null]);
        $this->subscriber('left@example.com', ['status' => 'unsubscribed']);
        app(SuppressionList::class)->suppress('blocked@example.com', SuppressionReason::Complaint);

        $path = tempnam(sys_get_temp_dir(), 'nl').'.csv';
        file_put_contents($path, "\xEF\xBB\xBFEmail,First_Name,last_name,phone\n"
            ."new@example.com,New,Person,123\n"
            ."EXISTING@example.com,Existing,Name,\n"
            ."left@example.com,Left,Already,\n"
            ."blocked@example.com,Blocked,Address,\n"
            ."not-an-email,Bad,Row,\n"
            ."new@example.com,Dupe,InFile,\n");

        $result = app(ImportSubscribersAction::class)->handle($this->admin(), $path, [$group->id], true);
        @unlink($path);

        $this->assertSame(['created' => 1, 'updated' => 2, 'skipped' => 2, 'invalid' => 1], $result);
        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'new@example.com')->value('status'));
        $this->assertSame('Existing', NewsletterSubscriber::query()->where('email', 'existing@example.com')->value('first_name'));
        $this->assertSame('unsubscribed', NewsletterSubscriber::query()->where('email', 'left@example.com')->value('status'));
        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => 'blocked@example.com']);
        $this->assertSame(2, $group->subscribers()->count());
    }

    public function test_csv_import_requires_consent(): void
    {
        $this->expectException(NewsletterException::class);

        app(ImportSubscribersAction::class)->handle($this->admin(), __FILE__, [], false);
    }

    public function test_subscribers_can_be_searched_and_filtered(): void
    {
        $group = $this->group();
        $match = $this->subscriber('grace@example.com', ['first_name' => 'Grace'], [$group]);
        $other = $this->subscriber('linus@example.com', ['first_name' => 'Linus', 'status' => 'unsubscribed']);

        Livewire::actingAs($this->admin())
            ->test(ListNewsletterSubscribers::class)
            ->searchTable('Grace')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable(null)
            ->filterTable('status', 'unsubscribed')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$match])
            ->resetTableFilters()
            ->filterTable('groups', [$group->id])
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_groups_are_managed_and_deleting_one_keeps_its_subscribers(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateNewsletterGroup::class)
            ->fillForm(['name' => 'Tools & Resources', 'description' => 'Tool launches'])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = NewsletterGroup::query()->sole();
        $subscriber = $this->subscriber('member@example.com', groups: [$group]);

        Livewire::actingAs($admin)
            ->test(EditNewsletterGroup::class, ['record' => $group->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($group);
        $this->assertModelExists($subscriber);
        $this->assertSame(0, $subscriber->groups()->count());
    }

    public function test_a_group_used_by_a_scheduled_campaign_cannot_be_deleted(): void
    {
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        $campaign->forceFill(['status' => 'scheduled'])->save();

        Livewire::actingAs($this->admin())
            ->test(ListNewsletterGroups::class)
            ->callTableAction('delete', $group);

        $this->assertModelExists($group);
    }
}
