<?php

namespace Tests\Feature\NewsletterMarketing;

use App\Modules\Newsletter\Actions\SendTestEmailAction;
use App\Modules\Newsletter\Enums\TemplateStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\CreateNewsletterTemplate;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\EditNewsletterTemplate;
use App\Modules\Newsletter\Filament\Resources\Templates\Pages\ListNewsletterTemplates;
use App\Modules\Newsletter\Filament\Support\NewsletterPreviewActions;
use App\Modules\Newsletter\Mail\NewsletterCampaignMail;
use App\Modules\Newsletter\Models\NewsletterCampaignRecipient;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\NewsletterRenderer;
use App\Modules\Newsletter\Support\StarterTemplate;
use App\Modules\Newsletter\Support\TemplateVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reusable templates and the renderer: allowlisted placeholders only, no
 * code execution, escaping, sanitizing, CSS inlining, required unsubscribe
 * link, preview and test email.
 */
class TemplateRenderingTest extends TestCase
{
    use InteractsWithNewsletter;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enable();
    }

    public function test_an_admin_creates_a_template_from_the_starter_layout(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterTemplate::class)
            ->assertFormSet(['html_content' => StarterTemplate::html()])
            ->fillForm(['name' => 'Product Release Announcement', 'subject' => 'New release', 'status' => TemplateStatus::Active->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = NewsletterTemplate::query()->sole();
        $this->assertSame('Product Release Announcement', $template->name);
        $this->assertStringContainsString('{{ campaign_content }}', $template->html_content);
    }

    public function test_a_template_without_an_unsubscribe_link_is_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterTemplate::class)
            ->fillForm(['name' => 'No footer', 'status' => 'active', 'html_content' => '<html><body>{{ campaign_content }}</body></html>'])
            ->call('create')
            ->assertHasFormErrors(['html_content']);
    }

    public function test_unknown_placeholders_are_rejected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateNewsletterTemplate::class)
            ->fillForm(['name' => 'Sneaky', 'status' => 'active', 'html_content' => '<p>{{ app.key }} {{ user.password }}</p>{{ footer }}'])
            ->call('create')
            ->assertHasFormErrors(['html_content']);

        $this->assertSame(['app.key', 'user.password'], TemplateVariables::unknownIn('{{ app.key }} {{ user.password }} {{ site_name }}'));
    }

    public function test_an_admin_edits_and_duplicates_a_template(): void
    {
        $template = $this->template(['name' => 'Monthly Newsletter']);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(EditNewsletterTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['description' => 'Our monthly round-up'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Our monthly round-up', $template->fresh()->description);

        Livewire::actingAs($admin)
            ->test(ListNewsletterTemplates::class)
            ->callTableAction('duplicate', $template);

        $copy = NewsletterTemplate::query()->where('name', 'Copy of Monthly Newsletter')->sole();
        $this->assertSame(TemplateStatus::Draft, $copy->status);
        $this->assertSame($template->html_content, $copy->html_content);
    }

    public function test_the_preview_renders_the_sample_subscriber(): void
    {
        $template = $this->template();

        Livewire::actingAs($this->admin())
            ->test(EditNewsletterTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview');

        // The modal body itself (Filament renders action modals outside the
        // page HTML a Livewire test sees).
        $data = NewsletterPreviewActions::render($template);
        $this->assertStringContainsString('Hello Alex,', $data['html']);

        $modal = view('newsletter-marketing::filament.preview', $data)->render();
        $this->assertStringContainsString('Desktop', $modal);
        $this->assertStringContainsString('Mobile', $modal);
        $this->assertStringContainsString('sandbox=""', $modal);
        $this->assertStringContainsString('Plain-text version', $modal);
    }

    public function test_a_test_email_goes_only_to_the_given_addresses(): void
    {
        Mail::fake();
        $template = $this->template();
        $this->subscriber('real-subscriber@example.com', groups: [$this->group()]);

        $count = app(SendTestEmailAction::class)->handle($template, $this->admin(), ['qa@example.com']);

        $this->assertSame(1, $count);
        Mail::assertSent(NewsletterCampaignMail::class, 1);
        Mail::assertSent(NewsletterCampaignMail::class, fn (NewsletterCampaignMail $mail): bool => $mail->hasTo('qa@example.com')
            && str_starts_with($mail->subjectLine, '[TEST] ')
            && $mail->oneClickUnsubscribeUrl === null);
        $this->assertDatabaseCount('newsletter_campaign_recipients', 0);
    }

    public function test_test_emails_are_limited_in_count_and_validated(): void
    {
        $template = $this->template();

        $this->expectException(NewsletterException::class);

        app(SendTestEmailAction::class)->handle($template, $this->admin(), array_map(fn (int $i): string => "t{$i}@example.com", range(1, 6)));
    }

    public function test_templates_cannot_execute_code_or_reach_application_objects(): void
    {
        // Each snippet would print a reversed word only if it actually ran.
        $template = $this->template(['html_content' => '<html><body>'
            .'<p>{{ phpinfo() }}</p><p>@php echo strrev("dexatlb"); @endphp</p><p><?php echo strrev("dexatphp"); ?></p>'
            .'<p>{!! config("app.key") !!}</p><p>{{ config.app.key }}</p><p>{{ strrev("dexatvar") }}</p>'
            .'{{ footer }}</body></html>']);
        $campaign = $this->campaign([$this->group()], attributes: ['template' => $template]);

        $html = app(NewsletterRenderer::class)->preview($campaign, $template, NewsletterRenderer::sampleSubscriber())->html;

        foreach (['bltaxed', 'phptaxed', 'vartaxed', 'PHP Version', (string) config('app.key')] as $executed) {
            $this->assertStringNotContainsString($executed, $html);
        }

        // Blade syntax stays inert text.
        $this->assertStringContainsString('@php echo strrev', $html);
    }

    public function test_subscriber_and_campaign_values_are_escaped(): void
    {
        $template = $this->template();
        $campaign = $this->campaign([$this->group()], attributes: [
            'template' => $template,
            'subject' => '<script>alert(1)</script> news',
            'cta_text' => '"><img src=x onerror=alert(1)>',
        ]);

        $html = app(NewsletterRenderer::class)->preview($campaign, $template, ['first_name' => '<b onmouseover=alert(1)>Eve</b>'])->html;

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<b onmouseover', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;b onmouseover=alert(1)&gt;Eve&lt;/b&gt;', $html);
    }

    public function test_dangerous_html_in_templates_and_content_is_stripped(): void
    {
        $template = $this->template(['html_content' => '<html><head><script>steal()</script><link rel="stylesheet" href="https://evil.test/x.css"></head><body>'
            .'<a href="javascript:alert(1)" onclick="steal()">x</a><a href="java&#x09;script:alert(2)">y</a>'
            .'<iframe src="https://evil.test"></iframe><form action="https://evil.test"><input name="p"></form>'
            .'<div style="background:url(javascript:alert(1))">z</div><img src="data:text/html;base64,AAAA"><img src="data:image/png;base64,AAAA">'
            .'{{ campaign_content }}{{ footer }}</body></html>']);
        $campaign = $this->campaign([$this->group()], attributes: [
            'template' => $template,
            'content' => '<p onclick="x()">Hi</p><script>alert(3)</script>',
        ]);

        $html = app(NewsletterRenderer::class)->snapshot($campaign, $template, tracking: false)->html;

        foreach (['<script', 'steal()', 'javascript:', 'onclick', '<iframe', '<form', '<input', '<link', 'data:text/html', 'alert(3)'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $html, "Found {$needle}");
        }

        $this->assertStringContainsString('data:image/png;base64,AAAA', $html);
        $this->assertStringContainsString('<p>Hi</p>', $html);
    }

    public function test_css_is_inlined_and_media_queries_are_kept(): void
    {
        $template = $this->template();
        $html = app(NewsletterRenderer::class)->snapshot($this->campaign([$this->group()], attributes: ['template' => $template]), $template)->html;

        $this->assertMatchesRegularExpression('/class="nl-content"[^>]*style="[^"]*padding: ?32px/', $html);
        $this->assertStringContainsString('@media only screen and (max-width: 620px)', $html);
    }

    public function test_a_template_without_an_unsubscribe_link_still_gets_the_footer_when_sent(): void
    {
        // Saved directly (bypassing form validation), as an older row might be.
        $template = $this->template(['html_content' => '<html><body><p>{{ campaign_content }}</p></body></html>']);
        $html = app(NewsletterRenderer::class)->preview($this->campaign([$this->group()], attributes: ['template' => $template]), $template, [], 'https://example.com/u/abc')->html;

        $this->assertStringContainsString('https://example.com/u/abc', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
    }

    public function test_the_preheader_and_fallbacks_render(): void
    {
        $template = $this->template();
        $campaign = $this->campaign([$this->group()], attributes: ['template' => $template, 'preheader' => 'Inbox preview text']);

        $email = app(NewsletterRenderer::class)->preview($campaign, $template, ['first_name' => '']);

        $this->assertStringContainsString('Inbox preview text', $email->html);
        $this->assertStringContainsString('Hello there,', $email->html);
        $this->assertStringContainsString('Hello there,', $email->text);
        $this->assertStringNotContainsString('Inbox preview text', $email->text);
    }

    public function test_click_tracking_rewrites_links_but_not_the_unsubscribe_link(): void
    {
        $template = $this->template();
        $campaign = $this->campaign([$this->group()], attributes: ['template' => $template, 'track_clicks' => true, 'track_opens' => true]);

        $snapshot = app(NewsletterRenderer::class)->snapshot($campaign, $template);
        $recipient = new NewsletterCampaignRecipient(['email' => 'a@example.com', 'first_name' => 'A', 'token' => str_repeat('a', 48)]);
        $email = app(NewsletterRenderer::class)->personalize($snapshot, $recipient);

        $this->assertContains('https://example.com/launch', $snapshot->links);
        $this->assertStringNotContainsString('href="https://example.com/launch"', $email->html);
        $this->assertStringContainsString('/n/c/'.str_repeat('a', 48).'/', $email->html);
        $this->assertStringContainsString('/n/o/'.str_repeat('a', 48), $email->html);
        $this->assertStringContainsString(route('newsletter.unsubscribe', ['token' => str_repeat('a', 48)]), $email->html);
        // The plain-text part keeps the real links.
        $this->assertStringContainsString('https://example.com/launch', $email->text);
    }

    public function test_a_template_used_by_a_campaign_cannot_be_deleted(): void
    {
        $template = $this->template();
        $this->campaign([$this->group()], attributes: ['template' => $template]);

        Livewire::actingAs($this->admin())
            ->test(ListNewsletterTemplates::class)
            ->callTableAction('delete', $template);

        $this->assertModelExists($template);
    }
}
