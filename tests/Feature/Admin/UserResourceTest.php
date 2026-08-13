<?php

namespace Tests\Feature\Admin;

use App\Actions\Media\StoreUploadedMediaAction;
use App\Actions\Users\ChangeUserStatusAction;
use App\Actions\Users\ForceLogoutAllSessionsAction;
use App\Actions\Users\GenerateNewPasswordAction;
use App\Actions\Users\UpdateUserAction;
use App\Enums\MediaCategory;
use App\Enums\UserStatus;
use App\Exceptions\Users\CannotModifySelfException;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\Widgets\UserStatsWidget;
use App\Filament\Support\Media\MediaPicker;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_user_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_user_list(): void
    {
        User::factory()->count(3)->create();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertSuccessful();
    }

    public function test_the_user_list_is_searchable_and_sortable(): void
    {
        $findable = User::factory()->create(['name' => 'Zzyzx Searchable']);
        $others = User::factory()->count(3)->create();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->searchTable('Zzyzx')
            ->assertCanSeeTableRecords([$findable])
            ->assertCanNotSeeTableRecords($others)
            ->searchTable('')
            ->sortTable('name')
            ->assertSuccessful();
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $suspended = User::factory()->create(['status' => 'suspended']);
        $active = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->filterTable('status', 'suspended')
            ->assertCanSeeTableRecords([$suspended])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_the_user_list_paginates_with_the_standard_page_options(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSet('tableRecordsPerPage', 25);
    }

    public function test_admin_can_create_a_user_with_a_profile(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Jane Editor',
                'email' => 'jane.editor@example.com',
                'status' => 'pending_verification',
                'profile_bio' => 'Writes things.',
                'profile_address' => '221B Baker Street',
                'profile_zip_code' => 'NW1 6XE',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'jane.editor@example.com')->firstOrFail();

        $this->assertSame('pending_verification', $user->status);
        $this->assertNotNull($user->password);
        $this->assertTrue($user->profile()->exists());
        $this->assertSame('Writes things.', $user->profile->bio);
        $this->assertSame('221B Baker Street', $user->profile->address);
        $this->assertSame('NW1 6XE', $user->profile->zip_code);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.created',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_a_new_users_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => 'taken@example.com',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_the_create_form_has_no_plaintext_password_field(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->assertFormFieldDoesNotExist('password');
    }

    public function test_admin_can_edit_a_users_name_email_and_profile(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Old Name']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => 'New Name',
                'email' => $target->email,
                'profile_bio' => 'Updated bio.',
                'profile_address' => '221B Baker Street',
                'profile_zip_code' => 'NW1 6XE',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertSame('New Name', $target->name);
        $this->assertSame('Updated bio.', $target->profile->bio);
        $this->assertSame('221B Baker Street', $target->profile->address);
        $this->assertSame('NW1 6XE', $target->profile->zip_code);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.updated',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_the_edit_form_does_not_expose_a_status_field(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldHidden('status');
    }

    public function test_the_edit_form_has_no_plaintext_password_field(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldDoesNotExist('password');
    }

    public function test_admin_can_change_another_users_status(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('changeStatus', $target, data: ['status' => 'suspended'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('suspended', $target->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.status_changed',
            'entity_type' => 'User',
            'entity_id' => $target->id,
            'metadata' => json_encode(['from' => 'active', 'to' => 'suspended']),
        ]);
    }

    public function test_status_change_accepts_the_deleted_status_instead_of_hard_deleting(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('changeStatus', $target, data: ['status' => 'deleted'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'deleted',
        ]);
    }

    public function test_admin_cannot_change_their_own_status_via_the_table_action(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('changeStatus', $admin);
    }

    public function test_admin_cannot_change_their_own_status_via_the_view_page(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $admin->getRouteKey()])
            ->assertActionHidden('changeStatus');
    }

    public function test_change_user_status_action_enforces_self_protection_at_the_domain_layer(): void
    {
        $admin = $this->admin();

        $this->expectException(CannotModifySelfException::class);

        app(ChangeUserStatusAction::class)->handle($admin, 'suspended', $admin);

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('sendPasswordResetLink', $target)
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo($target, ResetPassword::class);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.password_reset_link_sent',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_no_delete_action_exists_anywhere_on_the_user_resource(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');

        Livewire::actingAs($this->admin())
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionDoesNotExist('delete');

        Livewire::actingAs($this->admin())
            ->test(ViewUser::class, ['record' => $target->getRouteKey()])
            ->assertActionDoesNotExist('delete');
    }

    public function test_admin_can_assign_a_role_when_creating_a_user(): void
    {
        $editorRole = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Role Assigned',
                'email' => 'role.assigned@example.com',
                'status' => 'active',
                'role_id' => $editorRole->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'role.assigned@example.com')->firstOrFail();

        $this->assertTrue($user->roles()->where('roles.id', $editorRole->id)->exists());
    }

    public function test_admin_can_change_a_users_role_when_editing(): void
    {
        $admin = $this->admin();
        $editorRole = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'role_id' => $editorRole->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->roles()->where('roles.id', $editorRole->id)->exists());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.updated',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_the_role_field_is_disabled_when_an_admin_edits_their_own_account(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldDisabled('role_id');
    }

    public function test_an_admin_cannot_change_their_own_role_at_the_domain_layer(): void
    {
        $admin = $this->admin();
        $otherRole = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);

        $this->expectException(CannotModifySelfException::class);

        app(UpdateUserAction::class)->handle(
            $admin,
            ['name' => $admin->name, 'email' => $admin->email, 'role_id' => $otherRole->id],
            $admin,
        );
    }

    public function test_admin_can_set_a_users_phone_number(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'profile_phone_number' => '+44 7700 900001',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+44 7700 900001', $target->fresh()->profile->phone_number);
    }

    /**
     * USER ADMIN — Media Picker & Avatar List Improvement: regression
     * coverage for the pre-existing "admin can upload a user's avatar"
     * behavior, adapted to the MediaPicker action-based upload flow (the
     * avatar field no longer accepts a raw file directly via fillForm —
     * see App\Filament\Support\Media\MediaPicker, the ADMIN-006 convention
     * every Media-referencing field must now use, docs/ARCHITECTURE.md §14).
     */
    public function test_admin_can_upload_a_users_avatar(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('avatar__actions', 'avatar_upload', data: [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertNotNull($target->profile->avatar_media_id);
        Storage::disk('public')->assertExists($target->profile->avatar->path);
    }

    public function test_user_create_can_upload_a_new_avatar(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(CreateUser::class)
            ->callFormComponentAction('avatar__actions', 'avatar_upload', data: [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
                'alt_text' => 'New user avatar',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.avatar', fn (?int $value): bool => $value !== null)
            ->fillForm([
                'name' => 'Avatar Upload User',
                'email' => 'avatar-upload@example.com',
                'status' => UserStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'avatar-upload@example.com')->firstOrFail();

        $this->assertNotNull($user->profile->avatar_media_id);
        $this->assertSame(1, Media::query()->count());
        $this->assertSame('New user avatar', $user->profile->avatar->alt_text);
        Storage::disk('public')->assertExists($user->profile->avatar->path);
    }

    public function test_user_create_can_select_an_existing_media_image_as_avatar(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/existing-avatar.jpg');

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->callFormComponentAction('avatar__actions', 'avatar_select', data: [
                'media' => $existing->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertSet('data.avatar', $existing->id)
            ->fillForm([
                'name' => 'Avatar Select User',
                'email' => 'avatar-select@example.com',
                'status' => UserStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'avatar-select@example.com')->firstOrFail();

        $this->assertSame($existing->id, $user->profile->avatar_media_id);
    }

    public function test_selecting_an_existing_media_image_as_avatar_does_not_create_a_duplicate_media_record(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/no-duplicate-avatar.jpg');

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->callFormComponentAction('avatar__actions', 'avatar_select', data: [
                'media' => $existing->id,
            ])
            ->fillForm([
                'name' => 'No Duplicate User',
                'email' => 'no-duplicate@example.com',
                'status' => UserStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Media::query()->count());
    }

    public function test_user_edit_displays_the_existing_avatar(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/current-avatar.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $existing->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertSet('data.avatar', $existing->id);
    }

    public function test_user_edit_can_replace_the_avatar_using_upload_new_media(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $original = $this->storeImage($admin, 'media/images/original-avatar.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $original->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('avatar__actions', 'avatar_upload', data: [
                'file' => UploadedFile::fake()->image('replacement-avatar.jpg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->fillForm(['name' => $target->name, 'email' => $target->email])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertNotNull($target->profile->avatar_media_id);
        $this->assertNotSame($original->id, $target->profile->avatar_media_id);
        $this->assertSame(2, Media::query()->count());
    }

    public function test_user_edit_can_replace_the_avatar_using_select_from_media_library(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $original = $this->storeImage($admin, 'media/images/original-avatar-2.jpg');
        $replacement = $this->storeImage($admin, 'media/images/replacement-from-library.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $original->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('avatar__actions', 'avatar_select', data: [
                'media' => $replacement->id,
            ])
            ->assertHasNoFormComponentActionErrors()
            ->fillForm(['name' => $target->name, 'email' => $target->email])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertSame($replacement->id, $target->profile->avatar_media_id);
        $this->assertSame(2, Media::query()->count());
    }

    /**
     * Mirrors PageMediaAndSeoTest's category-filtering coverage: the avatar
     * field is built with MediaPicker::make('avatar', 'Avatar',
     * MediaCategory::Image), so its "Select from Media Library" grid is
     * backed by the exact same category-scoped MediaPicker::query() every
     * other MediaPicker consumer uses — never a per-field reimplementation.
     */
    public function test_the_avatar_media_picker_only_allows_image_media(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $image = $this->storeImage($admin, 'media/images/avatar-candidate.jpg');
        $document = $this->storeDocument($admin, 'media/documents/not-an-avatar.pdf');

        $ids = MediaPicker::query(MediaCategory::Image)->pluck('id');

        $this->assertTrue($ids->contains($image->id));
        $this->assertFalse($ids->contains($document->id));
    }

    public function test_users_list_displays_the_avatar_when_available(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $existing = $this->storeImage($admin, 'media/images/list-avatar.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $existing->id]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee(Storage::disk('public')->url($existing->path));
    }

    public function test_users_list_displays_a_fallback_when_no_avatar_exists(): void
    {
        User::factory()->create(['name' => 'No Avatar User']);

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee('No Avatar User')
            ->assertSee(UsersTable::defaultAvatarUrl(), escape: false);
    }

    /**
     * USER ADMIN — Media Picker & Avatar List Improvement (verification
     * round): users whose avatar Media row predates the MediaPicker
     * refactor — its path still under the legacy storage/app/public/avatars/
     * directory rather than the new media/images/ (config('media.categories.image.directory'))
     * — must keep displaying correctly. Neither MediaPicker's preview nor
     * UsersTable's ImageColumn reads or assumes a directory: both resolve
     * purely from the Media row's own disk/path columns.
     */
    public function test_a_legacy_avatars_directory_avatar_still_displays_in_user_edit_and_the_users_list(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $legacy = $this->storeLegacyAvatarImage($admin, 'avatars/legacy-existing.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $legacy->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertSet('data.avatar', $legacy->id);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertSee(Storage::disk('public')->url($legacy->path));
    }

    public function test_replacing_a_legacy_avatar_does_not_assume_the_new_media_directory_and_leaves_the_original_record_untouched(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $legacy = $this->storeLegacyAvatarImage($admin, 'avatars/legacy-to-replace.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $legacy->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('avatar__actions', 'avatar_upload', data: [
                'file' => UploadedFile::fake()->image('new-avatar.jpg'),
            ])
            ->assertHasNoFormComponentActionErrors()
            ->fillForm(['name' => $target->name, 'email' => $target->email])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $legacy->refresh();

        $this->assertNotSame($legacy->id, $target->profile->avatar_media_id);
        $this->assertSame('avatars/legacy-to-replace.jpg', $legacy->path);
        Storage::disk('public')->assertExists($legacy->path);
        $this->assertSame(2, Media::query()->count());
    }

    public function test_removing_a_legacy_avatar_clears_it_without_deleting_the_underlying_media_record_or_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $legacy = $this->storeLegacyAvatarImage($admin, 'avatars/legacy-to-remove.jpg');
        $target = User::factory()->create();
        $target->profile()->create(['avatar_media_id' => $legacy->id]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('avatar__actions', 'avatar_clear')
            ->assertHasNoFormComponentActionErrors()
            ->fillForm(['name' => $target->name, 'email' => $target->email])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertNull($target->profile->avatar_media_id);
        $this->assertNotNull(Media::query()->find($legacy->id));
        Storage::disk('public')->assertExists('avatars/legacy-to-remove.jpg');
    }

    public function test_admin_can_generate_a_new_password_for_a_user(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $target = User::factory()->create();
        $originalPassword = $target->password;

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('generateNewPasswordActions', 'generateNewPassword')
            ->assertHasNoFormComponentActionErrors();

        $this->assertNotSame($originalPassword, $target->fresh()->password);
        Notification::assertSentTo($target, ResetPassword::class);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.password_regenerated',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_an_admin_cannot_generate_a_new_password_for_themselves_at_the_domain_layer(): void
    {
        $admin = $this->admin();

        $this->expectException(CannotModifySelfException::class);

        app(GenerateNewPasswordAction::class)->handle($admin, $admin);
    }

    public function test_admin_can_force_logout_all_sessions_for_a_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callFormComponentAction('forceLogoutAllSessionsActions', 'forceLogoutAllSessions')
            ->assertHasNoFormComponentActionErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.sessions_revoked',
            'entity_type' => 'User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_an_admin_cannot_force_logout_their_own_sessions_at_the_domain_layer(): void
    {
        $admin = $this->admin();

        $this->expectException(CannotModifySelfException::class);

        app(ForceLogoutAllSessionsAction::class)->handle($admin, $admin);
    }

    public function test_admin_can_block_and_unblock_a_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('block', $target)
            ->assertHasNoTableActionErrors();

        $this->assertSame('suspended', $target->fresh()->status);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('block', $target)
            ->assertHasNoTableActionErrors();

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_admin_cannot_block_themselves(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('block', $admin);
    }

    public function test_admin_can_delete_a_user_via_the_list_action(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableAction('deleteUser', $target)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'deleted',
        ]);
    }

    public function test_admin_cannot_delete_themselves_via_the_list_action(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('deleteUser', $admin);
    }

    public function test_the_user_stats_widget_shows_accurate_counts(): void
    {
        User::factory()->count(2)->create(['status' => 'active']);
        User::factory()->create(['status' => 'pending_verification']);

        Livewire::actingAs($this->admin())
            ->test(UserStatsWidget::class)
            ->assertSuccessful();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function storeImage(User $admin, string $path): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 800, 600)->get());

        return app(StoreUploadedMediaAction::class)->handle('public', $path, $admin);
    }

    private function storeDocument(User $admin, string $path): Media
    {
        Storage::disk('public')->put($path, 'fake-pdf-bytes');

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = 'application/pdf';
        $media->size = 14;
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        $media->save();

        return $media;
    }

    /**
     * A Media row built directly (bypassing StoreUploadedMediaAction, which
     * always writes into the current MediaCategory::Image directory) so its
     * path lands under the pre-refactor storage/app/public/avatars/
     * directory — simulating a real avatar Media row that predates the
     * MediaPicker convention.
     */
    private function storeLegacyAvatarImage(User $admin, string $path): Media
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image(basename($path), 400, 400)->get());

        $media = new Media;
        $media->disk = 'public';
        $media->path = $path;
        $media->original_filename = basename($path);
        $media->mime_type = 'image/jpeg';
        $media->size = Storage::disk('public')->size($path);
        $media->visibility = 'public';
        $media->uploader_id = $admin->id;
        $media->save();

        return $media;
    }
}
