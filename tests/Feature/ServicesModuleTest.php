<?php

namespace Tests\Feature;

use App\Filament\Pages\ServiceSettings;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\BlogTag;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Service;
use App\Shared\Support\Features\Features;
use App\Shared\Support\Services\ServiceSettingsRepository;
use Database\Seeders\HomePageSeeder;
use Database\Seeders\NavigationMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesBlogContent;
use Tests\TestCase;

/**
 * Admin → Services, the public /services landing + detail pages, the
 * homepage Services section, and the migration off the old static gallery.
 */
class ServicesModuleTest extends TestCase
{
    use CreatesBlogContent;
    use RefreshDatabase;

    public function test_non_admins_cannot_reach_the_services_admin(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get('/admin/services')->assertForbidden();
        $this->actingAs($member)->get('/admin/services-settings')->assertForbidden();
    }

    public function test_admin_screens_open(): void
    {
        $admin = $this->admin();
        $service = $this->service();

        foreach (['/admin/services', '/admin/services/create', "/admin/services/{$service->getRouteKey()}/edit", '/admin/services-settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_a_service_with_highlights_faqs_and_seo(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateService::class)
            ->fillForm([
                'title' => 'Mobile Apps',
                'slug' => 'mobile-apps',
                'summary' => 'Native-feeling apps.',
                'icon' => 'monitor',
                'body' => '<p>We build apps.</p>',
                'highlights' => [['title' => 'iOS & Android', 'description' => 'One codebase.']],
                'faqs' => [['question' => 'How long?', 'answer' => 'It depends.']],
                'technologies' => ['Flutter'],
                'seo.meta_title' => 'Mobile App Development',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'mobile-apps')->sole();
        $this->assertSame('iOS & Android', $service->highlights[0]['title']);
        $this->assertSame('How long?', $service->faqs[0]['question']);
        $this->assertSame(['Flutter'], $service->technologies);
        $this->assertSame($admin->id, $service->created_by);
        $this->assertSame('Mobile App Development', $service->seo->meta_title);

        $this->get('/services/mobile-apps')->assertOk()->assertSee('<title>Mobile App Development</title>', false);
    }

    public function test_slug_and_summary_are_validated(): void
    {
        $this->service(['slug' => 'taken']);

        Livewire::actingAs($this->admin())
            ->test(CreateService::class)
            ->fillForm(['title' => 'X', 'slug' => 'taken', 'summary' => ''])
            ->call('create')
            ->assertHasFormErrors(['slug', 'summary' => 'required']);
    }

    public function test_admin_can_edit_a_service(): void
    {
        $service = $this->service();

        Livewire::actingAs($this->admin())
            ->test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['tagline' => 'New tagline', 'is_featured' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $service->refresh();
        $this->assertSame('New tagline', $service->tagline);
        $this->assertFalse($service->is_featured);
    }

    public function test_the_landing_page_lists_published_services_with_seo(): void
    {
        $this->service(['title' => 'Visible Service', 'technologies' => ['Laravel']]);
        $this->service(['title' => 'Hidden Service', 'slug' => 'hidden', 'is_published' => false]);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Visible Service')
            ->assertDontSee('Hidden Service')
            ->assertSee('Laravel')
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('<link rel="canonical" href="'.url('/services').'"', false);
    }

    public function test_a_service_page_renders_content_faqs_and_structured_data(): void
    {
        $service = $this->service([
            'title' => 'Cloud & DevOps',
            'slug' => 'cloud-devops',
            'tagline' => 'Secure infrastructure.',
            'highlights' => [['title' => 'Migration', 'description' => 'Move to AWS.']],
            'faqs' => [['question' => 'Can you cut costs?', 'answer' => 'Often, yes.']],
        ]);
        $this->service(['title' => 'Another One', 'slug' => 'another']);

        $this->get('/services/cloud-devops')
            ->assertOk()
            ->assertSee('Secure infrastructure.')
            ->assertSee('Migration')
            ->assertSee('Can you cut costs?')
            ->assertSee('"@type":"Service"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('Another One');
    }

    public function test_unpublished_services_404_except_for_admin_preview(): void
    {
        $this->service(['slug' => 'secret', 'is_published' => false]);

        $this->get('/services/secret')->assertNotFound();
        $this->actingAs($this->admin())->get('/services/secret')->assertOk()->assertSee('Preview')->assertSee('noindex, nofollow', false);
    }

    public function test_related_blog_posts_are_matched_by_technology_tag(): void
    {
        $service = $this->service(['slug' => 'cloud', 'technologies' => ['AWS']]);
        $post = $this->livePost(['title' => 'Our AWS guide']);
        $post->tags()->attach(BlogTag::query()->create(['name' => 'aws', 'slug' => 'aws']));
        $this->livePost(['title' => 'Unrelated post']);

        $this->get('/services/cloud')->assertSee('Related reading')->assertSee('Our AWS guide')->assertDontSee('Unrelated post');

        app(ServiceSettingsRepository::class)->save(['show_related_posts' => false]);
        $this->get('/services/cloud')->assertDontSee('Related reading');
    }

    public function test_the_homepage_section_shows_only_featured_services_linking_to_their_pages(): void
    {
        $this->admin();
        $this->seed(HomePageSeeder::class);
        $this->assertSame(8, Service::query()->count());
        $this->assertSame(6, Service::query()->where('is_featured', true)->count(), 'AI Development / Digital Marketing live in the spotlight, not the main grid');
        Service::query()->where('slug', 'cms-content-platforms')->update(['is_featured' => false]);

        $this->get('/')
            ->assertSee('id="services"', false)
            ->assertSee('href="'.url('/services/web-development').'"', false)
            ->assertDontSee('CMS &amp; Content Platforms', false)
            ->assertSee('href="/services"', false);
    }

    public function test_switching_services_off_404s_the_pages_and_hides_links_and_section(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);

        app(Features::class)->set('services', false);

        $this->get('/services')->assertNotFound();
        $this->get('/services/web-development')->assertNotFound();
        $this->get('/')->assertDontSee('id="services"', false)->assertDontSee('href="/services', false);
    }

    public function test_the_sitemap_lists_services(): void
    {
        $this->service(['slug' => 'listed']);
        $noindex = $this->service(['slug' => 'noindexed']);
        $noindex->seo()->create(['robots' => 'noindex, follow']);

        $this->get('/sitemap.xml')
            ->assertSee(url('/services'), false)
            ->assertSee(url('/services/listed'), false)
            ->assertDontSee(url('/services/noindexed'), false);
    }

    public function test_services_settings_save(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ServiceSettings::class)
            ->fillForm(['title' => 'What we build', 'cta_heading' => 'Book a call'])
            ->call('save');

        $this->service();
        $this->get('/services')->assertSee('What we build')->assertSee('Book a call');
    }

    public function test_the_migration_turns_the_static_gallery_into_services_and_repoints_menus(): void
    {
        $this->admin();
        $this->seed(NavigationMenuSeeder::class);
        $this->seed(HomePageSeeder::class);

        // Recreate the pre-migration state.
        Service::query()->delete();
        $section = Page::query()->where('slug', 'home')->sole()->sections()->where('section_type', 'services')->where('title', 'Services')->sole();
        $section->forceFill(['section_type' => 'gallery', 'content_json' => [
            'display' => 'services', 'anchor' => 'services', 'heading' => 'Our services', 'link_url' => '#',
            'gallery_items' => [
                ['title' => 'Web Development', 'description' => 'Custom card text.', 'icon' => 'monitor', 'url' => '#'],
                ['title' => 'Brand New Thing', 'description' => 'Not in the starter list.', 'icon' => 'cog', 'url' => '#'],
            ],
        ]])->save();
        DB::table('menu_items')->where('url', 'like', '/services%')->update(['url' => '/#services']);

        (require database_path('migrations/2026_09_26_150100_move_homepage_services_to_services_module.php'))->up();

        $web = Service::query()->where('slug', 'web-development')->sole();
        $this->assertSame('Custom card text.', $web->summary, 'the admin\'s card text wins over the starter copy');
        $this->assertNotEmpty($web->faqs, 'starter FAQs are added for known services');
        $this->assertSame('brand-new-thing', Service::query()->where('title', 'Brand New Thing')->sole()->slug);

        $section->refresh();
        $this->assertSame('services', $section->section_type);
        $this->assertSame('/services', $section->content_json['link_url']);

        $footer = Menu::query()->where('slug', 'footer-navigation')->sole();
        $this->assertSame('/services/web-development', $footer->items()->whereHas('parent')->where('label', 'Web Development')->value('url'));
        $this->assertSame('/services', Menu::query()->where('slug', 'primary-navigation')->sole()->items()->where('label', 'Services')->value('url'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(array $attributes = []): Service
    {
        static $n = 0;
        $n++;

        return Service::query()->create([
            'title' => "Service {$n}",
            'slug' => "service-{$n}",
            'summary' => 'A summary.',
            'body' => '<p>Body.</p>',
            'icon' => 'code',
            'is_published' => true,
            'is_featured' => true,
            ...$attributes,
        ]);
    }
}
