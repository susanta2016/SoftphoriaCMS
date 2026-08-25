<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every `/account/*` route (on top of the standard `auth`
 * middleware). `auth` alone only proves the session belongs to *some* user
 * — it doesn't re-check whether that user is still allowed to be signed in
 * at all, e.g. an admin suspending/banning them mid-session. This is the
 * public-site equivalent of User::canAccessPanel()'s own status check,
 * applied the same way here rather than inventing a second convention.
 *
 * PendingVerification and Active both pass — login/the account area were
 * never gated on email verification (no existing precedent requires it,
 * see EmailVerificationController's own independence from Subscription
 * state), only genuinely locked-out statuses are rejected here.
 */
class EnsureAccountIsUsable
{
    private const array BLOCKED_STATUSES = [
        UserStatus::Suspended->value,
        UserStatus::Locked->value,
        UserStatus::Banned->value,
        UserStatus::Deleted->value,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $user */
        $user = Auth::guard('web')->user();

        if ($user !== null && in_array($user->status, self::BLOCKED_STATUSES, true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account is no longer able to sign in. Please contact support.');
        }

        // An admin reaching /account/* is only possible via their existing
        // /admin Filament session (the same `web` guard) — AuthenticateUserAction
        // already refuses admin credentials on the public login form itself.
        // Redirect rather than logout: this doesn't touch their legitimate
        // admin session, it just isn't the customer account area.
        if ($user !== null && $user->hasAdminRole()) {
            return redirect('/admin')
                ->with('status', 'Admin accounts use the admin panel, not the member account area.');
        }

        return $next($request);
    }
}
