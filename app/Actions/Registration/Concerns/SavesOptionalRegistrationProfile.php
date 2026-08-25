<?php

namespace App\Actions\Registration\Concerns;

use App\Models\User;

/**
 * Shared by Free/Pro registration — the frontend registration form's
 * profile fields (Phone Number, Biography, Address, Zip Code) mirror the
 * admin User form's "Profile" section (see UserForm/CreateUserAction) field
 * for field, so a self-registered account can carry the same profile data
 * an admin-created one can. Only Biography is actually optional on that
 * form (RegistrationController requires the other three); array_filter is
 * kept anyway so a blank Biography never creates a user_profiles row with
 * an empty string, same as CreateUserAction's behavior.
 */
trait SavesOptionalRegistrationProfile
{
    /**
     * @param  array{phone_number?: ?string, bio?: ?string, address?: ?string, zip_code?: ?string}  $data
     */
    protected function saveOptionalProfile(User $user, array $data): void
    {
        $profileData = array_filter([
            'bio' => $data['bio'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'address' => $data['address'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
        ], fn ($value) => filled($value));

        if ($profileData !== []) {
            $user->profile()->create($profileData);
        }
    }
}
