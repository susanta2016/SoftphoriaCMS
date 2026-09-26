{{--
    Discussion: members-only comments that publish instantly. Each comment
    can be deleted by its author and reported (red flag) by other members
    when Comment Reporting is on — reports land in Admin → Blog → Comments.
    The comment form carries the site's standard honeypot + time trap.
--}}
@php $initials = $initials ?? fn (?string $name): string => mb_strtoupper(mb_substr($name ?? '?', 0, 1)); @endphp

<section id="discussion" class="mt-16 scroll-mt-28" aria-labelledby="comments-heading">
    <h2 id="comments-heading" class="flex items-center gap-3 text-2xl font-bold text-brand-navy">
        Discussion
        <span class="rounded-full bg-brand-sky px-2.5 py-0.5 text-sm font-semibold text-brand-accent">{{ $comments->count() }}</span>
    </h2>
    <span id="comments" class="block scroll-mt-28"></span>

    @if (session('comment_status'))
        <p role="status" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('comment_status') }}</p>
    @endif

    @auth
        <form method="POST" action="{{ route('blog.comments.store', $post) }}" class="mt-6 rounded-2xl border border-brand-navy/10 bg-white p-5 shadow-sm" data-comment-form>
            @csrf
            <input type="hidden" name="{{ \App\Shared\Support\Spam\FormTimeTrap::FIELD }}" value="{{ app(\App\Shared\Support\Spam\FormTimeTrap::class)->issue() }}">
            <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
                <label for="comment-hp_website">Website</label>
                <input type="text" id="comment-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
            </div>

            <div class="flex gap-4">
                <span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-accent text-sm font-bold text-white sm:flex" aria-hidden="true">{{ $initials(auth()->user()->name) }}</span>
                <div class="min-w-0 flex-1">
                    <label for="comment-body" class="sr-only">Your comment</label>
                    <textarea id="comment-body" name="body" rows="3" required minlength="2" maxlength="2000" placeholder="Share your thoughts, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}…"
                        @if ($errors->comment->has('body')) aria-invalid="true" aria-describedby="comment-body-error" @endif
                        class="block w-full resize-y rounded-xl border border-brand-navy/10 bg-brand-mist px-4 py-3 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:border-brand-accent focus:bg-white focus:ring-4 focus:ring-brand-accent/15 focus:outline-none aria-[invalid=true]:border-red-400">{{ old('body') }}</textarea>
                    @if ($errors->comment->has('body'))
                        <p id="comment-body-error" class="mt-1.5 text-xs text-red-600">{{ $errors->comment->first('body') }}</p>
                    @endif
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <p class="text-xs text-brand-navy/45">Comments are public. Please follow our <a href="/terms-of-service" class="underline hover:text-brand-accent">community rules</a>. <span data-comment-count>0</span>/2000</p>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-accent-dark disabled:opacity-60">Post comment</button>
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="mt-6 flex flex-col items-start gap-4 rounded-2xl border border-brand-accent/15 bg-brand-sky/60 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold text-brand-navy">Join the conversation</p>
                <p class="mt-1 text-sm text-brand-navy/65">Log in or create a free account to comment and react.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('blog.join', $post) }}" class="rounded-xl bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-accent-dark">Log in</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="rounded-xl border border-brand-navy/15 bg-white px-5 py-2.5 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent">Sign up</a>
                @endif
            </div>
        </div>
    @endauth

    @if ($comments->isEmpty())
        <p class="mt-8 text-sm text-brand-navy/55">No comments yet — be the first to share your thoughts.</p>
    @else
        <ol class="mt-8 space-y-4">
            @foreach ($comments as $comment)
                @php $isMine = auth()->id() === $comment->user_id; @endphp
                <li id="comment-{{ $comment->id }}" class="scroll-mt-28 rounded-2xl border border-brand-navy/8 bg-white p-5 target:ring-2 target:ring-brand-accent/40">
                    <div class="flex gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-sky text-sm font-bold text-brand-accent" aria-hidden="true">{{ $initials($comment->user?->name) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <p class="text-sm">
                                    <span class="font-semibold text-brand-navy">{{ $comment->user?->name ?? 'Member' }}</span>
                                    @if ($post->author_id === $comment->user_id)
                                        <span class="ml-1 rounded-full bg-brand-accent/10 px-2 py-0.5 text-xs font-semibold text-brand-accent">Author</span>
                                    @endif
                                    <span class="text-brand-navy/40">·</span>
                                    <time class="text-brand-navy/50" datetime="{{ $comment->created_at->toAtomString() }}" title="{{ $comment->created_at->format('M j, Y H:i') }}">{{ $comment->created_at->diffForHumans() }}</time>
                                </p>

                                <div class="flex items-center gap-1">
                                    @if ($isMine)
                                        <form method="POST" action="{{ route('blog.comments.destroy', $comment) }}" data-confirm="Delete your comment?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg px-2 py-1 text-xs font-medium text-brand-navy/50 transition hover:bg-red-50 hover:text-red-600">Delete</button>
                                        </form>
                                    @elseif ($reportsOn)
                                        @auth
                                            <details class="relative" data-report-menu>
                                                <summary class="flex cursor-pointer list-none items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand-navy/45 transition hover:bg-red-50 hover:text-red-600 [&::-webkit-details-marker]:hidden" title="Report this comment">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M5 2a1 1 0 011 1v.3C7.7 2.6 9.6 2.4 11.7 3.4c1.9.9 3.7.8 5.8-.1A1 1 0 0119 4.2v9a1 1 0 01-.6.9c-2.6 1.1-5 1.3-7.5.1-1.6-.7-3.2-.6-4.9.2V21a1 1 0 11-2 0V3a1 1 0 011-1z"/></svg>
                                                    Report
                                                </summary>
                                                <form method="POST" action="{{ route('blog.comments.report', $comment) }}" data-report-form
                                                    class="absolute right-0 z-20 mt-2 w-72 rounded-2xl border border-brand-navy/10 bg-white p-4 text-left shadow-2xl shadow-brand-navy/15">
                                                    @csrf
                                                    <p class="text-sm font-semibold text-brand-navy">Report this comment</p>
                                                    <fieldset class="mt-3 space-y-1.5">
                                                        <legend class="sr-only">Reason</legend>
                                                        @foreach (\App\Enums\BlogCommentReportReason::cases() as $reason)
                                                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-brand-navy/80 hover:bg-brand-mist">
                                                                <input type="radio" name="reason" value="{{ $reason->value }}" required class="text-red-600 focus:ring-red-500">
                                                                {{ $reason->getLabel() }}
                                                            </label>
                                                        @endforeach
                                                    </fieldset>
                                                    <label for="report-details-{{ $comment->id }}" class="sr-only">Details (optional)</label>
                                                    <textarea id="report-details-{{ $comment->id }}" name="details" rows="2" maxlength="500" placeholder="Details (optional)"
                                                        class="mt-2 block w-full resize-none rounded-lg border border-brand-navy/10 bg-brand-mist px-3 py-2 text-sm focus:border-red-400 focus:ring-2 focus:ring-red-500/15 focus:outline-none"></textarea>
                                                    <button type="submit" class="mt-3 w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">Send report</button>
                                                </form>
                                            </details>
                                        @endauth
                                    @endif
                                </div>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed break-words whitespace-pre-line text-brand-navy/80">{{ $comment->body }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
