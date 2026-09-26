<?php

namespace Tests\Feature\Contact;

use App\Models\ContactRequest;
use App\Shared\Services\Settings\SettingsRepository;
use App\Shared\Support\Spam\FormTimeTrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\PassesFormTimeTrap;
use Tests\TestCase;

/**
 * The Contact page's anti-harvesting and anti-spam layers: the site's own
 * contact details are masked in the HTML and only revealed on demand, and
 * submissions pass a time trap plus stricter validation.
 */
class ContactSpamProtectionTest extends TestCase
{
    use PassesFormTimeTrap;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SettingsRepository::class);
        $settings->set('contact', 'email', 'contact@softphoria.com');
        $settings->set('contact', 'phone', '+91 9163270494');
        $settings->set('contact', 'whatsapp', '919163270494');
        $settings->set('contact', 'address', 'Monalisa Mansion, Kolkata');
    }

    public function test_the_contact_page_never_contains_the_full_contact_details(): void
    {
        $response = $this->get('/contact')->assertOk();

        $response->assertDontSee('contact@softphoria.com', false);
        $response->assertDontSee('9163270494', false);
        $response->assertDontSee('mailto:', false);
        $response->assertDontSee('tel:', false);
        $response->assertDontSee('wa.me', false);

        $response->assertSee('co•••••@•••••ia.com', false);
        $response->assertSee('+91 91•••••94', false);
        $response->assertSee('data-contact-reveal="email"', false);
        $response->assertSee('Monalisa Mansion, Kolkata');
    }

    public function test_the_contact_forms_carry_a_time_trap_token(): void
    {
        $this->get('/contact')->assertSee('name="'.FormTimeTrap::FIELD.'"', false);
        $this->get('/')->assertSee('name="'.FormTimeTrap::FIELD.'"', false);
    }

    public function test_reveal_returns_the_real_value_for_an_ajax_request(): void
    {
        $response = $this->revealRequest('email');

        $response->assertOk()->assertExactJson([
            'display' => 'contact@softphoria.com',
            'href' => 'mailto:contact@softphoria.com',
        ]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->revealRequest('phone')->assertJson(['display' => '+91 9163270494', 'href' => 'tel:+919163270494']);
        $this->revealRequest('whatsapp')->assertJson(['display' => '+919163270494', 'href' => 'https://wa.me/919163270494']);
    }

    public function test_reveal_ignores_non_ajax_requests(): void
    {
        $this->post('/contact/reveal', ['channel' => 'email', '_started' => $this->formStartedToken()])
            ->assertNotFound();
    }

    public function test_reveal_only_serves_known_channels(): void
    {
        $this->revealRequest('address')->assertNotFound();
        $this->revealRequest('smtp_password')->assertNotFound();
    }

    public function test_reveal_rejects_a_missing_forged_or_too_fresh_token(): void
    {
        $this->revealRequest('email', token: '')->assertStatus(422);
        $this->revealRequest('email', token: 'not-a-real-token')->assertStatus(422);
        $this->revealRequest('email', token: app(FormTimeTrap::class)->issue())->assertStatus(422);
    }

    public function test_reveal_404s_for_an_unset_detail(): void
    {
        app(SettingsRepository::class)->set('contact', 'whatsapp', '');

        $this->revealRequest('whatsapp')->assertNotFound();
    }

    public function test_reveal_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->revealRequest('email');
        }

        $this->revealRequest('email')->assertStatus(429);
    }

    public function test_a_submission_without_a_time_trap_token_is_silently_discarded(): void
    {
        Mail::fake();

        $response = $this->post('/contact', $this->validPayload());

        $response->assertRedirect('/contact')->assertSessionHas('status');
        $this->assertSame(0, ContactRequest::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_submission_sent_back_too_quickly_is_silently_discarded(): void
    {
        $response = $this->postJson('/contact', $this->validPayload([
            '_started' => app(FormTimeTrap::class)->issue(),
        ]));

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_a_forged_time_trap_token_is_silently_discarded(): void
    {
        // Correct shape, but not encrypted with this app's key.
        $this->post('/contact', $this->validPayload(['_started' => base64_encode((string) now()->subHour()->getTimestamp())]));
        $this->post('/contact', $this->validPayload(['_started' => Crypt::encryptString('yesterday')]));

        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_links_in_the_name_are_rejected(): void
    {
        $this->from('/contact')
            ->post('/contact', $this->validPayload(['name' => 'Cheap pills https://spam.example', '_started' => $this->formStartedToken()]))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, ContactRequest::query()->count());
    }

    public function test_more_than_three_links_in_the_message_are_rejected(): void
    {
        $message = 'See https://a.example http://b.example www.c.example https://d.example';

        $this->from('/contact')
            ->post('/contact', $this->validPayload(['message' => $message, '_started' => $this->formStartedToken()]))
            ->assertSessionHasErrors('message');
    }

    public function test_an_unknown_category_or_malformed_phone_is_rejected(): void
    {
        $this->from('/contact')
            ->post('/contact', $this->validPayload([
                'category' => 'casino',
                'phone' => 'call me maybe',
                '_started' => $this->formStartedToken(),
            ]))
            ->assertSessionHasErrors(['category', 'phone']);
    }

    public function test_a_genuine_submission_passes_every_layer(): void
    {
        $this->post('/contact', $this->validPayload([
            'category' => 'project',
            'message' => 'We need a new website — our current one is at https://example.com.',
            '_started' => $this->formStartedToken(),
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contact_requests', ['email' => 'jane@example.com', 'category' => 'project']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'message' => 'Hello there.',
        ], $overrides);
    }

    private function revealRequest(string $channel, ?string $token = null): TestResponse
    {
        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/contact/reveal', [
                'channel' => $channel,
                FormTimeTrap::FIELD => $token ?? $this->formStartedToken(),
            ]);
    }
}
