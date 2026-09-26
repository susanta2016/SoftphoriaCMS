<?php

namespace App\Models;

use App\Enums\BlogCommentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's comment on a blog post. Publishes instantly; an admin can
 * Revoke it (hidden, kept) or Verify it after members report it —
 * Admin → Blog → Comments.
 */
#[Fillable(['blog_post_id', 'user_id', 'body', 'status', 'ip_address', 'user_agent'])]
class BlogComment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BlogCommentStatus::class,
            'reviewed_at' => 'datetime',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BlogCommentReport::class);
    }

    public function openReports(): HasMany
    {
        return $this->reports()->whereNull('resolved_at');
    }

    /**
     * Cached geolocation of the IP the comment was posted from (admin only).
     */
    public function ipLocation(): BelongsTo
    {
        return $this->belongsTo(IpLocation::class, 'ip_address', 'ip');
    }

    public function isVisible(): bool
    {
        return $this->status === BlogCommentStatus::Published;
    }
}
