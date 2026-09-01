<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Commerce\Models\DownloadLog;
use App\Modules\Commerce\Models\Entitlement;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Subscription;
use App\Modules\Music\Models\Album;
use App\Modules\Music\Models\Single;
use App\Modules\Music\Models\Track;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * The uploaded profile->avatar (Media) when set, otherwise the same
     * self-contained placeholder used by Filament's UsersTable — a public
     * frontend page (e.g. a review/rating card) never invents its own
     * "dummy photo" or reaches for an external avatar service.
     */
    public function avatarUrl(): string
    {
        $avatar = $this->profile?->avatar;

        return $avatar ? Storage::disk($avatar->disk)->url($avatar->path) : static::defaultAvatarUrl();
    }

    /**
     * A self-contained inline SVG (no external service, no stored asset).
     * Public so tests can assert against the exact fallback string rather
     * than the rendered HTML.
     */
    public static function defaultAvatarUrl(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<circle cx="20" cy="20" r="20" fill="#e5e7eb"/>'
            .'<circle cx="20" cy="16" r="7" fill="#9ca3af"/>'
            .'<path d="M6 34c0-8 6-13 14-13s14 5 14 13" fill="#9ca3af"/>'
            .'</svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function lightPosts(): HasMany
    {
        return $this->hasMany(LightPost::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(DownloadLog::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Does this user already own this Album/Single outright (a real
     * purchase), independent of whether they're also an active Pro Member —
     * see hasActiveMembership() for the separate, catalogue-wide check.
     */
    public function ownsRelease(Album|Single $release): bool
    {
        $column = $release instanceof Album ? 'album_id' : 'single_id';

        return $this->entitlements()
            ->where($column, $release->getKey())
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Does this user already own this specific Track outright — via a
     * direct Track purchase, an Album purchase covering it, or a Single
     * purchase covering it (whichever applies; see Entitlement::coversTrack()).
     * Never true merely because the user owns some other track under the
     * same Album — only used for the Track's own individual purchase button,
     * which CartController never offers an Album-owner a second purchase of.
     */
    public function ownsTrack(Track $track): bool
    {
        return $this->entitlements()
            ->where(fn ($query) => $query
                ->where('track_id', $track->getKey())
                ->when($track->album_id !== null, fn ($q) => $q->orWhere('album_id', $track->album_id))
                ->when($track->single_id !== null, fn ($q) => $q->orWhere('single_id', $track->single_id)))
            ->whereNull('revoked_at')
            ->exists();
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * "Is this user currently an active Pro Member" — see
     * App\Modules\Commerce\Models\Subscription::isActive() for the rule.
     * Pure DB read, never a live Stripe call.
     */
    public function hasActiveMembership(): bool
    {
        return $this->subscription?->isActive() ?? false;
    }

    /**
     * ADMIN-001 authorization integration point: gates access to the
     * Filament admin panel using the existing roles schema (DB-002/003)
     * rather than a new concept. A user needs an active status and the
     * reserved "admin" role slug. Full role/permission management UI is
     * built in ADMIN-004; this only wires the existing tables to Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active' && $this->hasAdminRole();
    }

    /**
     * Also used outside the panel gate: the public /login form
     * (AuthenticateUserAction) and the account-area middleware
     * (EnsureAccountIsUsable) both need to tell an admin account apart from
     * a regular member — admins sign in only at /admin/login and never use
     * the customer account area, regardless of which guard session they
     * hold.
     */
    public function hasAdminRole(): bool
    {
        return $this->roles()->where('slug', 'admin')->exists();
    }
}
