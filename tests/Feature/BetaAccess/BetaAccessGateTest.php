<?php

namespace Tests\Feature\BetaAccess;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The temporary password-only "coming soon" gate (App\Http\Middleware\
 * BetaAccessGate's own docblock). Deliberately never uses the real
 * production password anywhere here — see .env.example/docs for where that
 * actually lives.
 */
class BetaAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private const string TEST_PASSWORD = 'test-only-secret-999';

    protected function setUp(): void
    {
        parent::setUp();

        // Every other Feature test in this suite relies on the gate being
        // off by default (phpunit.xml sets no BETA_ACCESS_ENABLED) — assert
        // that assumption explicitly here rather than only implicitly.
        config(['beta.enabled' => false]);
    }

    public function test_the_gate_is_off_by_default_and_the_site_works_normally(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_an_unauthorized_visitor_is_redirected_to_the_password_page(): void
    {
        $this->enableGate();

        $response = $this->get('/music');

        $response->assertRedirect(route('beta.show'));
    }

    public function test_the_password_page_itself_is_reachable_without_a_redirect_loop(): void
    {
        $this->enableGate();

        $response = $this->get(route('beta.show'));

        $response->assertOk();
        $response->assertSee('Beta Access');
        $response->assertSee('name="password"', false);
        $response->assertDontSee(self::TEST_PASSWORD);
    }

    public function test_the_frameworks_health_route_bypasses_the_gate(): void
    {
        $this->enableGate();

        $response = $this->get('/up');

        $response->assertOk();
    }

    public function test_an_incorrect_password_is_rejected_with_a_generic_error(): void
    {
        $this->enableGate();

        $response = $this->post(route('beta.attempt'), ['password' => 'wrong-guess']);

        $response->assertRedirect(route('beta.show'));
        $response->assertSessionHasErrors('password');
        $this->assertNull(session('beta_access_granted'));
    }

    public function test_a_blank_configured_password_never_matches_a_blank_submission(): void
    {
        config(['beta.password' => '']);
        config(['beta.enabled' => true]);

        $response = $this->post(route('beta.attempt'), ['password' => '']);

        $response->assertSessionHasErrors('password');
        $this->assertNull(session('beta_access_granted'));
    }

    public function test_the_honeypot_field_silently_rejects_the_submission(): void
    {
        $this->enableGate();

        $response = $this->post(route('beta.attempt'), [
            'password' => self::TEST_PASSWORD,
            'hp_website' => 'https://spam.example',
        ]);

        $response->assertRedirect(route('beta.show'));
        $this->assertNull(session('beta_access_granted'));
    }

    public function test_the_correct_password_grants_access_and_the_visitor_can_then_browse_normally(): void
    {
        $this->enableGate();

        $response = $this->post(route('beta.attempt'), ['password' => self::TEST_PASSWORD]);

        $response->assertRedirect(route('home'));
        $this->assertTrue(session('beta_access_granted'));

        $next = $this->get('/music');
        $next->assertOk();
    }

    public function test_after_success_the_visitor_returns_to_the_url_they_originally_requested(): void
    {
        $this->enableGate();

        $this->get('/music');
        $response = $this->post(route('beta.attempt'), ['password' => self::TEST_PASSWORD]);

        $response->assertRedirect(url('/music'));
    }

    public function test_login_register_and_admin_remain_reachable_once_authorized(): void
    {
        $this->enableGate();
        $this->withSession(['beta_access_granted' => true]);

        $this->get(route('login'))->assertOk();
        $this->get(route('register.show'))->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_the_internal_auth_check_reflects_session_state(): void
    {
        $this->enableGate();

        $this->get(route('beta.auth-check'))->assertStatus(401);

        $this->withSession(['beta_access_granted' => true]);
        $this->get(route('beta.auth-check'))->assertNoContent();
    }

    public function test_the_internal_auth_check_always_passes_when_the_gate_is_disabled(): void
    {
        $this->get(route('beta.auth-check'))->assertNoContent();
    }

    public function test_robots_txt_fully_disallows_crawling_while_the_gate_is_enabled(): void
    {
        $this->enableGate();

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /');
        $response->assertDontSee('Sitemap:');
    }

    public function test_the_homepage_is_forced_noindex_while_the_gate_is_enabled(): void
    {
        $this->enableGate();
        $this->withSession(['beta_access_granted' => true]);

        $response = $this->get('/');

        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    private function enableGate(): void
    {
        config(['beta.enabled' => true]);
        config(['beta.password' => self::TEST_PASSWORD]);
    }
}
