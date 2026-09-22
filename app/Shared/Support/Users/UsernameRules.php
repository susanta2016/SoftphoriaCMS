<?php

namespace App\Shared\Support\Users;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * The single source of truth for Username's format — required at
 * registration (App\Http\Controllers\RegistrationController), optional when
 * an existing member sets/changes it later (App\Http\Controllers\Account\
 * ProfileController) — both read the exact same rules from here rather than
 * keeping two copies in sync.
 *
 * Letters, numbers, underscore only, 3-20 characters (client-confirmed
 * 2026-09-22). Uniqueness is a plain closure doing its own `LOWER(username)
 * = LOWER(?)` comparison rather than Rule::unique('users', 'username')
 * relying on the connection's collation for case-insensitivity — that would
 * only actually be case-insensitive on MySQL/MariaDB's default
 * utf8mb4_unicode_ci collation, not on SQLite (case-sensitive by default,
 * and what the test suite's connection uses — phpunit.xml), so the rule
 * would silently behave differently than production ever tested it. This
 * closure gives the client-confirmed "Jacob"/"jacob" collision the same way
 * on every driver.
 */
class UsernameRules
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 20;

    /**
     * $enforceUniqueness is false only for RegistrationController::
     * registerPro() — same reasoning as that method's own `email` rule
     * omitting `unique:users,email`: an abandoned Pro registration retrying
     * with its own already-saved email/username is a legitimate retry, not
     * a conflict, and there is no user id yet at validation time (the
     * existing-row lookup happens inside RegisterProUserAction) to ignore
     * here. RegisterProUserAction checks uniqueness itself (also
     * case-insensitively) before actually creating a brand-new user, so a
     * real conflict is still caught — just one step later, as a proper
     * ValidationException instead of this rule.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $required, ?int $ignoreUserId = null, bool $enforceUniqueness = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:'.self::MIN_LENGTH,
            'max:'.self::MAX_LENGTH,
            'regex:/^[A-Za-z0-9_]+$/',
            ...($enforceUniqueness ? [self::uniquenessRule($ignoreUserId)] : []),
        ];
    }

    /**
     * Also used directly by RegisterProUserAction's own pre-save check
     * (same query, so "does this username collide" is answered identically
     * whether it's this rule or that action asking).
     */
    public static function isTaken(string $username, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->whereRaw('LOWER(username) = ?', [Str::lower($username)])
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }

    private static function uniquenessRule(?int $ignoreUserId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
            if (is_string($value) && self::isTaken($value, $ignoreUserId)) {
                $fail('This username has already been taken.');
            }
        };
    }
}
