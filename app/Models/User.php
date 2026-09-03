<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(UserDownload::class);
    }

    /**
     * ADMIN-001 authorization integration point: gates access to the
     * Filament admin panel using the existing roles schema (DB-002/003)
     * rather than a new concept. A user needs an active status and the
     * reserved Role::ADMIN_SLUG role. ADMIN-004 (App\Filament\Resources\Roles)
     * built the role/permission management UI on top of this same check —
     * it deliberately still gates on role slug, not on individual
     * permissions, so this method is unchanged by that work.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active'
            && $this->roles()->where('slug', 'admin')->exists();
    }
}
