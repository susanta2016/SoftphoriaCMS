<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sender banned from the public Contact Us form. Each row bans one value
 * of one TYPE; a submission is blocked when ANY of its IP, email or name
 * matches a ban (isBanned()). Values are normalised on save so "Jane@X.com "
 * and "jane@x.com" are the same ban.
 */
#[Fillable(['type', 'value', 'reason', 'source_submission_id', 'banned_by'])]
class ContactBan extends Model
{
    public const TYPE_IP = 'ip';

    public const TYPE_EMAIL = 'email';

    public const TYPE_NAME = 'name';

    public const TYPES = [
        self::TYPE_IP => 'IP address',
        self::TYPE_EMAIL => 'Email',
        self::TYPE_NAME => 'Name',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContactBan $ban): void {
            $ban->value = self::normalize($ban->type, (string) $ban->value);
        });
    }

    public static function normalize(string $type, ?string $value): string
    {
        $value = trim((string) $value);

        return match ($type) {
            self::TYPE_EMAIL => mb_strtolower($value),
            self::TYPE_NAME => mb_strtolower((string) preg_replace('/\s+/u', ' ', $value)),
            default => mb_strtolower($value),
        };
    }

    public static function isBanned(?string $name, ?string $email, ?string $ip): bool
    {
        $candidates = array_filter([
            self::TYPE_IP => filled($ip) ? self::normalize(self::TYPE_IP, $ip) : null,
            self::TYPE_EMAIL => filled($email) ? self::normalize(self::TYPE_EMAIL, $email) : null,
            self::TYPE_NAME => filled($name) ? self::normalize(self::TYPE_NAME, $name) : null,
        ]);

        if ($candidates === []) {
            return false;
        }

        return self::query()
            ->where(function ($query) use ($candidates): void {
                foreach ($candidates as $type => $value) {
                    $query->orWhere(fn ($q) => $q->where('type', $type)->where('value', $value));
                }
            })
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
