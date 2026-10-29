<?php

namespace Tests\Feature\InspirationalResources;

use App\Models\Role;
use App\Models\User;
use App\Modules\InspirationalResources\Models\ResourceSubmission;
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
 * Inspirational Resources "Submit Your Writing" spam layers
 * (InspirationalResourceSubmissionController::store()): honeypot, link
 * rules, time trap and the per-IP 'resource-submit' rate limiter — plus the
 * IP/user agent recorded on genuine submissions for the admin panel.
 */
class ResourceSubmissionSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = [], string $ip = '203.0.113.10'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', 'Mozilla/5.0 (Test Browser)')
            ->post(route('inspirational-resources.submit'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'subject' => 'A book that helped me',
                'category' => 'Books',
                'theme' => 'Gratitude',
                'message' => 'This book changed how I see things.',
                FormTimeTrap::FIELD => $this->agedToken(),
                ...$overrides,
            ]);
    }

    /**
     * A token issued 30 s before the test's current (possibly travelled)
     * time, without resetting that time travel afterwards.
     */
    private function agedToken(): string
    {
        $now = now();
        Carbon::setTestNow($now->copy()->subSeconds(30));
        $token = app(FormTimeTrap::class)->issue();
        Carbon::setTestNow($now);

        return $token;
    }

    public function test_the_form_carries_a_time_trap_token_and_a_honeypot(): void
    {
        $this->get(route('inspirational-resources.create'))
            ->assertSee('name="'.FormTimeTrap::FIELD.'"', false)
            ->assertSee('name="hp_website"', false);
    }

    public function test_a_genuine_submission_records_its_ip_and_user_agent(): void
    {
        $this->submit()->assertRedirect(route('inspirational-resources.create'))->assertSessionHas('status');

        $submission = ResourceSubmission::query()->sole();
        $this->assertSame('203.0.113.10', $submission->ip_address);
        $this->assertSame('Mozilla/5.0 (Test Browser)', $submission->user_agent);
    }

    public function test_a_filled_honeypot_is_silently_discarded_without_any_email(): void
    {
        Mail::fake();
        $this->seed(EmailTemplateSeeder::class);

        $this->submit(['hp_website' => 'https://spam.example.com'])
            ->assertRedirect(route('inspirational-resources.create'))
            ->assertSessionHas('status');

        $this->assertSame(0, ResourceSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_missing_forged_or_too_fast_time_trap_tokens_are_silently_discarded(): void
    {
        $this->submit([FormTimeTrap::FIELD => null])->assertSessionHas('status');
        $this->submit([FormTimeTrap::FIELD => 'not-a-real-token'])->assertSessionHas('status');
        $this->submit([FormTimeTrap::FIELD => app(FormTimeTrap::class)->issue()], '198.51.100.30')->assertSessionHas('status');

        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_discarded_spam_is_logged_with_its_reason_and_ip(): void
    {
        Log::spy();

        $this->submit(['hp_website' => 'filled']);
        $this->submit([FormTimeTrap::FIELD => null], '198.51.100.7');

        Log::shouldHaveReceived('info')->with('Inspirational resource submission discarded as spam', Mockery::on(
            fn (array $context): bool => $context === ['reason' => 'honeypot', 'ip' => '203.0.113.10']
        ))->once();
        Log::shouldHaveReceived('info')->with('Inspirational resource submission discarded as spam', Mockery::on(
            fn (array $context): bool => $context === ['reason' => 'time-trap', 'ip' => '198.51.100.7']
        ))->once();
    }

    public function test_links_in_the_name_subject_or_custom_category_are_rejected(): void
    {
        $this->submit(['name' => 'Cheap SEO https://spam.example.com'])->assertSessionHasErrors('name');
        $this->submit(['subject' => 'Visit www.spam.example.com'])->assertSessionHasErrors('subject');
        $this->submit(['category' => 'Other', 'category_other' => 'http://spam.example.com'])->assertSessionHasErrors('category_other');

        $this->assertSame(0, ResourceSubmission::query()->count());
    }

    public function test_more_than_three_links_in_the_message_is_rejected_but_a_reference_url_is_fine(): void
    {
        $this->submit(['message' => 'https://a.example https://b.example http://c.example www.d.example'])
            ->assertSessionHasErrors('message');
        $this->assertSame(0, ResourceSubmission::query()->count());

        $this->submit(['reference_url' => 'https://example.com/the-book'])->assertSessionDoesntHaveErrors();
        $this->assertSame('https://example.com/the-book', ResourceSubmission::query()->sole()->reference_url);
    }

    public function test_an_ip_is_limited_to_three_submissions_per_ten_minutes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->submit()->assertRedirect(route('inspirational-resources.create'));
        }

        $this->submit()->assertStatus(429);
        $this->submit([], '198.51.100.20')->assertRedirect(route('inspirational-resources.create'));

        $this->travel(11)->minutes();
        $this->submit()->assertRedirect(route('inspirational-resources.create'));

        $this->assertSame(5, ResourceSubmission::query()->count());
    }

    public function test_an_ip_is_limited_to_twenty_submissions_per_day(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->submit()->assertRedirect(route('inspirational-resources.create'));
            $this->travel(4)->minutes();
        }

        $this->submit()->assertStatus(429);
        $this->assertSame(20, ResourceSubmission::query()->count());
    }

    public function test_the_resource_limit_is_separate_from_the_contact_form_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->submit()->assertRedirect(route('inspirational-resources.create'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->post(route('contact.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'Hello.',
            FormTimeTrap::FIELD => $this->agedToken(),
        ])->assertRedirect(route('contact.index'));
    }

    public function test_admin_sees_the_recorded_ip_and_user_agent(): void
    {
        $this->submit();
        $submission = ResourceSubmission::query()->sole();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        $this->actingAs($admin)->get('/admin/resource-submissions')
            ->assertOk()
            ->assertSee('203.0.113.10');

        $this->actingAs($admin)->get("/admin/resource-submissions/{$submission->getKey()}")
            ->assertOk()
            ->assertSee('203.0.113.10')
            ->assertSee('Mozilla/5.0 (Test Browser)');
    }
}
