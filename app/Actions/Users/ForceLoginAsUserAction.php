<?php

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Exceptions\Users\ForceLoginNotAllowedException;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "Force Login" (config('features.force_login_enabled')): replaces
 * the acting admin's authenticated browser session with one for $target.
 * Admins and members share the `web` guard, so this is a full switch, not
 * an impersonation layer — the admin session is destroyed and there is no
 * "switch back".
 *
 * Order matters:
 *  1. eligibility (never an admin, never a blocked account, never self);
 *  2. audit record written while still authenticated as the admin, so the
 *     actor is the real admin (AuditLogService also captures IP + user
 *     agent + timestamp) — never inferred from the switched identity;
 *  3. admin logged out, old session invalidated, CSRF token regenerated;
 *  4. target logged in (no "remember me"), then the session ID regenerated
 *     again (session-fixation defence).
 *
 * Deliberately does not touch the target's data: no password, email, role,
 * entitlement or last_login_at change, and no login side effects such as
 * claiming guest purchases (those belong to the member's own sign-in —
 * App\Actions\Auth\AuthenticateUserAction).
 */
class ForceLoginAsUserAction
{
    private const array BLOCKED_STATUSES = [
        UserStatus::Suspended->value,
        UserStatus::Locked->value,
        UserStatus::Banned->value,
        UserStatus::Deleted->value,
    ];

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Shared by the admin UI (whether to offer the action) and handle()
     * (the authoritative check).
     *
     * @throws ForceLoginNotAllowedException
     */
    public function ensureEligible(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw ForceLoginNotAllowedException::forSelf();
        }

        if ($target->hasAdminRole()) {
            throw ForceLoginNotAllowedException::forAdminAccount();
        }

        if (in_array($target->status, self::BLOCKED_STATUSES, true)) {
            throw ForceLoginNotAllowedException::forBlockedAccount();
        }
    }

    public function isEligible(User $target, User $actor): bool
    {
        try {
            $this->ensureEligible($target, $actor);
        } catch (ForceLoginNotAllowedException) {
            return false;
        }

        return true;
    }

    /**
     * @throws ForceLoginNotAllowedException
     */
    public function handle(User $target, User $actor, Request $request): void
    {
        $this->ensureEligible($target, $actor);

        $this->auditLog->record($actor, 'user.force_login', $target, [
            'target_email' => $target->email,
        ]);

        $guard = Auth::guard('web');

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $guard->login($target);
        $request->session()->regenerate();
    }
}
