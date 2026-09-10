<?php

namespace Tests\Feature;

use App\Actions\GratitudeJournal\CreateGratitudeJournalEntryAction;
use App\Enums\GratitudeJournalVisibility;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two pieces of previously-hardcoded homepage copy became admin-editable
 * 2026-09-10, at the client's request:
 *
 * - The "Join Our Community" card's heading/subheading/button label/button
 *   URL (HomeController::communityContent()) now read from the "home" Page's
 *   FeaturedContent section content_json, falling back to the original
 *   approved copy when a field is blank/the section doesn't exist yet.
 * - The "Latest Gratitude" carousel's link label (HomeController's
 *   $gratitudeCtaLabel) now reads from the new `home.gratitude_cta_label`
 *   Website Setup setting (see Tests\Feature\Admin\WebsiteSetupTest for the
 *   admin-save-side coverage), falling back to "Share Your Light". The link's
 *   actual target (member -> /account/gratitude-journal, guest ->
 *   registration) is unrelated, code-driven access-control behavior and is
 *   NOT admin-editable — untouched by this feature.
 */
class HomeAdminEditableContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gratitude_cta_defaults_to_share_your_light_when_no_setting_is_saved(): void
    {
        $this->createPublicGratitudeEntry();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Share Your Light');
    }

    public function test_a_custom_gratitude_cta_label_overrides_the_default(): void
    {
        $this->createPublicGratitudeEntry();
        app(SettingsRepository::class)->set('home', 'gratitude_cta_label', 'Share Your Own Light');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Share Your Own Light');
        $response->assertDontSee('Share Your Light </a>', false);
    }

    private function createPublicGratitudeEntry(): void
    {
        $user = User::factory()->create();
        (new CreateGratitudeJournalEntryAction)->handle($user, 'Grateful for this test entry.', GratitudeJournalVisibility::Public);
    }

    public function test_the_community_card_shows_the_original_default_copy_when_the_section_has_no_overrides(): void
    {
        $this->publishedHomePageWithFeaturedContentSection([]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Join Our Community');
        $response->assertSee('A growing space of hearts and minds united.');
        $response->assertSee('Join Now');
    }

    public function test_admin_edited_community_card_copy_overrides_the_defaults(): void
    {
        $this->publishedHomePageWithFeaturedContentSection([
            'heading' => 'Come Grow With Us',
            'subheading' => 'A custom invitation written by the admin.',
            'cta_label' => 'Get Involved',
            'cta_url' => '/custom-join-path',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Come Grow With Us');
        $response->assertSee('A custom invitation written by the admin.');
        $response->assertSee('Get Involved');
        $response->assertSee('/custom-join-path', false);
        $response->assertDontSee('Join Our Community');
    }

    public function test_the_community_card_is_absent_when_no_featured_content_section_exists(): void
    {
        $page = Page::query()->create([
            'title' => 'Home',
            'slug' => 'home',
            'template' => 'custom',
            'status' => PageStatus::Published,
            'publish_at' => now(),
        ]);
        $page->sections()->create([
            'section_type' => PageSectionType::Hero->value,
            'title' => 'Homepage Hero',
            'sort_order' => 0,
            'is_enabled' => true,
            'content_json' => [],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Join Our Community');
    }

    /**
     * @param  array<string, mixed>  $contentOverrides
     */
    private function publishedHomePageWithFeaturedContentSection(array $contentOverrides): Page
    {
        $page = Page::query()->create([
            'title' => 'Home',
            'slug' => 'home',
            'template' => 'custom',
            'status' => PageStatus::Published,
            'publish_at' => now(),
        ]);

        $page->sections()->create([
            'section_type' => PageSectionType::FeaturedContent->value,
            'title' => 'Join Our Community',
            'sort_order' => 0,
            'is_enabled' => true,
            'content_json' => ['module_key' => 'community', ...$contentOverrides],
        ]);

        return $page;
    }
}
