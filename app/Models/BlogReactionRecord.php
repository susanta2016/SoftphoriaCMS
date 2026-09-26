<?php

namespace App\Models;

use App\Enums\BlogReaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's emoji reaction on a post (a row in blog_reactions). Named
 * "Record" so it doesn't clash with the App\Enums\BlogReaction enum of
 * allowed reactions.
 */
#[Fillable(['blog_post_id', 'user_id', 'reaction', 'ip_address', 'user_agent'])]
class BlogReactionRecord extends Model
{
    protected $table = 'blog_reactions';

    protected function casts(): array
    {
        return [
            'reaction' => BlogReaction::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cached geolocation of the IP the reaction came from (admin only).
     */
    public function ipLocation(): BelongsTo
    {
        return $this->belongsTo(IpLocation::class, 'ip_address', 'ip');
    }
}
