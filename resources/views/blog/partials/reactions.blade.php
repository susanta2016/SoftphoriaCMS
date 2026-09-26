{{--
    Emoji reaction bar. Members toggle a reaction (one of each emoji per
    member) through a form per emoji — upgraded to fetch() by
    resources/js/app.js ([data-blog-reactions]). Guests see the counts and
    are sent to log in, then straight back to this post.
--}}
<div id="reactions" class="scroll-mt-28" data-blog-reactions aria-label="Reactions">
    <p class="mb-2 text-sm font-medium text-brand-navy/60">Was this helpful?</p>
    <div class="flex flex-wrap gap-2">
        @foreach (\App\Enums\BlogReaction::cases() as $reaction)
            @php
                $count = $reactionCounts[$reaction->value] ?? 0;
                $mine = in_array($reaction->value, $myReactions, true);
                $classes = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition';
            @endphp
            @auth
                <form method="POST" action="{{ route('blog.reactions.toggle', $post) }}">
                    @csrf
                    <input type="hidden" name="reaction" value="{{ $reaction->value }}">
                    <button type="submit" data-reaction="{{ $reaction->value }}" aria-pressed="{{ $mine ? 'true' : 'false' }}" title="{{ $reaction->getLabel() }}"
                        class="{{ $classes }} border-brand-navy/10 bg-white text-brand-navy/80 hover:-translate-y-0.5 hover:border-brand-accent/40 aria-pressed:border-brand-accent aria-pressed:bg-brand-sky aria-pressed:text-brand-accent">
                        <span class="text-base" aria-hidden="true">{{ $reaction->emoji() }}</span>
                        <span class="sr-only">{{ $reaction->getLabel() }}</span>
                        <span data-reaction-count class="min-w-3 font-semibold tabular-nums">{{ $count }}</span>
                    </button>
                </form>
            @else
                <a href="{{ route('blog.join', $post) }}" title="Log in to react with {{ $reaction->getLabel() }}"
                    class="{{ $classes }} border-brand-navy/10 bg-white text-brand-navy/80 hover:border-brand-accent/40">
                    <span class="text-base" aria-hidden="true">{{ $reaction->emoji() }}</span>
                    <span class="sr-only">{{ $reaction->getLabel() }}</span>
                    <span class="min-w-3 font-semibold tabular-nums">{{ $count }}</span>
                </a>
            @endauth
        @endforeach
    </div>
</div>
