<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stamps users.last_login_at after a sign-in has fully succeeded — called
 * from AuthenticateUserAction (public form, after its blocked-status and
 * admin checks) and App\Filament\Pages\Auth\Login (admin panel). Not a
 * listener on Illuminate\Auth\Events\Login: the public form fires that
 * event before it logs blocked/admin accounts straight back out, which
 * would record logins that never really happened.
 *
 * A plain query-builder write on purpose: it must not bump updated_at or
 * fire model events/audit entries — this is internal tracking, not a
 * profile change. A "remember me" cookie restoring a session is not a new
 * sign-in and is not recorded.
 */
class RecordLastLoginAction
{
    public function handle(User $user): void
    {
        $now = now();

        DB::table('users')->where('id', $user->getKey())->update(['last_login_at' => $now]);

        $user->setRawAttributes(['last_login_at' => $now->toDateTimeString()] + $user->getAttributes(), true);
    }
}
