<?php

namespace App\Http\Controllers\Blog;

use App\Enums\BlogCommentReportReason;
use App\Enums\BlogCommentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\LookupIpLocation;
use App\Models\BlogComment;
use App\Models\BlogCommentReport;
use App\Models\BlogPost;
use App\Shared\Support\Spam\FormTimeTrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Member comments on blog posts: post (publishes instantly), delete your
 * own, and report someone else's (the red flag reviewed in Admin → Blog →
 * Comments). Routes require an authenticated, usable account plus the
 * matching feature switch; posting also carries the site's standard
 * honeypot + time trap and is throttled.
 */
class BlogCommentController extends Controller
{
    public function store(Request $request, BlogPost $post, FormTimeTrap $timeTrap): RedirectResponse
    {
        abort_unless($post->isLive() && $post->allow_comments, 404);

        $target = $post->url().'#comments';

        // Honeypot / time trap — discard silently, same as the Contact form.
        if (filled($request->input('hp_website'))
            || ! $timeTrap->passes($request->input(FormTimeTrap::FIELD), FormTimeTrap::SUBMIT_MIN_SECONDS)) {
            Log::info('Blog comment discarded as spam', ['user_id' => $request->user()->getKey(), 'ip' => $request->ip()]);

            return redirect()->to($target);
        }

        $data = $request->validateWithBag('comment', [
            'body' => ['required', 'string', 'min:2', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && preg_match_all('~https?://|www\.~i', $value) > 2) {
                    $fail('Please include no more than 2 links in a comment.');
                }
            }],
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->getKey(),
            'body' => trim($data['body']),
            'status' => BlogCommentStatus::Published,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        // Admin-only IP location, resolved after the response is sent.
        LookupIpLocation::dispatchAfterResponse($request->ip());

        return redirect()->to($post->url().'#comment-'.$comment->getKey())
            ->with('comment_status', 'Thanks — your comment is live.');
    }

    public function destroy(Request $request, BlogComment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->getKey(), 403);

        $url = $comment->post->url().'#comments';
        $comment->delete();

        return redirect()->to($url)->with('comment_status', 'Your comment was deleted.');
    }

    public function report(Request $request, BlogComment $comment): RedirectResponse|JsonResponse
    {
        abort_unless($comment->isVisible() && $comment->post?->isLive(), 404);
        if ($comment->user_id === $request->user()->getKey()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'You cannot report your own comment.'], 422)
                : redirect()->to($comment->post->url().'#comment-'.$comment->getKey());
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', Rule::enum(BlogCommentReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        // Built by hand for fetch() callers: bootstrap/app.php only renders
        // JSON errors for api/* (same as ContactController::store()).
        if ($validator->fails()) {
            return $request->expectsJson()
                ? response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422)
                : redirect()->to($comment->post->url().'#comment-'.$comment->getKey())->withErrors($validator);
        }

        $data = $validator->validated();

        // One report per member per comment; reporting again just updates it.
        BlogCommentReport::query()->updateOrCreate(
            ['blog_comment_id' => $comment->getKey(), 'user_id' => $request->user()->getKey()],
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null, 'resolved_at' => null],
        );

        $message = 'Thanks — our team will review this comment.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->to($comment->post->url().'#comment-'.$comment->getKey())->with('comment_status', $message);
    }
}
