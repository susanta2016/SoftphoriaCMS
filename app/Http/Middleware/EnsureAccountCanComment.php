<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The verified-account gate for blog commenting (posting AND deleting a
 * comment): the user must be Active AND have a verified email address.
 * This is the dedicated check EnsureAccountIsUsable's docblock asks for —
 * `auth`, EnsureAccountIsUsable and EnsureAccountNotBlocked only reject
 * locked-out statuses and deliberately let PendingVerification through.
 *
 * Both conditions are required because they can disagree: an account
 * created in the admin can be Active without ever verifying its email.
 * There is no admin bypass. Unlike the blocked-status middlewares this
 * never logs anyone out — an unverified member can still read and react;
 * they just can't comment until they verify.
 *
 * Runs after `auth` + EnsureAccountNotBlocked (see routes/web.php).
 */
class EnsureAccountCanComment
{
    public const string MESSAGE = 'Verify your email to comment.';

    public static function allows(?User $user): bool
    {
        return $user !== null
            && $user->status === UserStatus::Active->value
            && $user->email_verified_at !== null;
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $user */
        $user = Auth::guard('web')->user();

        if (! self::allows($user)) {
            return $request->expectsJson()
                ? response()->json(['message' => self::MESSAGE], 403)
                : redirect()->back()->withErrors(['body' => self::MESSAGE], 'comment');
        }

        return $next($request);
    }
}
