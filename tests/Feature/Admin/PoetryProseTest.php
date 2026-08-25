<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Modules\PoetryProse\Actions\RestorePoetryProseRevisionAction;
use App\Modules\PoetryProse\Enums\PoetryProseContentType;
use App\Modules\PoetryProse\Enums\PoetryProseStatus;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\CreatePoetryProse;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\EditPoetryProse;
use App\Modules\PoetryProse\Filament\Resources\PoetryProses\Pages\ListPoetryProses;
use App\Modules\PoetryProse\Models\PoetryProse;
use App\Modules\PoetryProse\Models\PoetryProseCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Poetry/Prose CRUD, taxonomy (fixed content_type enum + freeform admin
 * Category/Tag + one belongsTo Collection per entry), and revision history
 * — see database/migrations/2026_08_10_100801_create_poetry_prose_table.php
 * and siblings for the already-fixed schema this builds on.
 */
class PoetryProseTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_poetry_prose(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/poetry-proses');

        $response->assertForbidden();
    }

    public function test_admin_can_create_an_entry_with_categories_tags_and_a_collection(): void
    {
        $category = Category::query()->create(['type' => 'poetry_prose', 'name' => 'Grief', 'slug' => 'grief']);
        $tag = Tag::query()->create(['name' => 'Hope', 'slug' => 'hope']);
        $collection = PoetryProseCollection::query()->create(['title' => 'Reflections on Grief', 'slug' => 'reflections-on-grief', 'status' => PoetryProseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(CreatePoetryProse::class)
            ->fillForm([
                'title' => 'On Letting Go',
                'slug' => 'on-letting-go',
                'body' => '<p>A reflection.</p>',
                'content_type' => PoetryProseContentType::Reflection->value,
                'status' => PoetryProseStatus::Draft->value,
                'categoryIds' => [$category->id],
                'tagIds' => [$tag->id],
                'collection_id' => $collection->id,
                'seo' => ['meta_title' => 'On Letting Go'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = PoetryProse::query()->where('slug', 'on-letting-go')->firstOrFail();

        $this->assertSame(PoetryProseContentType::Reflection, $entry->content_type);
        $this->assertTrue($entry->categories->contains($category));
        $this->assertTrue($entry->tags->contains($tag));
        $this->assertTrue($entry->collection->is($collection));
        $this->assertSame('On Letting Go', $entry->seo->meta_title);
    }

    public function test_creating_an_entry_writes_an_initial_revision(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreatePoetryProse::class)
            ->fillForm([
                'title' => 'A New Essay',
                'slug' => 'a-new-essay',
                'body' => '<p>Body.</p>',
                'content_type' => PoetryProseContentType::Essay->value,
                'status' => PoetryProseStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = PoetryProse::query()->where('slug', 'a-new-essay')->firstOrFail();

        $this->assertSame(1, $entry->revisions()->count());
        $this->assertSame('A New Essay', $entry->revisions()->first()->snapshot_json['title']);
    }

    public function test_updating_an_entry_writes_a_new_revision_and_can_be_restored(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreatePoetryProse::class)
            ->fillForm([
                'title' => 'The Power of Presence',
                'slug' => 'the-power-of-presence',
                'body' => '<p>Body.</p>',
                'content_type' => PoetryProseContentType::Essay->value,
                'status' => PoetryProseStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = PoetryProse::query()->where('slug', 'the-power-of-presence')->firstOrFail();
        $this->assertSame(1, $entry->revisions()->count());

        Livewire::actingAs($admin)
            ->test(EditPoetryProse::class, ['record' => $entry->getRouteKey()])
            ->fillForm(['title' => 'Updated Title'])
            ->call('save')
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame('Updated Title', $entry->title);
        $this->assertSame(2, $entry->revisions()->count());

        // PoetryProse::revisions() already applies orderByDesc('version');
        // reorder() clears that before re-sorting ascending, since a plain
        // chained orderBy() would only add a second, redundant clause on
        // the same column and the original DESC order would still win.
        $firstRevision = $entry->revisions()->reorder('version')->first();

        app(RestorePoetryProseRevisionAction::class)->handle($entry, $firstRevision, $admin);

        $entry->refresh();
        $this->assertSame('The Power of Presence', $entry->title);
        // Restoring is itself a new revision — never rewinds the counter.
        $this->assertSame(3, $entry->revisions()->count());
    }

    public function test_admin_can_view_the_list(): void
    {
        $entry = $this->createEntry();

        Livewire::actingAs($this->admin())
            ->test(ListPoetryProses::class)
            ->assertCanSeeTableRecords([$entry]);
    }

    public function test_admin_can_delete_an_entry(): void
    {
        $entry = $this->createEntry();

        Livewire::actingAs($this->admin())
            ->test(ListPoetryProses::class)
            ->callTableAction('delete', $entry);

        $this->assertSoftDeleted('poetry_prose', ['id' => $entry->id]);
    }

    public function test_an_entry_belongs_to_at_most_one_collection_at_a_time(): void
    {
        $entry = $this->createEntry();
        $collectionA = PoetryProseCollection::query()->create(['title' => 'Collection A', 'slug' => 'collection-a', 'status' => PoetryProseStatus::Published]);
        $collectionB = PoetryProseCollection::query()->create(['title' => 'Collection B', 'slug' => 'collection-b', 'status' => PoetryProseStatus::Published]);

        Livewire::actingAs($this->admin())
            ->test(EditPoetryProse::class, ['record' => $entry->getRouteKey()])
            ->fillForm(['collection_id' => $collectionA->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($entry->refresh()->collection->is($collectionA));

        // Reassigning replaces the previous collection outright — there is
        // no many-to-many membership to accumulate.
        Livewire::actingAs($this->admin())
            ->test(EditPoetryProse::class, ['record' => $entry->getRouteKey()])
            ->fillForm(['collection_id' => $collectionB->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($entry->refresh()->collection->is($collectionB));
    }

    public function test_non_admin_cannot_access_poetry_prose_collections(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/poetry-prose-collections');

        $response->assertForbidden();
    }

    private function createEntry(): PoetryProse
    {
        $actor = $this->admin();

        return PoetryProse::query()->create([
            'title' => 'The Power of Presence',
            'slug' => 'the-power-of-presence',
            'body' => '<p>Body.</p>',
            'content_type' => PoetryProseContentType::Essay,
            'status' => PoetryProseStatus::Draft,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
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
