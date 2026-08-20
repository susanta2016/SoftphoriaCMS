<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Filament\Resources\Singles\Pages\CreateSingle;
use App\Modules\Music\Filament\Resources\Singles\Pages\EditSingle;
use App\Modules\Music\Filament\Resources\Singles\Pages\ListSingles;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A one-song release (Database Specification §19's `singles` table). See
 * TrackTest for track CRUD and the Album/Single release relationship
 * hardening tests.
 */
class SingleTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_singles(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/singles');

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_single_with_links_and_seo(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSingle::class)
            ->fillForm([
                'title' => 'Still Water',
                'slug' => 'still-water',
                'description' => 'A single about stillness.',
                'status' => ReleaseStatus::Published->value,
                'is_featured' => true,
                'links' => [
                    ['provider' => 'apple_music', 'url' => 'https://music.apple.com/single/xyz'],
                ],
                'seo' => ['meta_title' => 'Still Water'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $single = Single::query()->where('slug', 'still-water')->firstOrFail();

        $this->assertTrue($single->is_featured);
        $this->assertCount(1, $single->streamingLinks);
        $this->assertSame('https://music.apple.com/single/xyz', $single->streamingLinks->first()->url);
        $this->assertSame('Still Water', $single->seo->meta_title);
    }

    public function test_admin_can_view_the_single_list(): void
    {
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(ListSingles::class)
            ->assertCanSeeTableRecords([$single]);
    }

    public function test_admin_can_update_a_single(): void
    {
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(EditSingle::class, ['record' => $single->getRouteKey()])
            ->fillForm(['title' => 'Renamed Single'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Single', $single->refresh()->title);
    }

    public function test_deleting_a_single_with_a_track_is_blocked(): void
    {
        $single = $this->createSingle();
        Track::query()->create([
            'single_id' => $single->id,
            'title' => 'Still Water',
            'slug' => 'still-water-track',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListSingles::class)
            ->callTableAction('deleteSingle', $single)
            ->assertNotified();

        $this->assertDatabaseHas('singles', ['id' => $single->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_single_with_no_track_succeeds(): void
    {
        $single = $this->createSingle();

        Livewire::actingAs($this->admin())
            ->test(ListSingles::class)
            ->callTableAction('deleteSingle', $single);

        $this->assertSoftDeleted('singles', ['id' => $single->id]);
    }

    /**
     * Requirement 7 of the hardening pass: no artificial "only one featured
     * release" constraint should exist unless explicitly required.
     */
    public function test_multiple_singles_can_be_featured_simultaneously(): void
    {
        $first = $this->createSingle(['is_featured' => true]);
        $second = Single::query()->create([
            'title' => 'Second Single',
            'slug' => 'second-single',
            'status' => ReleaseStatus::Published,
            'is_featured' => true,
        ]);

        $this->assertTrue($first->refresh()->is_featured);
        $this->assertTrue($second->refresh()->is_featured);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSingle(array $overrides = []): Single
    {
        return Single::query()->create([
            'title' => 'Still Water',
            'slug' => 'still-water',
            'status' => ReleaseStatus::Draft,
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }
}
