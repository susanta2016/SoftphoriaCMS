<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Enums\UserStatus;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Actions\Campaigns\PrepareCampaignContent;
use App\Modules\Newsletter\Actions\Campaigns\ScheduleCampaignAction;
use App\Modules\Newsletter\Enums\CampaignRecipientStatus;
use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\CreateNewsletterSubscriber;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ListNewsletterSubscribers;
use App\Modules\Newsletter\Filament\Resources\Subscribers\Pages\ViewNewsletterSubscriber;
use App\Modules\Newsletter\Jobs\SendCampaignBatchJob;
use App\Modules\Newsletter\Jobs\StartCampaignJob;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Mail\NewsletterMailer;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Services\CampaignLifecycle;
use App\Modules\Newsletter\Services\NewsletterDeliveryService;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\RenderedEmail;
use Database\Seeders\EmailTemplateSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * NEWSLETTER_MARKETING_ENABLED controls the whole module, enforced
 * server-side everywhere — and deliberately leaves the pre-existing footer
 * signup and recipients' unsubscribe links working.
 */
class NewsletterFeatureFlagTest extends TestCase
{
    use InteractsWithNewsletter;
    use RefreshDatabase;

    public function test_the_module_is_disabled_by_default(): void
    {
        $backup = [$_ENV['NEWSLETTER_MARKETING_ENABLED'] ?? null, $_SERVER['NEWSLETTER_MARKETING_ENABLED'] ?? null, getenv('NEWSLETTER_MARKETING_ENABLED')];
        unset($_ENV['NEWSLETTER_MARKETING_ENABLED'], $_SERVER['NEWSLETTER_MARKETING_ENABLED']);
        putenv('NEWSLETTER_MARKETING_ENABLED');

        try {
            $this->assertFalse((require config_path('newsletter.php'))['enabled']);
        } finally {
            [$env, $server, $getenv] = $backup;
            if ($env !== null) {
                $_ENV['NEWSLETTER_MARKETING_ENABLED'] = $env;
            }
            if ($server !== null) {
                $_SERVER['NEWSLETTER_MARKETING_ENABLED'] = $server;
            }
            if ($getenv !== false) {
                putenv("NEWSLETTER_MARKETING_ENABLED={$getenv}");
            }
        }

        $this->assertFalse((bool) config('newsletter.enabled'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminPaths(): array
    {
        return [
            'dashboard' => ['/admin/newsletter'],
            'groups' => ['/admin/newsletter/groups'],
            'templates' => ['/admin/newsletter/templates'],
            'campaigns' => ['/admin/newsletter/campaigns'],
            'create campaign' => ['/admin/newsletter/campaigns/create'],
            'scheduled campaigns' => ['/admin/newsletter/campaigns/scheduled'],
            'campaign reports' => ['/admin/newsletter/campaigns/reports'],
            'settings' => ['/admin/newsletter/settings'],
        ];
    }

    #[DataProvider('adminPaths')]
    public function test_every_admin_url_is_a_404_when_disabled(string $path): void
    {
        $this->actingAs($this->admin())->get($path)->assertNotFound();
    }

    #[DataProvider('adminPaths')]
    public function test_every_admin_url_works_when_enabled(string $path): void
    {
        $this->enable();

        $this->actingAs($this->admin())->get($path)->assertOk();
    }

    public function test_the_record_pages_are_also_404_when_disabled(): void
    {
        $this->enable();
        $group = $this->group();
        $campaign = $this->campaign([$group]);
        $subscriber = $this->subscriber('a@example.com');
        config(['newsletter.enabled' => false]);

        $admin = $this->admin();
        $this->actingAs($admin)->get("/admin/newsletter/campaigns/{$campaign->id}")->assertNotFound();
        $this->actingAs($admin)->get("/admin/newsletter/groups/{$group->id}/edit")->assertNotFound();
        // The subscriber list predates the module and is never gated.
        $this->actingAs($admin)->get("/admin/newsletter-subscribers/{$subscriber->id}")->assertOk();
    }

    public function test_the_subscriber_list_stays_available_without_groups_when_disabled(): void
    {
        $subscriber = $this->subscriber('kept@example.com');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/newsletter-subscribers')->assertOk()->assertSee('kept@example.com');
        $this->actingAs($admin)->get('/admin/newsletter-subscribers/create')->assertOk()->assertDontSee('Group membership never overrides');
        $this->actingAs($admin)->get("/admin/newsletter-subscribers/{$subscriber->id}/edit")->assertOk();

        Livewire::actingAs($admin)
            ->test(ListNewsletterSubscribers::class)
            ->assertTableColumnHidden('groups.name')
            ->assertTableBulkActionHidden('addToGroup');
    }

    public function test_subscribers_can_be_added_and_unsubscribed_while_disabled(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateNewsletterSubscriber::class)
            ->fillForm(['email' => 'offline@example.com', 'first_name' => 'Off', 'consent_confirmed' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscriber = NewsletterSubscriber::query()->where('email', 'offline@example.com')->sole();
        $this->assertSame('subscribed', $subscriber->status);

        Livewire::actingAs($admin)
            ->test(ViewNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])
            ->callAction('unsubscribe');

        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
    }

    // Filament builds the navigation once per application instance, so the
    // disabled and enabled menus are checked in separate tests.
    public function test_the_navigation_group_is_hidden_when_disabled(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertDontSee('Newsletter Marketing')
            ->assertDontSee('Scheduled Campaigns')
            ->assertDontSee('Subscriber Groups')
            // The pre-module subscriber list, as a top-level item.
            ->assertSee('Newsletter Subscribers')
            ->assertSee('/admin/newsletter-subscribers', false);
    }

    public function test_the_navigation_group_is_shown_when_enabled(): void
    {
        $this->enable();

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertDontSee('Newsletter Subscribers')
            ->assertSee('Newsletter Marketing')
            ->assertSee('Subscriber Groups')
            ->assertSee('Email Templates')
            ->assertSee('Scheduled Campaigns')
            ->assertSee('Campaign Reports')
            ->assertSee('Newsletter Settings');
    }

    public function test_non_admins_cannot_reach_the_module_even_when_enabled(): void
    {
        $this->enable();
        $member = User::factory()->create(['status' => UserStatus::Active->value]);

        $this->actingAs($member)->get('/admin/newsletter/campaigns')->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->enable();

        $this->get('/admin/newsletter/campaigns')->assertRedirect();
    }

    public function test_the_scheduled_dispatcher_does_nothing_when_disabled(): void
    {
        $this->enable();
        Queue::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(ScheduleCampaignAction::class)
            ->handle($campaign, $this->admin(), now('Asia/Kolkata')->addHour()->format('Y-m-d H:i'), 'Asia/Kolkata');
        $this->travel(2)->hours();

        config(['newsletter.enabled' => false]);
        $this->artisan('newsletter:dispatch-due')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(CampaignStatus::Scheduled, $campaign->fresh()->status);
    }

    public function test_queued_jobs_exit_without_sending_when_disabled(): void
    {
        $this->enable();
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(PrepareCampaignContent::class)->snapshot($campaign);
        $campaign->forceFill(['status' => CampaignStatus::Queued, 'queued_at' => now(), 'send_chain' => 'chain-1'])->save();

        config(['newsletter.enabled' => false]);

        (new StartCampaignJob($campaign->id, 'chain-1'))->handle(app(RecipientResolver::class), app(CampaignLifecycle::class));
        $this->assertSame(CampaignStatus::Queued, $campaign->fresh()->status);

        // Even a campaign already mid-send stops.
        $campaign->forceFill(['status' => CampaignStatus::Sending, 'recipients_snapshot_at' => now()])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        app()->call([new SendCampaignBatchJob($campaign->id, 'chain-1'), 'handle']);

        Mail::assertNothingSent();
        $this->assertSame(CampaignRecipientStatus::Pending, $campaign->recipients()->sole()->status);
    }

    public function test_the_delivery_service_and_mailer_refuse_when_disabled(): void
    {
        $this->enable();
        Mail::fake();
        $group = $this->group();
        $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
        app(RecipientResolver::class)->snapshot($campaign);
        $recipient = NewsletterCampaignRecipient::query()->sole();

        config(['newsletter.enabled' => false]);

        try {
            app(NewsletterDeliveryService::class)->deliver($campaign, $recipient, new RenderedEmail('<p>x</p>', 'x'));
            $this->fail('Expected the delivery service to refuse.');
        } catch (NewsletterException) {
        }

        $this->expectException(NewsletterException::class);

        try {
            app(NewsletterMailer::class)->send('a@example.com', new NewsletterCampaignMail('s', '<p>x</p>', 'x', 'news@example.com', 'News'));
        } finally {
            Mail::assertNothingSent();
            $this->assertSame(CampaignRecipientStatus::Pending, $recipient->fresh()->status);
        }
    }

    public function test_the_existing_footer_signup_keeps_working_when_disabled(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();

        $this->post('/newsletter/subscribe', ['email' => 'visitor@example.com'])->assertRedirect();

        $this->assertSame('pending', NewsletterSubscriber::query()->where('email', 'visitor@example.com')->value('status'));
    }

    public function test_unsubscribe_links_keep_working_when_disabled(): void
    {
        $this->enable();
        $group = $this->group();
        $subscriber = $this->subscriber('a@example.com', groups: [$group]);
        $campaign = $this->campaign([$group]);
        app(RecipientResolver::class)->snapshot($campaign);
        $token = NewsletterCampaignRecipient::query()->sole()->token;

        config(['newsletter.enabled' => false]);

        $this->post("/newsletter/unsubscribe/{$token}")->assertOk()->assertSee('unsubscribed');
        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
    }

    public function test_the_rest_of_the_admin_panel_is_unaffected_when_disabled(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->actingAs($this->admin())->get('/admin/users')->assertOk();
    }
}
