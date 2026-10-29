<?php

namespace Tests\Feature\Contact;

use App\Models\ContactSubmission;
use App\Models\Role;
use App\Models\User;
use App\Shared\Support\Spam\FormTimeTrap;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

/**
 * Contact Us spam layers (ContactController::store()): honeypot, link
 * rules, time trap and the per-IP 'contact-submit' rate limiter — plus the
 * IP/user agent recorded on genuine submissions for the admin panel.
 */
class ContactSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [], string $ip = '203.0.113.10'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', 'Mozilla/5.0 (Test Browser)')
            ->post(route('contact.submit'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'message' => 'Hello, I have a question.',
                FormTimeTrap::FIELD => $this->agedToken(),
                ...$overrides,
            ]);
    }

    /**
     * Like PassesFormTimeTrap::formStartedToken(), but returns to the
     * test's current (possibly travelled) time rather than real time, so
     * the rate-limit tests' time travel isn't undone between submissions.
     */
    private function agedToken(): string
    {
        $now = now();
        Carbon::setTestNow($now->copy()->subSeconds(30));
        $token = app(FormTimeTrap::class)->issue();
        Carbon::setTestNow($now);

        return $token;
    }

    public function test_the_contact_form_carries_a_time_trap_token(): void
    {
        $this->get(route('contact.index'))->assertSee('name="'.FormTimeTrap::FIELD.'"', false);
    }

    public function test_a_genuine_submission_records_its_ip_and_user_agent(): void
    {
        $this->submit()->assertRedirect(route('contact.index'))->assertSessionHas('status');

        $submission = ContactSubmission::query()->sole();
        $this->assertSame('203.0.113.10', $submission->ip_address);
        $this->assertSame('Mozilla/5.0 (Test Browser)', $submission->user_agent);
    }

    public function test_a_submission_without_a_time_trap_token_is_silently_discarded(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->submit([FormTimeTrap::FIELD => null])
            ->assertRedirect(route('contact.index'))
            ->assertSessionHas('status');

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_submission_sent_back_too_fast_is_silently_discarded(): void
    {
        $this->submit([FormTimeTrap::FIELD => app(FormTimeTrap::class)->issue()])
            ->assertSessionHas('status');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_a_forged_time_trap_token_is_silently_discarded(): void
    {
        $this->submit([FormTimeTrap::FIELD => 'not-a-real-token'])->assertSessionHas('status');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_discarded_spam_is_logged_with_its_reason_and_ip(): void
    {
        Log::spy();

        $this->submit(['hp_website' => 'https://spam.example.com']);
        $this->submit([FormTimeTrap::FIELD => null], '198.51.100.7');

        Log::shouldHaveReceived('info')->with('Contact submission discarded as spam', Mockery::on(
            fn (array $context): bool => $context === ['reason' => 'honeypot', 'ip' => '203.0.113.10']
        ))->once();
        Log::shouldHaveReceived('info')->with('Contact submission discarded as spam', Mockery::on(
            fn (array $context): bool => $context === ['reason' => 'time-trap', 'ip' => '198.51.100.7']
        ))->once();
    }

    public function test_a_link_in_the_name_is_rejected(): void
    {
        $this->submit(['name' => 'Cheap SEO https://spam.example.com'])->assertSessionHasErrors('name');
        $this->submit(['name' => 'Visit www.spam.example.com'])->assertSessionHasErrors('name');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_more_than_three_links_in_the_message_is_rejected_but_three_are_fine(): void
    {
        $fourLinks = 'See https://a.example https://b.example http://c.example www.d.example';
        $this->submit(['message' => $fourLinks])->assertSessionHasErrors('message');
        $this->assertSame(0, ContactSubmission::query()->count());

        $threeLinks = 'See https://a.example and https://b.example and www.c.example';
        $this->submit(['message' => $threeLinks])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_an_ip_is_limited_to_three_submissions_per_ten_minutes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->submit()->assertRedirect(route('contact.index'));
        }

        $this->submit()->assertStatus(429);

        // A different visitor is unaffected.
        $this->submit([], '198.51.100.20')->assertRedirect(route('contact.index'));

        // The 10-minute window resets.
        $this->travel(11)->minutes();
        $this->submit()->assertRedirect(route('contact.index'));

        $this->assertSame(5, ContactSubmission::query()->count());
    }

    public function test_an_ip_is_limited_to_twenty_submissions_per_day(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->submit()->assertRedirect(route('contact.index'));
            $this->travel(4)->minutes();
        }

        $this->submit()->assertStatus(429);
        $this->assertSame(20, ContactSubmission::query()->count());
    }

    public function test_admin_sees_the_recorded_ip_and_user_agent(): void
    {
        $this->submit();
        $submission = ContactSubmission::query()->sole();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->actingAs($admin)->get('/admin/contact-submissions')
            ->assertOk()
            ->assertSee('203.0.113.10');

        $this->actingAs($admin)->get("/admin/contact-submissions/{$submission->getKey()}")
            ->assertOk()
            ->assertSee('203.0.113.10')
            ->assertSee('Mozilla/5.0 (Test Browser)');
    }
}
