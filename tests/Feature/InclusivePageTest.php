<?php

namespace Tests\Feature;

use App\Enums\ModuleKey;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PageTemplate;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Enums\TrackStatus;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use App\Modules\Podcast\Enums\PodcastEpisodeStatus;
use App\Modules\Podcast\Enums\PodcastStatus;
use App\Modules\Podcast\Models\Podcast;
use App\Modules\Podcast\Models\PodcastEpisode;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Models\PoetryProse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Inclusive page (PageTemplate::Inclusive) — a normal CMS Page whose
 * Featured Content sections aggregate the latest published Music
 * (Albums/Singles), Poetry/Prose and Podcast records by reference. See
 * App\Shared\Support\Pages\InclusivePageContent and
 * resources/views/pages/inclusive.blade.php.
 */
class InclusivePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_inclusive_page_renders_its_hero_and_all_three_sections(): void
    {
        $page = $this->inclusivePage(['summary' => 'Everything, all in one place.']);
        $this->album(['title' => 'Light Album', 'status' => ReleaseStatus::Published]);
        $this->entry(['title' => 'A Quiet Poem']);
        $this->episode($this->podcast(), ['title' => 'Episode Of Light']);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Inclusive');
        $response->assertSee('Everything, all in one place.');
        $response->assertSee('data-inclusive-section="music"', false);
        $response->assertSee('data-inclusive-section="poetry_prose"', false);
        $response->assertSee('data-inclusive-section="podcast"', false);
        $response->assertSee('Light Album');
        $response->assertSee('A Quiet Poem');
        $response->assertSee('Episode Of Light');
    }

    public function test_sections_render_in_the_admin_defined_order(): void
    {
        $page = $this->inclusivePage(sections: [
            [ModuleKey::Podcast, ['heading' => 'Podcast Heading']],
            [ModuleKey::Music, ['heading' => 'Music Heading']],
            [ModuleKey::PoetryProse, ['heading' => 'Writing Heading']],
        ]);
        $this->album(['status' => ReleaseStatus::Published]);
        $this->entry();
        $this->episode($this->podcast());

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSeeInOrder(['Podcast Heading', 'Music Heading', 'Writing Heading']);
    }

    public function test_multiple_sections_render_independently_even_for_the_same_module(): void
    {
        $page = $this->inclusivePage(sections: [
            [ModuleKey::PoetryProse, ['heading' => 'Latest Three Writings', 'items_to_show' => 3]],
            [ModuleKey::Podcast, ['heading' => 'Our Podcast', 'items_to_show' => 4]],
            [ModuleKey::PoetryProse, ['heading' => 'More Writing', 'items_to_show' => 5, 'cta_label' => 'Read everything']],
        ]);
        $this->entries(8);
        $this->episode($this->podcast(), ['title' => 'Only Episode']);

        $html = $this->get(route('pages.show', $page))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-inclusive-section="'));
        $this->assertSame(3 + 5, substr_count($html, 'data-test-entry'));
        [$first, $rest] = explode('Our Podcast', $html, 2);
        [$podcast, $last] = explode('More Writing', $rest, 2);
        $this->assertSame(3, substr_count($first, 'data-test-entry'));
        $this->assertStringContainsString('Only Episode', $podcast);
        $this->assertSame(0, substr_count($podcast, 'data-test-entry'));
        $this->assertSame(5, substr_count($last, 'data-test-entry'));
        $this->assertStringContainsString('Read everything', $last);
        $this->assertStringNotContainsString('Read everything', $first);
    }

    public function test_the_empty_state_shows_when_every_section_is_disabled(): void
    {
        $page = $this->inclusivePage();
        $page->sections()->update(['is_enabled' => false]);
        $this->album(['title' => 'Hidden Album', 'status' => ReleaseStatus::Published]);
        $this->entry(['title' => 'Hidden Poem']);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertDontSee('data-inclusive-section', false);
        $response->assertDontSee('Hidden Album');
        $response->assertDontSee('Hidden Poem');
        $response->assertSee('New music, writing and podcasts are on their way');
    }

    public function test_the_empty_state_shows_when_no_section_has_published_items(): void
    {
        $page = $this->inclusivePage();
        $this->entry(['title' => 'Draft Only Poem', 'status' => PoetryProseStatus::Draft]);

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertDontSee('data-inclusive-section', false)
            ->assertSee('New music, writing and podcasts are on their way');
    }

    public function test_a_featured_content_section_with_no_module_chosen_is_skipped(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::PoetryProse, ['heading' => 'Writing Block']]]);
        $page->sections()->create([
            'section_type' => PageSectionType::FeaturedContent->value,
            'sort_order' => 9,
            'is_enabled' => true,
            'content_json' => ['heading' => 'Unconfigured Block'],
        ]);
        $this->entry();

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSee('Writing Block')
            ->assertDontSee('Unconfigured Block');
    }

    public function test_podcast_durations_under_a_minute_read_less_than_1_min(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::Podcast, []]]);
        $podcast = $this->podcast();
        $this->episode($podcast, ['title' => 'Short One', 'duration_seconds' => 30, 'publish_date' => now()]);
        $this->episode($podcast, ['title' => 'Long One', 'duration_seconds' => 1500, 'publish_date' => now()->subDay()]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Less than 1 min');
        $response->assertSee('25 min');
        $response->assertDontSee('>0 min<', false);
    }

    public function test_a_disabled_section_is_not_rendered(): void
    {
        $page = $this->inclusivePage();
        $page->sections()->where('content_json->module_key', ModuleKey::PoetryProse->value)->update(['is_enabled' => false]);
        $this->album(['title' => 'Light Album', 'status' => ReleaseStatus::Published]);
        $this->entry(['title' => 'Hidden Poem']);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Light Album');
        $response->assertDontSee('data-inclusive-section="poetry_prose"', false);
        $response->assertDontSee('Hidden Poem');
    }

    public function test_non_aggregatable_sections_are_ignored(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::Community, ['heading' => 'Community Card Heading']]]);
        $page->sections()->create([
            'section_type' => PageSectionType::Quote->value,
            'sort_order' => 5,
            'is_enabled' => true,
            'content_json' => ['quote' => 'A stray quote block'],
        ]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertDontSee('Community Card Heading');
        $response->assertDontSee('A stray quote block');
        $response->assertSee('New music, writing and podcasts are on their way');
    }

    public function test_each_section_defaults_to_six_items(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::PoetryProse, []]]);
        $this->entries(8);

        $this->assertSame(6, substr_count($this->get(route('pages.show', $page))->getContent(), 'data-test-entry'));
    }

    public function test_the_configured_item_limit_is_respected(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::PoetryProse, ['items_to_show' => 4]]]);
        $this->entries(8);

        $this->assertSame(4, substr_count($this->get(route('pages.show', $page))->getContent(), 'data-test-entry'));
    }

    public function test_out_of_range_item_limits_are_clamped_to_3_to_12(): void
    {
        $this->entries(14);

        $low = $this->inclusivePage(['slug' => 'inclusive-low'], [[ModuleKey::PoetryProse, ['items_to_show' => 1]]]);
        $high = $this->inclusivePage(['slug' => 'inclusive-high'], [[ModuleKey::PoetryProse, ['items_to_show' => 50]]]);

        $this->assertSame(3, substr_count($this->get(route('pages.show', $low))->getContent(), 'data-test-entry'));
        $this->assertSame(12, substr_count($this->get(route('pages.show', $high))->getContent(), 'data-test-entry'));
    }

    public function test_each_section_shows_its_latest_items_first_by_its_own_date_field(): void
    {
        $page = $this->inclusivePage();
        $this->album(['title' => 'Older Album', 'slug' => 'older-album', 'status' => ReleaseStatus::Published, 'release_date' => now()->subYears(2)]);
        $this->single(['title' => 'Newer Single', 'slug' => 'newer-single', 'status' => ReleaseStatus::Published, 'release_date' => now()->subMonth()]);
        $this->entry(['title' => 'Older Poem', 'slug' => 'older-poem', 'publish_at' => now()->subYear()]);
        $this->entry(['title' => 'Newer Poem', 'slug' => 'newer-poem', 'publish_at' => now()->subDay()]);
        $podcast = $this->podcast();
        $this->episode($podcast, ['title' => 'Older Episode', 'publish_date' => now()->subYear()]);
        $this->episode($podcast, ['title' => 'Newer Episode', 'publish_date' => now()->subDay()]);

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertSeeInOrder(['Newer Single', 'Older Album'])
            ->assertSeeInOrder(['Newer Poem', 'Older Poem'])
            ->assertSeeInOrder(['Newer Episode', 'Older Episode']);
    }

    public function test_draft_and_archived_content_is_never_shown(): void
    {
        $page = $this->inclusivePage();
        $this->album(['title' => 'Draft Album', 'slug' => 'draft-album', 'status' => ReleaseStatus::Draft]);
        $this->album(['title' => 'Scheduled Album', 'slug' => 'scheduled-album', 'status' => ReleaseStatus::Scheduled]);
        $this->single(['title' => 'Archived Single', 'slug' => 'archived-single', 'status' => ReleaseStatus::Archived]);
        $this->entry(['title' => 'Draft Poem', 'slug' => 'draft-poem', 'status' => PoetryProseStatus::Draft]);
        $this->entry(['title' => 'Archived Poem', 'slug' => 'archived-poem', 'status' => PoetryProseStatus::Archived]);
        $this->episode($this->podcast(), ['title' => 'Draft Episode', 'status' => PodcastEpisodeStatus::Draft]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        foreach (['Draft Album', 'Scheduled Album', 'Archived Single', 'Draft Poem', 'Archived Poem', 'Draft Episode'] as $title) {
            $response->assertDontSee($title);
        }
    }

    public function test_an_episode_of_an_unpublished_podcast_is_never_shown(): void
    {
        $page = $this->inclusivePage();
        $this->episode($this->podcast(['status' => PodcastStatus::Draft]), ['title' => 'Orphaned Episode']);
        $this->episode($this->podcast(), ['title' => 'Visible Episode']);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Visible Episode');
        $response->assertDontSee('Orphaned Episode');
    }

    public function test_music_shows_releases_not_individual_tracks(): void
    {
        $page = $this->inclusivePage();
        $album = $this->album(['title' => 'Album With Tracks', 'status' => ReleaseStatus::Published]);
        Track::query()->create([
            'album_id' => $album->id,
            'title' => 'A Distinct Track Title',
            'slug' => 'a-distinct-track-title',
            'track_number' => 1,
            'status' => TrackStatus::Published,
        ]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Album With Tracks');
        $response->assertSee('1 Track');
        $response->assertDontSee('A Distinct Track Title');
        $response->assertDontSee(route('music.tracks.show', 'a-distinct-track-title'), false);
    }

    public function test_cards_link_to_the_existing_detail_pages_and_view_all_defaults_to_the_listing_pages(): void
    {
        $page = $this->inclusivePage();
        $album = $this->album(['status' => ReleaseStatus::Published]);
        $single = $this->single(['status' => ReleaseStatus::Published]);
        $entry = $this->entry();
        $episode = $this->episode($this->podcast());

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee(route('music.albums.show', $album), false);
        $response->assertSee(route('music.singles.show', $single), false);
        $response->assertSee(route('poetry-prose.show', $entry), false);
        $response->assertSee(route('podcast.episodes.show', $episode), false);
        $response->assertSee(route('music.index'), false);
        $response->assertSee(route('poetry-prose.index'), false);
        $response->assertSee(route('podcast.episodes.index'), false);
    }

    public function test_admin_heading_subheading_and_view_all_overrides_are_used(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::Music, [
            'heading' => 'Songs of Light',
            'subheading' => 'Our newest releases.',
            'cta_label' => 'Browse the catalogue',
            'cta_url' => '/music?type=album',
        ]]]);
        $this->album(['status' => ReleaseStatus::Published]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Songs of Light');
        $response->assertSee('Our newest releases.');
        $response->assertSee('Browse the catalogue');
        $response->assertSee('/music?type=album', false);
    }

    public function test_a_featured_release_is_marked_featured(): void
    {
        $page = $this->inclusivePage(sections: [[ModuleKey::Music, []]]);
        $this->album(['title' => 'Featured Album', 'status' => ReleaseStatus::Published, 'is_featured' => true]);

        $this->get(route('pages.show', $page))->assertOk()->assertSee('>Featured</span>', false);
    }

    public function test_a_section_with_no_published_items_is_omitted(): void
    {
        $page = $this->inclusivePage();
        $this->entry(['title' => 'Only Poem']);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('Only Poem');
        $response->assertDontSee('data-inclusive-section="music"', false);
        $response->assertDontSee('data-inclusive-section="podcast"', false);
    }

    public function test_guests_and_registered_users_see_the_same_page_without_any_player_cart_or_download_actions(): void
    {
        $page = $this->inclusivePage();
        $album = $this->album(['status' => ReleaseStatus::Published]);
        Track::query()->create([
            'album_id' => $album->id, 'title' => 'T', 'slug' => 'track-t', 'track_number' => 1, 'status' => TrackStatus::Published,
        ]);
        $episode = $this->episode($this->podcast());
        $this->entry();

        $guest = $this->get(route('pages.show', $page));
        $member = $this->actingAs(User::factory()->create(['status' => 'active']))->get(route('pages.show', $page));

        foreach ([$guest, $member] as $response) {
            $response->assertOk();
            $response->assertSee(route('music.albums.show', $album), false);
            $response->assertSee(route('podcast.episodes.show', $episode), false);
            $response->assertDontSee(route('music.tracks.download', 'track-t'), false);
            $response->assertDontSee(route('music.tracks.stream', 'track-t'), false);
            $response->assertDontSee(route('podcast.episodes.download', $episode), false);
            $response->assertDontSee(route('podcast.episodes.stream', $episode), false);
            $response->assertDontSee(route('cart.add'), false);
            $response->assertDontSee('<audio', false);
        }
    }

    public function test_a_draft_inclusive_page_404s_publicly_but_previews_for_admins(): void
    {
        $page = $this->inclusivePage(['status' => PageStatus::Draft->value]);
        $this->entry(['title' => 'Preview Poem']);

        $this->get(route('pages.show', $page))->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('pages.preview', $page))
            ->assertOk()
            ->assertSee('Preview only')
            ->assertSee('Preview Poem')
            ->assertSee('noindex, nofollow', false)
            // Rendered after the fixed site header, inside the hero content,
            // so it is never hidden underneath the header.
            ->assertSeeInOrder(['<header', '</header>', 'data-preview-notice'], false);
    }

    public function test_a_published_inclusive_page_shows_no_preview_notice(): void
    {
        $page = $this->inclusivePage();

        $this->get(route('pages.show', $page))
            ->assertOk()
            ->assertDontSee('data-preview-notice', false)
            ->assertDontSee('Preview only');
    }

    public function test_seo_title_description_and_canonical_url_come_from_the_page(): void
    {
        $page = $this->inclusivePage();
        $page->seo()->create([
            'meta_title' => 'Inclusive — Music, Writing & Podcasts',
            'meta_description' => 'All of All The Things Light in one place.',
        ]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('<title>Inclusive — Music, Writing &amp; Podcasts</title>', false);
        $response->assertSee('All of All The Things Light in one place.', false);
        $response->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/inclusive"', false);
        $response->assertDontSee('noindex', false);
    }

    public function test_the_published_inclusive_page_is_listed_in_the_sitemap(): void
    {
        $this->inclusivePage();

        $this->get(route('sitemap'))->assertOk()->assertSee(route('pages.show', 'inclusive'), false);
    }

    public function test_admin_can_create_an_inclusive_page_with_an_items_to_show_setting(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePage::class)
            ->fillForm([
                'title' => 'Inclusive',
                'slug' => 'inclusive',
                'template' => PageTemplate::Inclusive->value,
                'status' => PageStatus::Draft->value,
                'sections' => [
                    ['section_type' => PageSectionType::FeaturedContent->value, 'is_enabled' => true, 'content_json' => [
                        'module_key' => ModuleKey::Music->value, 'heading' => 'Music', 'items_to_show' => 8,
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'inclusive')->firstOrFail();
        $this->assertSame(PageTemplate::Inclusive, $page->template);
        $section = $page->sections()->firstOrFail();
        $this->assertSame(ModuleKey::Music->value, $section->content_json['module_key']);
        $this->assertEquals(8, $section->content_json['items_to_show']);
    }

    public function test_the_homepage_community_card_is_unaffected_by_the_inclusive_settings(): void
    {
        $home = Page::query()->create([
            'title' => 'Home', 'slug' => 'home', 'template' => PageTemplate::Custom->value,
            'status' => PageStatus::Published, 'publish_at' => now(),
        ]);
        $home->sections()->create([
            'section_type' => PageSectionType::FeaturedContent->value,
            'sort_order' => 0,
            'is_enabled' => true,
            'content_json' => ['module_key' => ModuleKey::Community->value, 'heading' => 'Come Grow With Us'],
        ]);
        $this->inclusivePage();
        $this->entry(['title' => 'Inclusive Only Poem']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Come Grow With Us');
        $response->assertDontSee('Inclusive Only Poem');
        $response->assertDontSee('data-inclusive-section', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<int, array{0: ModuleKey, 1: array<string, mixed>}>|null  $sections
     */
    private function inclusivePage(array $overrides = [], ?array $sections = null): Page
    {
        $page = Page::query()->create([
            'title' => 'Inclusive',
            'slug' => 'inclusive',
            'template' => PageTemplate::Inclusive->value,
            'status' => PageStatus::Published->value,
            'publish_at' => now(),
            ...$overrides,
        ]);

        $sections ??= [[ModuleKey::Music, []], [ModuleKey::PoetryProse, []], [ModuleKey::Podcast, []]];

        foreach ($sections as $index => [$module, $content]) {
            $page->sections()->create([
                'section_type' => PageSectionType::FeaturedContent->value,
                'title' => $module->value,
                'sort_order' => $index,
                'is_enabled' => true,
                'content_json' => ['module_key' => $module->value, ...$content],
            ]);
        }

        return $page;
    }

    /**
     * Counts are taken via a unique per-entry title marker.
     */
    private function entries(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $this->entry(['title' => "data-test-entry {$i}", 'slug' => "entry-{$i}", 'publish_at' => now()->subDays($i)]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function album(array $overrides = []): Album
    {
        return Album::query()->create([
            'title' => 'An Album', 'slug' => 'an-album', 'status' => ReleaseStatus::Draft, 'release_date' => now()->subMonths(3),
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function single(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'A Single', 'slug' => 'a-single', 'status' => ReleaseStatus::Draft, 'release_date' => now()->subMonths(2),
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function entry(array $overrides = []): PoetryProse
    {
        return PoetryProse::query()->create([
            'title' => 'A Poetry/Prose Entry',
            'slug' => 'a-poetry-prose-entry-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'content_type' => PoetryProseContentType::Essay,
            'status' => PoetryProseStatus::Published,
            'publish_at' => now(),
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function podcast(array $overrides = []): Podcast
    {
        return Podcast::query()->create([
            'title' => 'Test Podcast '.uniqid(), 'slug' => 'test-podcast-'.uniqid(), 'status' => PodcastStatus::Published,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function episode(Podcast $podcast, array $overrides = []): PodcastEpisode
    {
        return PodcastEpisode::query()->create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode '.uniqid(),
            'slug' => 'test-episode-'.uniqid(),
            'status' => PodcastEpisodeStatus::Published,
            'publish_date' => now(),
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }
}
