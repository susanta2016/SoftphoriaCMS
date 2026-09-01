<?php

namespace App\Actions\Registration\Concerns;

use App\Models\User;

/**
 * Shared by Free/Pro registration. Phone Number, Address, and Zip Code are
 * no longer collected on the registration form (they remain admin-settable
 * fields on the User Profile — see UserForm/CreateUserAction) — only
 * Biography ever reaches here now, and it too is no longer collected on the
 * registration form itself (Phase 1: replaced by the "Leave a Little Light"
 * prompt — see CreatesLightPostOnRegistration), kept in case a future caller
 * ever passes it. array_filter keeps a blank value from ever creating a
 * user_profiles row with an empty string, same as CreateUserAction's
 * behavior.
 */
trait SavesOptionalRegistrationProfile
{
    /**
     * @param  array{bio?: ?string}  $data
     */
    protected function saveOptionalProfile(User $user, array $data): void
    {
        $profileData = array_filter([
            'bio' => $data['bio'] ?? null,
        ], fn ($value) => filled($value));

        if ($profileData !== []) {
            $user->profile()->create($profileData);
        }
    }
}
