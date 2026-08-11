<?php

namespace App\Actions\Users;

use App\Actions\Users\Concerns\ResolvesAvatarMedia;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates an admin-managed user. The password is never set by the
 * administrator (ADMIN-003 boundary): a random hash is stored and the
 * account is only usable once the user completes the existing
 * password-reset flow, triggered separately via SendUserPasswordResetLinkAction.
 */
class CreateUserAction
{
    use ResolvesAvatarMedia;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->status = $data['status'];
            $user->password = Hash::make(Str::random(40));
            $user->save();

            if (! empty($data['role_id'])) {
                $user->roles()->sync([(int) $data['role_id']]);
            }

            $profileData = array_filter([
                'bio' => $data['profile_bio'] ?? null,
                'phone_number' => $data['profile_phone_number'] ?? null,
                'address' => $data['profile_address'] ?? null,
                'zip_code' => $data['profile_zip_code'] ?? null,
            ], fn ($value) => filled($value));

            $profileData = array_merge(
                $profileData,
                $this->resolveAvatarMediaId($user, $data['avatar'] ?? null, $actor),
            );

            if ($profileData !== []) {
                $user->profile()->create($profileData);
            }

            $this->auditLog->record($actor, 'user.created', $user, [
                'status' => $user->status,
            ]);

            return $user;
        });
    }
}
