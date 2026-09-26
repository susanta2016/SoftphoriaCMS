<?php

namespace Tests\Feature\Contact;

use App\Models\ContactRequest;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Shared\Mail\TemplatedNotificationMail;
use App\Shared\Support\Features\Features;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * The quick-contact popup and the lead context (page, form, button,
 * referrer) every contact submission now carries.
 */
class ContactLeadContextTest extends TestCase
{
    use PassesFormTimeTrap;
    use RefreshDatabase;

    public function test_the_popup_is_on_public_pages_but_not_the_contact_page(): void
    {
        $this->get('/services')->assertOk()->assertSee('data-contact-modal', false)->assertSee('name="lead_source" value="popup"', false);
        $this->get('/contact')->assertOk()->assertDontSee('data-contact-modal', false);
    }

    public function test_the_popup_follows_its_feature_switch(): void
    {
        app(Features::class)->set('contact_popup', false);

        $this->get('/services')->assertDontSee('data-contact-modal', false);
    }

    public function test_every_contact_form_carries_lead_fields_with_its_own_source(): void
    {
        $this->get('/contact')->assertSee('name="lead_source" value="contact_page"', false)->assertSee('name="page_url"', false);
        $this->get('/services')->assertSee('name="lead_source" value="widget"', false);
    }

    public function test_a_popup_submission_stores_the_lead_context(): void
    {
        $this->postJson('/contact', $this->payload([
            'page_url' => url('/services/cloud-devops'),
            'page_title' => 'Cloud & DevOps — Softphoria',
            'lead_source' => 'popup',
            'lead_cta' => '  Get a free   consultation ',
            'referrer' => 'https://www.google.com/',
        ]))->assertOk();

        $lead = ContactRequest::query()->sole();
        $this->assertSame(url('/services/cloud-devops'), $lead->page_url);
        $this->assertSame('Cloud & DevOps — Softphoria', $lead->page_title);
        $this->assertSame('popup', $lead->source);
        $this->assertSame('Get a free consultation', $lead->cta_label);
        $this->assertSame('https://www.google.com/', $lead->referrer);
    }

    public function test_untrusted_lead_values_are_rejected_or_cleaned(): void
    {
        $this->post('/contact', $this->payload([
            'page_url' => 'https://evil.example/phish',
            'lead_source' => 'hacker',
            'lead_cta' => '<script>alert(1)</script>Click',
            'referrer' => 'javascript:alert(1)',
        ]));

        $lead = ContactRequest::query()->sole();
        $this->assertNull($lead->page_url, 'an off-site page URL is never stored');
        $this->assertNull($lead->source);
        $this->assertSame('alert(1)Click', $lead->cta_label);
        $this->assertNull($lead->referrer);
    }

    public function test_the_referer_header_is_the_same_site_fallback_for_page_url(): void
    {
        $this->withHeader('Referer', url('/blog/some-post'))->post('/contact', $this->payload());
        $this->assertSame(url('/blog/some-post'), ContactRequest::query()->sole()->page_url);
    }

    public function test_the_admin_notification_includes_the_lead_context(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->postJson('/contact', $this->payload([
            'page_url' => url('/services/web-development'),
            'lead_source' => 'popup',
            'lead_cta' => 'Start a Conversation',
        ]))->assertOk();

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) use ($admin): bool {
            $html = $mail->render();

            return $mail->hasTo($admin->email)
                && str_contains($html, url('/services/web-development'))
                && str_contains($html, 'Call-to-action popup')
                && str_contains($html, 'Start a Conversation');
        });
    }

    public function test_the_seeder_upgrades_an_unedited_previous_admin_body_but_keeps_an_edited_one(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = EmailTemplate::query()->where(['notification_key' => 'contact_form_submitted', 'recipient_type' => 'admin'])->sole();

        $previous = config('email_templates.contact_form_submitted.previous_default_html_body.admin');
        $template->forceFill(['html_body' => $previous])->save();
        $this->seed(EmailTemplateSeeder::class);
        $this->assertStringContainsString('{{page_url}}', $template->fresh()->html_body);

        $template->forceFill(['html_body' => '<p>My own wording {{name}}</p>'])->save();
        $this->seed(EmailTemplateSeeder::class);
        $this->assertSame('<p>My own wording {{name}}</p>', $template->fresh()->html_body);
    }

    public function test_admin_can_see_the_lead_source(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
        $this->postJson('/contact', $this->payload(['page_url' => url('/services/cloud-devops'), 'lead_source' => 'popup', 'lead_cta' => 'Get a free consultation']));
        $lead = ContactRequest::query()->sole();

        $this->actingAs($admin)->get('/admin/contact-requests')->assertOk()->assertSee('/services/cloud-devops');
        $this->actingAs($admin)->get("/admin/contact-requests/{$lead->getRouteKey()}")
            ->assertOk()
            ->assertSee('Lead source')
            ->assertSee('Call-to-action popup')
            ->assertSee('Get a free consultation');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return [
            'name' => 'Jane Lead',
            'email' => 'jane@example.com',
            'message' => 'Interested in a project.',
            '_started' => $this->formStartedToken(),
            ...$extra,
        ];
    }
}
