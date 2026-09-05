@php
    use Illuminate\Support\Facades\Storage;

    $bannerUrl = $heroBanner ? Storage::disk($heroBanner->disk)->url($heroBanner->path) : null;
@endphp

<x-layouts.site :seo="$seo">
    {{--
        Client-supplied Gratitude jar photograph (client-confirmed,
        2026-09-05, corrected same day after two rounds of client visual
        feedback). The text column below uses the EXACT SAME container
        classes as Podcast's and Poetry-Prose's own hero
        (`mx-auto max-w-7xl px-4 pt-32 pb-24 sm:px-6 lg:px-8 lg:pt-40
        lg:pb-32` wrapping a `max-w-xl` inner block — see
        podcast/index.blade.php) — this is also the exact container the
        site header itself uses (resources/views/components/site/
        header.blade.php), which is why reusing it verbatim, rather than
        re-deriving similar-looking padding, is what actually guarantees
        the hero title lines up with the header's logo above it.

        The second attempt at this hero broke that alignment: it made the
        text column a `flex-1` sibling of an image panel, so the column
        was only ~62% of the viewport width, not the full width — nesting
        `mx-auto max-w-7xl` inside an already-narrower-than-1280px box has
        no effect (nothing to center against), so the text fell back to
        sitting flush against ITS OWN box's left edge instead of the
        header's actual grid column, a very visible ~120px+ misalignment
        confirmed by comparing rendered coordinates directly. The fix
        below keeps the SAME `mx-auto max-w-7xl` text container Podcast
        uses completely unchanged and un-nested inside anything narrower,
        and instead makes the image `absolute`-positioned against this
        outer hero div (which spans the full viewport, exactly like
        Podcast/Poetry-Prose's own outer hero div) — so the image never
        participates in the text column's box model or width calculation
        at all, and can't misalign it a second time.

        Image: `sm:absolute sm:inset-y-0 sm:right-0 sm:w-[38%]`, `bg-cover
        bg-center` (identical property values to Podcast's own hero) with
        a gradient laid directly on the photo (not before it) — a
        gradient over empty space can't blend into pixels that aren't
        there, which is what made the second attempt's transition still
        read as a hard seam despite a soft opacity curve; overlaying it on
        the real image is what makes the fade genuinely soft. `min-h-*` on
        the outer div gives the absolutely-positioned image a height to
        fill regardless of how tall the text happens to be.

        Mobile (below sm:): the image reverts to a normal, in-flow,
        full-width band (`h-64 w-full`, no `absolute`) stacked above the
        text, rather than squeezing a ~38%-wide sliver into a narrow
        viewport — there's no pre-existing stacked-hero pattern elsewhere
        on the site to reuse, so this part is page-local, not a new
        site-wide hero variant.
    --}}
    <div @class(['relative overflow-hidden min-h-[26rem] sm:min-h-[30rem] lg:min-h-[34rem]', 'bg-brand-ivory' => ! $bannerUrl])>
        <x-site.header :transparent="(bool) $bannerUrl" :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

        @if ($bannerUrl)
            <div class="relative h-64 w-full sm:absolute sm:inset-y-0 sm:right-0 sm:h-auto sm:w-[38%]">
                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $bannerUrl }}');"></div>

                {{-- The gradient seam — laid directly over the photo
                    itself (not over empty space), which is what makes the
                    transition read as intentional rather than a hard
                    panel boundary. Horizontal fade for the side-by-side
                    layout (sm+); vertical fade for the stacked mobile
                    layout. No filter/tint touches the photo outside this
                    translucent overlay. --}}
                <div class="absolute inset-0 bg-gradient-to-b from-brand-ivory via-brand-ivory/40 to-transparent sm:bg-gradient-to-r sm:from-brand-ivory sm:via-brand-ivory/35 sm:to-transparent" aria-hidden="true"></div>
            </div>
        @endif

        <div class="relative mx-auto max-w-7xl px-4 pt-24 pb-14 sm:px-6 sm:pt-32 sm:pb-24 lg:px-8 lg:pt-40 lg:pb-32">
            <div class="max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Inspirational Resources</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">Gratitude Journal</h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">
                    Gratitude shared within our member community.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white py-14 sm:py-16">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if ($entries->isEmpty())
                <p class="mt-10 text-center text-sm text-brand-navy/60">No Gratitude Journal entries yet — check back soon.</p>
            @else
                <p class="text-xs font-semibold tracking-[0.15em] text-brand-gold uppercase">
                    {{ $entries->total() }} {{ $entries->total() === 1 ? 'Reflection' : 'Reflections' }} Shared Within Our Member Community
                </p>

                <ul class="mt-6 divide-y divide-brand-navy/10 border-y border-brand-navy/10">
                    @foreach ($entries as $entry)
                        <li class="py-8 sm:py-9">
                            <p class="max-w-3xl font-serif text-xl leading-relaxed text-brand-navy sm:text-2xl">{{ $entry->content }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <p class="text-xs text-brand-navy/50">
                                    {{ $entry->user?->name ?? 'A member' }} · <span class="tabular-nums">{{ $entry->created_at?->format('M j, Y') }}</span>
                                </p>

                                {{-- The 🙌 reaction — same generic
                                    data-reaction-* markup/JS as Music/Podcast/
                                    Poetry-Prose. Toggled asynchronously via
                                    resources/js/app.js; the real POST submit
                                    here is the no-JS fallback. --}}
                                @if (config('features.gratitude_journal_reactions_enabled'))
                                    @auth
                                        <form method="POST" action="{{ route('inspirational-resources.gratitude-journal.reactions.toggle', $entry) }}" data-reaction-form>
                                            @csrf
                                            <button
                                                type="submit"
                                                data-reaction-button
                                                aria-pressed="{{ $entry->userReacted ? 'true' : 'false' }}"
                                                @class([
                                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                                    'border-brand-gold bg-brand-gold/10 text-brand-navy' => $entry->userReacted,
                                                    'border-brand-navy/20 text-brand-navy/70 hover:border-brand-gold' => ! $entry->userReacted,
                                                ])
                                            >
                                                <span aria-hidden="true">🙌</span> <span data-reaction-count>{{ $entry->reactions_count }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-full border border-brand-navy/20 px-3 py-1.5 text-sm font-medium text-brand-navy/70 transition hover:border-brand-gold" title="Log in to react">
                                            <span aria-hidden="true">🙌</span> {{ $entry->reactions_count }}
                                        </a>
                                    @endauth
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($entries->hasPages())
                    <div class="mt-10 border-t border-brand-navy/10 pt-8">
                        {{ $entries->onEachSide(1)->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
