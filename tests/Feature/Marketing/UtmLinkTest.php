<?php

namespace Tests\Feature\Marketing;

use App\Filament\Resources\UtmLinks\Pages\CreateUtmLink;
use App\Filament\Resources\UtmLinks\Pages\EditUtmLink;
use App\Filament\Resources\UtmLinks\Pages\ListUtmLinks;
use App\Filament\Resources\UtmLinks\Schemas\UtmLinkForm;
use App\Models\Role;
use App\Models\User;
use App\Models\UtmLink;
use App\Shared\Support\Marketing\UtmDestination;
use App\Shared\Support\Marketing\UtmUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin → UTM Links: URL generation (UtmUrlGenerator), destination safety
 * (UtmDestination) and the admin create/edit/list screens.
 */
class UtmLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://allthethingslight.com']);
    }

    // ------------------------------------------------------------ Generation

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function channels(): array
    {
        return [
            'Apple Music' => ['apple_music', 'music', 'https://allthethingslight.com/register?utm_source=apple_music&utm_medium=music&utm_campaign=launch'],
            'Podcast' => ['podcast', 'podcast', 'https://allthethingslight.com/register?utm_source=podcast&utm_medium=podcast&utm_campaign=launch'],
            'Instagram' => ['instagram', 'social', 'https://allthethingslight.com/register?utm_source=instagram&utm_medium=social&utm_campaign=launch'],
            'Facebook' => ['facebook', 'social', 'https://allthethingslight.com/register?utm_source=facebook&utm_medium=social&utm_campaign=launch'],
            'Email' => ['email', 'email', 'https://allthethingslight.com/register?utm_source=email&utm_medium=email&utm_campaign=launch'],
        ];
    }

    #[DataProvider('channels')]
    public function test_each_requested_channel_generates_its_link(string $source, string $medium, string $expected): void
    {
        $this->assertSame($medium, config("utm.channels.{$source}.medium"));

        $url = UtmUrlGenerator::generate('/register', [
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => 'launch',
        ]);

        $this->assertSame($expected, $url);
    }

    public function test_optional_content_and_term_are_included_only_when_filled(): void
    {
        $base = ['utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'summer_launch'];

        $this->assertSame(
            'https://allthethingslight.com/register?utm_source=instagram&utm_medium=social&utm_campaign=summer_launch&utm_content=bio&utm_term=gratitude',
            UtmUrlGenerator::generate('/register', [...$base, 'utm_content' => 'bio', 'utm_term' => 'gratitude']),
        );

        $this->assertSame(
            'https://allthethingslight.com/register?utm_source=instagram&utm_medium=social&utm_campaign=summer_launch',
            UtmUrlGenerator::generate('/register', [...$base, 'utm_content' => '', 'utm_term' => null]),
        );
    }

    public function test_values_are_url_encoded(): void
    {
        $url = UtmUrlGenerator::generate('/register', [
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'Summer Launch & More',
        ]);

        $this->assertSame('https://allthethingslight.com/register?utm_source=instagram&utm_medium=social&utm_campaign=Summer%20Launch%20%26%20More', $url);
    }

    public function test_an_existing_destination_query_string_is_preserved_and_utm_keys_are_never_duplicated(): void
    {
        $params = ['utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'summer_launch'];

        $this->assertSame(
            'https://allthethingslight.com/register?ref=music&utm_source=instagram&utm_medium=social&utm_campaign=summer_launch',
            UtmUrlGenerator::generate('https://allthethingslight.com/register?ref=music', $params),
        );

        $this->assertSame(
            'https://allthethingslight.com/register?ref=music&utm_source=instagram&utm_medium=social&utm_campaign=summer_launch#plans',
            UtmUrlGenerator::generate('/register?utm_source=old&ref=music&UTM_CAMPAIGN=old#plans', $params),
        );
    }

    public function test_links_always_use_the_configured_app_url(): void
    {
        config(['app.url' => 'https://staging.allthethingslight.com/']);

        $this->assertSame(
            'https://staging.allthethingslight.com/music?utm_source=email&utm_medium=email&utm_campaign=news',
            UtmUrlGenerator::generate('/music', ['utm_source' => 'email', 'utm_medium' => 'email', 'utm_campaign' => 'news']),
        );
    }

    // -------------------------------------------------------------- Security

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeDestinations(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'file scheme' => ['file:///etc/passwd'],
            'another domain' => ['https://evil.example.com/register'],
            'look-alike domain' => ['https://allthethingslight.com.evil.example/register'],
            'protocol-relative' => ['//evil.example.com/register'],
            'backslash trick' => ['/\\evil.example.com'],
            'embedded credentials' => ['https://user:pass@allthethingslight.com/register'],
            'bare word' => ['register'],
            'whitespace' => ['/register page'],
        ];
    }

    #[DataProvider('unsafeDestinations')]
    public function test_unsafe_destinations_are_rejected(string $destination): void
    {
        $this->assertFalse(UtmDestination::isValid($destination));
    }

    public function test_own_site_destinations_are_accepted_and_stored_site_relative(): void
    {
        $this->assertSame('/register', UtmDestination::toRelative('/register'));
        $this->assertSame('/', UtmDestination::toRelative('https://allthethingslight.com'));
        $this->assertSame('/music?ref=x', UtmDestination::toRelative('https://www.allthethingslight.com/music?ref=x'));
        $this->assertSame('/podcast#latest', UtmDestination::toRelative('http://allthethingslight.com/podcast#latest'));
    }

    // ----------------------------------------------------------------- Admin

    public function test_admin_can_create_a_link_and_the_channel_fills_in_the_medium(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateUtmLink::class)
            ->fillForm([
                'name' => 'Instagram bio',
                'channel' => 'instagram',
                'utm_campaign' => 'summer_launch',
                'utm_content' => 'bio',
                'destination' => 'https://allthethingslight.com/register?ref=music',
            ])
            ->assertFormSet(['utm_medium' => 'social'])
            ->call('create')
            ->assertHasNoFormErrors();

        $link = UtmLink::query()->sole();

        $this->assertSame('instagram', $link->utm_source);
        $this->assertSame('social', $link->utm_medium);
        $this->assertSame('/register?ref=music', $link->destination);
        $this->assertNull($link->utm_term);
        $this->assertTrue($link->is_enabled);
        $this->assertSame($admin->id, $link->created_by);
        $this->assertSame(
            'https://allthethingslight.com/register?ref=music&utm_source=instagram&utm_medium=social&utm_campaign=summer_launch&utm_content=bio',
            $link->url(),
        );
    }

    public function test_any_public_page_can_be_the_destination(): void
    {
        foreach (['/music', '/music/tracks/some-track', '/podcast/episodes/episode-one', '/inspirational-resources', '/light-posts/abc123', '/about'] as $path) {
            $this->assertSame($path, UtmDestination::toRelative('https://allthethingslight.com'.$path));
            $this->assertSame(
                'https://allthethingslight.com'.$path.'?utm_source=instagram&utm_medium=social&utm_campaign=october_launch',
                UtmUrlGenerator::generate($path, ['utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'october_launch']),
            );
        }

        Livewire::actingAs($this->admin())
            ->test(CreateUtmLink::class)
            ->assertFormSet(['destination' => null])
            ->fillForm([
                'name' => 'Instagram — track',
                'channel' => 'instagram',
                'utm_campaign' => 'october_launch',
                'destination' => 'https://allthethingslight.com/music/tracks/some-track',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'https://allthethingslight.com/music/tracks/some-track?utm_source=instagram&utm_medium=social&utm_campaign=october_launch',
            UtmLink::query()->sole()->url(),
        );
    }

    public function test_admin_can_create_a_link_for_a_custom_channel(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUtmLink::class)
            ->fillForm([
                'name' => 'YouTube description',
                'channel' => UtmLinkForm::CUSTOM_CHANNEL,
                'utm_source' => 'youtube',
                'utm_medium' => 'video',
                'utm_campaign' => 'launch',
                'destination' => '/register',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('youtube', UtmLink::query()->sole()->utm_source);
    }

    public function test_admin_can_edit_a_link(): void
    {
        $link = $this->link(['utm_source' => 'facebook', 'utm_medium' => 'social']);

        Livewire::actingAs($this->admin())
            ->test(EditUtmLink::class, ['record' => $link->getRouteKey()])
            ->assertFormSet(['channel' => 'facebook', 'utm_campaign' => 'launch'])
            ->fillForm(['utm_campaign' => 'autumn_followup', 'is_enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $link->refresh();

        $this->assertSame('facebook', $link->utm_source);
        $this->assertSame('autumn_followup', $link->utm_campaign);
        $this->assertFalse($link->is_enabled);
    }

    public function test_editing_a_custom_source_link_keeps_its_source(): void
    {
        $link = $this->link(['utm_source' => 'youtube', 'utm_medium' => 'video']);

        Livewire::actingAs($this->admin())
            ->test(EditUtmLink::class, ['record' => $link->getRouteKey()])
            ->assertFormSet(['channel' => UtmLinkForm::CUSTOM_CHANNEL, 'utm_source' => 'youtube'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('youtube', $link->refresh()->utm_source);
    }

    #[DataProvider('unsafeDestinations')]
    public function test_the_admin_form_rejects_unsafe_destinations(string $destination): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUtmLink::class)
            ->fillForm([
                'name' => 'Bad link',
                'channel' => 'email',
                'utm_campaign' => 'launch',
                'destination' => $destination,
            ])
            ->call('create')
            ->assertHasFormErrors(['destination']);

        $this->assertSame(0, UtmLink::query()->count());
    }

    public function test_the_admin_form_rejects_unsafe_utm_values(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUtmLink::class)
            ->fillForm([
                'name' => 'Bad values',
                'channel' => 'email',
                'utm_campaign' => '<script>alert(1)</script>',
                'utm_content' => 'a&b=c',
                'destination' => '/register',
            ])
            ->call('create')
            ->assertHasFormErrors(['utm_campaign', 'utm_content']);
    }

    public function test_the_list_shows_each_links_channel_and_copyable_url(): void
    {
        $instagram = $this->link(['name' => 'Instagram bio']);
        $disabled = $this->link(['name' => 'Old email', 'utm_source' => 'email', 'utm_medium' => 'email', 'is_enabled' => false]);

        Livewire::actingAs($this->admin())
            ->test(ListUtmLinks::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$instagram, $disabled])
            ->assertTableColumnStateSet('generated_url', $instagram->url(), $instagram)
            ->assertTableColumnFormattedStateSet('utm_source', 'Instagram', $instagram)
            ->filterTable('is_enabled', false)
            ->assertCanSeeTableRecords([$disabled])
            ->assertCanNotSeeTableRecords([$instagram]);
    }

    public function test_the_copy_button_copies_the_full_link_not_the_shortened_display(): void
    {
        $link = $this->link(['utm_campaign' => 'a_long_campaign_name_for_october', 'utm_content' => 'instagram_story']);
        $this->assertGreaterThan(60, strlen($link->url()));

        $column = Livewire::actingAs($this->admin())
            ->test(ListUtmLinks::class)
            ->instance()
            ->getTable()
            ->getColumn('generated_url')
            ->record($link);

        $this->assertSame($link->url(), $column->getCopyableState($column->getState()));
    }

    public function test_non_admins_cannot_reach_utm_links(): void
    {
        $member = User::factory()->create(['status' => 'active']);

        $this->actingAs($member)->get('/admin/utm-links')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function link(array $overrides = []): UtmLink
    {
        return UtmLink::query()->create([
            'name' => 'Instagram bio',
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'launch',
            'destination' => '/register',
            'is_enabled' => true,
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
