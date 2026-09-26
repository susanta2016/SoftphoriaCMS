<?php

namespace App\Http\Controllers\Blog;

use App\Enums\BlogReaction;
use App\Http\Controllers\Controller;
use App\Jobs\LookupIpLocation;
use App\Models\BlogPost;
use App\Models\BlogReactionRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Toggles one of the member's emoji reactions on a post (each emoji at
 * most once per member). Answers fetch() with the post's fresh counts;
 * without JavaScript it redirects back to the reaction bar.
 */
class BlogReactionController extends Controller
{
    public function toggle(Request $request, BlogPost $post): JsonResponse|RedirectResponse
    {
        abort_unless($post->isLive(), 404);

        $reaction = BlogReaction::tryFrom((string) $request->input('reaction'));

        // Hand-built 422: bootstrap/app.php only renders JSON errors for api/*.
        if ($reaction === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unknown reaction.'], 422)
                : redirect()->to($post->url().'#reactions');
        }

        $userId = $request->user()->getKey();
        $existing = BlogReactionRecord::query()
            ->where(['blog_post_id' => $post->getKey(), 'user_id' => $userId, 'reaction' => $reaction->value])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            BlogReactionRecord::query()->firstOrCreate(
                ['blog_post_id' => $post->getKey(), 'user_id' => $userId, 'reaction' => $reaction->value],
                ['ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 512)],
            );

            // Admin-only IP location, resolved after the response is sent.
            LookupIpLocation::dispatchAfterResponse($request->ip());
        }

        if (! $request->expectsJson()) {
            return redirect()->to($post->url().'#reactions');
        }

        return response()->json([
            'counts' => $post->reactions()->toBase()->selectRaw('reaction, count(*) as total')->groupBy('reaction')->pluck('total', 'reaction')->map(fn ($n): int => (int) $n),
            'mine' => $post->reactions()->where('user_id', $userId)->pluck('reaction')->map(fn (BlogReaction $r): string => $r->value)->values(),
        ]);
    }
}
