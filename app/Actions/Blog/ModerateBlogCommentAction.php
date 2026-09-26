<?php

namespace App\Actions\Blog;

use App\Enums\BlogCommentStatus;
use App\Models\BlogComment;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

/**
 * Admin moderation of a member's blog comment (Blog → Comments):
 *
 * - verify(): the admin reviewed the reports and the comment is fine — it
 *   stays published and every open report on it is resolved.
 * - revoke(): the comment is hidden from the site (kept for the record)
 *   and every open report on it is resolved.
 * - restore(): a revoked comment is published again.
 *
 * Every decision is written to the audit log.
 */
class ModerateBlogCommentAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function verify(BlogComment $comment, User $admin): void
    {
        $this->decide($comment, $admin, BlogCommentStatus::Published, 'blog_comment.verified');
    }

    public function revoke(BlogComment $comment, User $admin): void
    {
        $this->decide($comment, $admin, BlogCommentStatus::Revoked, 'blog_comment.revoked');
    }

    public function restore(BlogComment $comment, User $admin): void
    {
        $this->decide($comment, $admin, BlogCommentStatus::Published, 'blog_comment.restored');
    }

    private function decide(BlogComment $comment, User $admin, BlogCommentStatus $status, string $event): void
    {
        DB::transaction(function () use ($comment, $admin, $status, $event): void {
            $resolved = $comment->openReports()->update(['resolved_at' => now()]);

            $comment->forceFill([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ])->save();

            $this->audit->record($admin, $event, $comment, [
                'blog_post_id' => $comment->blog_post_id,
                'reports_resolved' => $resolved,
            ]);
        });
    }
}
