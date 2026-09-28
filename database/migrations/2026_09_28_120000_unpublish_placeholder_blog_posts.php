<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BLOG-003 — content cleanup only (no schema change):
 *
 * - The four placeholder posts created while the Blog module was built are
 *   set back to Draft (not deleted), so they leave /blog, the homepage
 *   Latest Insights, the RSS feed and the sitemap, 404 publicly, and stay in
 *   Admin → Blog → Posts to be rewritten later. Matched on exact slug AND
 *   title, so a post an admin has since retitled is left alone.
 * - The known test interactions are deleted: the two seeded demo members'
 *   (@example.com) comments and reactions on the AWS guide, and the two
 *   test reactions left on the Django post on 2026-09-26. Comment reports
 *   on those comments go with them (cascade). Nothing else is touched.
 *
 * Plain query-builder code on purpose, so later model changes can't break it.
 */
return new class extends Migration
{
    /** slug => exact title */
    private const PLACEHOLDER_POSTS = [
        'aws-migration-guide' => 'A Practical Guide to Migrating Applications to AWS',
        'django-vs-flask' => 'Django vs Flask: Which is Right for Your Project?',
        'scalable-apis-with-laravel' => 'Building Scalable APIs with Laravel',
        'lessons-from-client-projects' => 'Lessons from 20 Years of Client Projects',
    ];

    private const DEMO_MEMBERS = ['demo.member@example.com', 'demo.member2@example.com'];

    private const DEMO_COMMENTS = [
        'Really useful breakdown — the point about DNS TTLs bit us last year. Would love a follow-up on cost monitoring.',
        'Check out my site for cheap hosting!!! best deals',
    ];

    public function up(): void
    {
        foreach (self::PLACEHOLDER_POSTS as $slug => $title) {
            DB::table('blog_posts')
                ->where('slug', $slug)
                ->where('title', $title)
                ->where('status', 'published')
                ->update(['status' => 'draft', 'updated_at' => now()]);
        }

        $postId = fn (string $slug): ?int => DB::table('blog_posts')->where('slug', $slug)->where('title', self::PLACEHOLDER_POSTS[$slug])->value('id');
        $demoUserIds = DB::table('users')->whereIn('email', self::DEMO_MEMBERS)->pluck('id');
        $awsId = $postId('aws-migration-guide');
        $djangoId = $postId('django-vs-flask');

        if ($awsId !== null && $demoUserIds->isNotEmpty()) {
            DB::table('blog_comments')
                ->where('blog_post_id', $awsId)
                ->whereIn('user_id', $demoUserIds)
                ->whereIn('body', self::DEMO_COMMENTS)
                ->delete();

            DB::table('blog_reactions')
                ->where('blog_post_id', $awsId)
                ->whereIn('user_id', $demoUserIds)
                ->delete();
        }

        if ($djangoId !== null) {
            DB::table('blog_reactions')
                ->where('blog_post_id', $djangoId)
                ->whereIn('reaction', ['love', 'fire'])
                ->whereDate('created_at', '2026-09-26')
                ->delete();
        }
    }

    public function down(): void
    {
        // Deleted test interactions are not restored, and the placeholder
        // posts are deliberately not re-published.
    }
};
