<?php

namespace App\Modules\Music\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One completed whole-track listen (see the create_track_listens_table
 * migration's docblock). Written only by TrackListenController.
 */
#[Fillable(['user_id', 'track_id'])]
class TrackListen extends Model
{
    public const ?string UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
