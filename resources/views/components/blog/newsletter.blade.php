{{--
    Inline newsletter signup for blog pages — posts to the same
    route('newsletter.subscribe') as the footer form (honeypot included).
    Only rendered when the Newsletter Signup feature and Blog Settings'
    newsletter box are both on.
--}}
@props(['compact' => false])

<section {{ $attributes->class(['relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark text-white', 'p-6 sm:p-8' => $compact, 'p-8 sm:p-12' => ! $compact]) }} aria-labelledby="blog-newsletter-heading">
    <div class="pointer-events-none absolute -top-16 -right-16 -z-10 h-56 w-56 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
    <div @class(['grid items-center gap-6', 'lg:grid-cols-2 lg:gap-12' => ! $compact])>
        <div>
            <p class="text-xs font-semibold tracking-[0.18em] text-white/70 uppercase">Newsletter</p>
            <h2 id="blog-newsletter-heading" @class(['mt-2 font-bold', 'text-xl' => $compact, 'text-2xl sm:text-3xl' => ! $compact])>Get new articles in your inbox</h2>
            <p class="mt-2 text-sm leading-relaxed text-white/75">Practical engineering notes, no fluff. Unsubscribe anytime.</p>
        </div>
        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="w-full">
            @csrf
            <div style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden" aria-hidden="true">
                <label for="blog-nl-hp_website">Website</label>
                <input type="text" id="blog-nl-hp_website" name="hp_website" tabindex="-1" autocomplete="off">
            </div>
            <label for="blog-newsletter-email" class="sr-only">Email address</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input id="blog-newsletter-email" name="email" type="email" required maxlength="255" autocomplete="email" placeholder="you@company.com"
                    class="min-w-0 flex-1 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-white/50 backdrop-blur focus:border-white/60 focus:bg-white/15 focus:ring-4 focus:ring-white/15 focus:outline-none">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-brand-navy transition hover:bg-brand-sky">
                    Subscribe
                    <x-site.arrow class="h-4 w-4"/>
                </button>
            </div>
        </form>
    </div>
</section>
