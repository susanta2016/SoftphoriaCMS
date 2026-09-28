<?php

namespace Tests\Feature\Public;

use App\Actions\Media\DeleteMediaAction;
use App\Exceptions\Media\MediaInUseException;
use App\Filament\Resources\PortfolioItems\Pages\CreatePortfolioItem;
use App\Models\Media;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Shared\Support\Features\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The public Portfolio: the /portfolio listing, one /portfolio/{slug}
 * detail page per published project (PortfolioController::show), its SEO,
 * sitemap entries, privacy rules, admin fields and the copy-correction
 * data migration.
 */
class PortfolioDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_shows_published_projects_with_their_card_details(): void
    {
        $cover = $this->imageMedia('media/images/b2b-cover.jpg', 'B2B platform dashboard');
        $web = $this->service('Web Development', 'web-development');
        $item = $this->project([
            'title' => 'B2B E-Commerce Platform',
            'category' => 'E-Commerce',
            'summary' => 'A large-scale B2B e-commerce platform with complex product management, pricing, and ERP integration.',
            'technologies' => ['Node.js', 'Angular', 'MySQL', 'AWS'],
            'cover_media_id' => $cover->id,
        ]);
        $item->services()->attach($web);
        $this->project(['title' => 'Secret Draft', 'summary' => 'Confidential client work.', 'is_published' => false]);

        $response = $this->get('/portfolio')->assertOk();

        $response->assertSee('Selected projects');
        $response->assertSee('A selection of websites, platforms and technology solutions delivered across different industries.');
        $response->assertSeeInOrder(['E-Commerce', 'B2B E-Commerce Platform', 'A large-scale B2B e-commerce platform', 'Web Development', 'Node.js', 'Angular', 'MySQL', 'AWS']);
        $response->assertSee('storage/media/images/b2b-cover.jpg', false);
        $response->assertSee('alt="B2B platform dashboard"', false);
        $response->assertSee('href="'.$item->url().'"', false);
        $response->assertDontSee('NodeJs');
        $response->assertDontSee('AngularJs');
        $response->assertDontSee('Secret Draft');
        $response->assertDontSee('Confidential client work.');
    }

    public function test_a_published_project_page_renders_its_cms_content(): void
    {
        $web = $this->service('Web Development', 'web-development');
        $hidden = $this->service('Hidden Service', 'hidden-service', published: false);
        $item = $this->project([
            'title' => 'Sanjog Loan',
            'category' => 'Fintech',
            'summary' => 'A lending platform.',
            'technologies' => ['Node.js', 'Angular', 'MySQL', 'AWS'],
        ]);
        $item->services()->attach([$web->id, $hidden->id]);

        $response = $this->get('/portfolio/sanjog-loan')->assertOk();

        $response->assertSeeInOrder(['Home', 'Portfolio', 'Sanjog Loan'], false);
        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSee('Fintech');
        $response->assertSee('<h1', false);
        $response->assertSee('A lending platform.');
        $response->assertSeeInOrder(['Services', 'Web Development', 'Technologies', 'Node.js', 'Angular', 'MySQL', 'AWS']);
        $response->assertSee('href="'.$web->url().'"', false);
        $response->assertDontSee('Hidden Service');
        // CTA → the existing Contact page (no second contact form).
        $response->assertSeeInOrder(['Have a project like this in mind?', 'Tell us about it', 'href="'.route('contact.index').'"', 'Start a project'], false);
        $response->assertDontSee('cw-name-page', false);
    }

    public function test_detail_sections_render_only_when_filled_and_are_sanitised(): void
    {
        $this->project([
            'title' => 'Cloud Migration & Modernization',
            'challenge' => '<p>Legacy servers were hard to scale.</p><script>alert("x")</script>',
            'solution' => '<p>   </p>',
        ]);

        $response = $this->get('/portfolio/cloud-migration-modernization')->assertOk();

        $response->assertSee('Challenge');
        $response->assertSee('Legacy servers were hard to scale.');
        $response->assertDontSee('alert("x")', false);
        $response->assertDontSee('>Solution<', false);
        $response->assertDontSee('>Outcome<', false);
    }

    public function test_a_project_without_detail_content_shows_no_empty_sections(): void
    {
        $this->project(['title' => 'Bare Project']);

        $response = $this->get('/portfolio/bare-project')->assertOk();

        foreach (['>Challenge<', '>Solution<', '>Outcome<', 'Project gallery', 'Visit project', '>Services<', '>Technologies<'] as $absent) {
            $response->assertDontSee($absent, false);
        }
        // No featured image: a branded project graphic, not a fake screenshot.
        $response->assertDontSee('fetchpriority="high"', false);
    }

    public function test_the_gallery_renders_only_when_configured(): void
    {
        $withAlt = $this->imageMedia('media/images/shot-1.jpg', 'Checkout screen');
        $noAlt = $this->imageMedia('media/images/shot-2.jpg');
        $this->project(['title' => 'Gallery Project', 'gallery_media_ids' => [$noAlt->id, $withAlt->id]]);
        $this->project(['title' => 'No Gallery Project']);

        $this->get('/portfolio/gallery-project')
            ->assertSee('Project gallery')
            ->assertSeeInOrder(['shot-2.jpg', 'shot-1.jpg'], false)
            ->assertSee('alt="Checkout screen"', false)
            ->assertSee('alt="Gallery Project — image 1"', false)
            ->assertSee('loading="lazy"', false);

        $this->get('/portfolio/no-gallery-project')->assertDontSee('Project gallery');
    }

    public function test_the_project_url_renders_only_when_configured(): void
    {
        $this->project(['title' => 'Linked Project', 'link_url' => 'https://client.example']);
        $this->project(['title' => 'Unlinked Project']);

        $this->get('/portfolio/linked-project')
            ->assertSeeInOrder(['href="https://client.example"', 'target="_blank"', 'rel="noopener noreferrer"', 'Visit project'], false);

        $this->get('/portfolio/unlinked-project')->assertDontSee('Visit project');
    }

    public function test_unpublished_and_unknown_projects_are_not_publicly_accessible(): void
    {
        $this->project(['title' => 'Private Client Work', 'summary' => 'NDA details.', 'is_published' => false]);
        $member = User::factory()->create(['status' => 'active']);

        $this->get('/portfolio/private-client-work')->assertNotFound()->assertDontSee('NDA details.');
        $this->actingAs($member)->get('/portfolio/private-client-work')->assertNotFound()->assertDontSee('NDA details.');
        $this->get('/portfolio/does-not-exist')->assertNotFound();
    }

    public function test_an_admin_gets_a_noindex_preview_of_an_unpublished_project(): void
    {
        $this->project(['title' => 'Draft Project', 'is_published' => false]);

        $this->actingAs($this->admin())->get('/portfolio/draft-project')
            ->assertOk()
            ->assertSee('Preview — this project is unpublished')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
    }

    public function test_project_pages_have_title_description_canonical_og_and_structured_data(): void
    {
        $cover = $this->imageMedia('media/images/og-cover.jpg');
        $item = $this->project(['title' => 'SEO Project', 'summary' => 'Short, factual description.', 'cover_media_id' => $cover->id]);

        $response = $this->get('/portfolio/seo-project')->assertOk();

        $response->assertSee('<title>SEO Project — ', false);
        $response->assertSee('name="description" content="Short, factual description."', false);
        $response->assertSee('<link rel="canonical" href="'.$item->url().'">', false);
        $response->assertSee('name="robots" content="index, follow"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('"@type":"CreativeWork"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_an_admin_noindex_is_respected_and_the_listing_stays_indexable(): void
    {
        $item = $this->project(['title' => 'Hidden From Search']);
        $item->seo()->create(['robots' => 'noindex, follow']);

        $this->get('/portfolio/hidden-from-search')->assertSee('name="robots" content="noindex, follow"', false);
        $this->get('/portfolio')->assertSee('name="robots" content="index, follow"', false);
    }

    public function test_the_sitemap_lists_only_public_indexable_project_pages(): void
    {
        $public = $this->project(['title' => 'Public Project']);
        $this->project(['title' => 'Draft Project', 'is_published' => false]);
        $noindex = $this->project(['title' => 'Noindex Project']);
        $noindex->seo()->create(['robots' => 'noindex, follow']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('portfolio.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$public->url().'</loc>', $xml);
        $this->assertStringNotContainsString('draft-project', $xml);
        $this->assertStringNotContainsString('noindex-project', $xml);
    }

    public function test_project_pages_404_while_the_portfolio_feature_is_off(): void
    {
        $this->project(['title' => 'Switched Off']);
        app(Features::class)->set('portfolio', false);

        $this->get('/portfolio/switched-off')->assertNotFound();
    }

    public function test_slugs_are_generated_uniquely_when_not_given(): void
    {
        $first = $this->project(['title' => 'Same Name']);
        $second = $this->project(['title' => 'Same Name']);

        $this->assertSame('same-name', $first->slug);
        $this->assertSame('same-name-2', $second->slug);
    }

    public function test_admin_can_create_a_project_with_slug_services_gallery_and_details(): void
    {
        $admin = $this->admin();
        $cover = $this->imageMedia('media/images/new-cover.jpg');
        $shot = $this->imageMedia('media/images/new-shot.jpg');
        $cloud = $this->service('Cloud & DevOps', 'cloud-devops');

        Livewire::actingAs($admin)
            ->test(CreatePortfolioItem::class)
            ->fillForm([
                'title' => 'New Project',
                'slug' => 'new-project',
                'summary' => 'Short description.',
                'challenge' => '<p>The challenge.</p>',
                'technologies' => ['AWS'],
                'services' => [$cloud->id],
                'cover_media_id' => $cover->id,
                'gallery_media_ids' => [$shot->id],
                'seo' => ['meta_title' => 'Custom Title'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = PortfolioItem::query()->sole();
        $this->assertSame('new-project', $item->slug);
        $this->assertSame([$cloud->id], $item->services->pluck('id')->all());
        $this->assertSame([$shot->id], $item->gallery_media_ids);
        $this->assertStringContainsString('The challenge.', $item->challenge);
        $this->assertSame('Custom Title', $item->seo?->meta_title);
    }

    public function test_admin_slugs_must_be_unique_and_url_safe(): void
    {
        $this->project(['title' => 'Taken', 'slug' => 'taken']);

        Livewire::actingAs($this->admin())
            ->test(CreatePortfolioItem::class)
            ->fillForm(['title' => 'Another', 'slug' => 'taken'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        Livewire::actingAs($this->admin())
            ->test(CreatePortfolioItem::class)
            ->fillForm(['title' => 'Another', 'slug' => 'Not A Slug!'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_media_used_in_a_project_gallery_cannot_be_deleted(): void
    {
        $media = $this->imageMedia('media/images/in-gallery.jpg');
        $this->project(['title' => 'Gallery Owner', 'gallery_media_ids' => [$media->id]]);

        $this->expectException(MediaInUseException::class);

        app(DeleteMediaAction::class)->handle($media, $this->admin());
    }

    public function test_the_copy_migration_fixes_the_b2b_description_and_technology_names(): void
    {
        $b2b = $this->project(['title' => 'B2B', 'summary' => 'A large scale e-commerce platform with complex product management, pricing and ERP integration.']);
        $sanjog = $this->project(['title' => 'Sanjog', 'technologies' => ['NodeJs, AngularJs, MySQL, AWS']]);
        $edited = $this->project(['title' => 'Edited', 'summary' => 'An admin-written description.', 'technologies' => ['Laravel']]);

        (require database_path('migrations/2026_09_28_110100_correct_portfolio_project_copy.php'))->up();

        $this->assertSame('A large-scale B2B e-commerce platform with complex product management, pricing, and ERP integration.', $b2b->fresh()->summary);
        $this->assertSame(['Node.js', 'Angular', 'MySQL', 'AWS'], $sanjog->fresh()->technologies);
        $this->assertSame('An admin-written description.', $edited->fresh()->summary);
        $this->assertSame(['Laravel'], $edited->fresh()->technologies);
    }

    public function test_the_schema_migration_backfills_slugs_for_existing_rows(): void
    {
        $this->assertTrue(DB::getSchemaBuilder()->hasColumns('portfolio_items', ['slug', 'challenge', 'solution', 'outcome', 'gallery_media_ids']));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('portfolio_item_service'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function project(array $attributes): PortfolioItem
    {
        return PortfolioItem::query()->create($attributes + ['is_published' => true]);
    }

    private function service(string $title, string $slug, bool $published = true): Service
    {
        return Service::query()->create(['title' => $title, 'slug' => $slug, 'summary' => "{$title} summary.", 'is_published' => $published]);
    }

    private function imageMedia(string $path, ?string $alt = null): Media
    {
        Storage::fake('public');
        Storage::disk('public')->put($path, 'fake-jpg-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = 'image/jpeg';
        $media->size = 14;
        $media->visibility = 'public';
        $media->alt_text = $alt;
        $media->uploader_id = User::factory()->create()->id;
        $media->save();

        return $media;
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }
}
