<?php

namespace App\Filament\Pages\Auth;

use App\Actions\Auth\RecordLastLoginAction;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;

/**
 * Filament's own admin login, unchanged except that a completed sign-in
 * (response returned and the panel guard now authenticated — i.e. not a
 * rate-limit, MFA challenge or failure) records users.last_login_at.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        $user = Filament::auth()->user();

        if ($response !== null && $user instanceof User) {
            app(RecordLastLoginAction::class)->handle($user);
        }

        return $response;
    }
}
