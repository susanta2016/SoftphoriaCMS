<?php

namespace App\Models;

use App\Enums\BlogCommentReportReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's report (red flag) on a comment. Open until an admin
 * Verifies or Revokes the comment, which resolves every open report on it.
 */
#[Fillable(['blog_comment_id', 'user_id', 'reason', 'details'])]
class BlogCommentReport extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => BlogCommentReportReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(BlogComment::class, 'blog_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
