<?php

namespace App\Actions\Users;

use App\Actions\Users\Concerns\ResolvesAvatarMedia;
use App\Exceptions\Users\CannotModifySelfException;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Updates the admin-editable identity/profile fields for a user. Status is
 * deliberately excluded — status transitions only happen through
 * ChangeUserStatusAction, which carries the self-protection guard and its
 * own audit action.
 */
class UpdateUserAction
{
    use ResolvesAvatarMedia;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);
            $changedAttributes = array_keys($user->getDirty());
            $user->save();

            // The role field is disabled (and therefore not dehydrated/submitted)
            // when an admin is editing their own account, so a missing key here
            // means "field wasn't part of this submission", not "clear the role".
            if (array_key_exists('role_id', $data)) {
                $currentRoleId = $user->roles()->orderBy('roles.id')->value('roles.id');
                $newRoleId = $data['role_id'] !== null ? (int) $data['role_id'] : null;

                if ($newRoleId !== $currentRoleId) {
                    if ($user->is($actor)) {
                        throw CannotModifySelfException::forRoleChange();
                    }

                    $user->roles()->sync($newRoleId ? [$newRoleId] : []);
                    $changedAttributes[] = 'role';
                }
            }

            $profileData = array_filter([
                'bio' => $data['profile_bio'] ?? null,
                'phone_number' => $data['profile_phone_number'] ?? null,
                'address' => $data['profile_address'] ?? null,
                'zip_code' => $data['profile_zip_code'] ?? null,
            ], fn ($value) => filled($value));

            $profileData = array_merge(
                $profileData,
                $this->resolveAvatarMediaId($user, $data['avatar'] ?? null),
            );

            if ($profileData !== []) {
                $user->profile()->updateOrCreate([], $profileData);
                $changedAttributes[] = 'profile';
            }

            if ($changedAttributes !== []) {
                $this->auditLog->record($actor, 'user.updated', $user, [
                    'changed' => $changedAttributes,
                ]);
            }

            return $user;
        });
    }
}
