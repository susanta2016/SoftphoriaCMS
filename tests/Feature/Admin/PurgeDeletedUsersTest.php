<?php

namespace Tests\Feature\Admin;

use App\Actions\Users\PurgeDeletedUsersAction;
use App\Enums\PageStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin → Users → "Permanently delete deleted users": removes only users
 * with the "deleted" status, after typing DELETE; their own rows go by
 * cascade, content they authored is kept without an author.
 */
class PurgeDeletedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_button_is_hidden_when_no_user_is_deleted_and_shows_the_count_otherwise(): void
    {
        $admin = $this->admin();
        User::factory()->create(['status' => 'active']);

        Livewire::actingAs($admin)->test(ListUsers::class)->assertActionHidden('purgeDeletedUsers');

        User::factory()->count(2)->create(['status' => 'deleted']);

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->assertActionVisible('purgeDeletedUsers')
            ->assertActionHasLabel('purgeDeletedUsers', 'Permanently delete deleted users (2)');
    }

    public function test_nothing_is_deleted_unless_delete_is_typed(): void
    {
        $admin = $this->admin();
        User::factory()->create(['status' => 'deleted']);

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->callAction('purgeDeletedUsers', data: ['confirmation' => 'delete'])
            ->assertHasActionErrors(['confirmation' => 'in']);

        $this->assertSame(1, User::query()->where('status', 'deleted')->count());
    }

    public function test_only_deleted_users_are_purged_with_their_own_rows_and_authored_content_is_kept(): void
    {
        $admin = $this->admin();
        $keep = collect(['active', 'suspended', 'banned'])->map(fn (string $s) => User::factory()->create(['status' => $s]));
        $gone = User::factory()->create(['status' => 'deleted', 'email' => 'gone@example.com']);

        $gone->roles()->attach(Role::query()->firstOrCreate(['slug' => 'member'], ['name' => 'Member']));
        $gone->profile()->create(['bio' => 'Old account']);
        $page = Page::query()->create(['title' => 'Written by them', 'slug' => 'written-by-them', 'template' => 'standard', 'status' => PageStatus::Published]);
        $page->forceFill(['author_id' => $gone->id])->save();
        DB::table('sessions')->insert(['id' => 'sess-gone', 'user_id' => $gone->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => 'gone@example.com', 'token' => 'x', 'created_at' => now()]);

        Livewire::actingAs($admin)->test(ListUsers::class)
            ->callAction('purgeDeletedUsers', data: ['confirmation' => 'DELETE'])
            ->assertHasNoActionErrors()
            ->assertNotified('1 user permanently deleted');

        $this->assertDatabaseMissing('users', ['id' => $gone->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $gone->id]);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $gone->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-gone']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'gone@example.com']);

        $this->assertNull($page->refresh()->author_id, 'authored content is kept, without an author');
        foreach ([$admin, ...$keep] as $user) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }

        $audit = AuditLog::query()->where('action', 'user.purged')->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($gone->id, (int) $audit->entity_id);
        $this->assertSame(['previous_status' => 'deleted'], $audit->metadata);
    }

    public function test_the_acting_admin_is_never_purged(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['status' => 'deleted'])->save();

        $this->assertSame(0, app(PurgeDeletedUsersAction::class)->handle($admin));
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));

        return $user;
    }
}
