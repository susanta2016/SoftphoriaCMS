<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For public member actions outside the /account area (blog comments,
 * reports, reactions): logs out a suspended/locked/banned account, same as
 * EnsureAccountIsUsable, but — unlike it — lets admins through, so a
 * post's author can join their own post's discussion.
 */
class EnsureAccountNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user !== null && in_array($user->status, EnsureAccountIsUsable::BLOCKED_STATUSES, true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Your account is no longer able to sign in.'], 403)
                : redirect()->route('login')->with('status', 'Your account is no longer able to sign in. Please contact support.');
        }

        return $next($request);
    }
}
