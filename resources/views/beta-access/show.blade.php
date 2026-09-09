<x-layouts.site :seo="$seo">
    <div class="flex min-h-screen w-full flex-col items-center justify-center bg-brand-ivory px-4 py-16">
        <div class="w-full max-w-md">
            <div class="text-center">
                <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">{{ $siteName }}</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <h2 class="font-serif text-xl text-brand-navy sm:text-2xl">Beta Access</h2>
                <p class="mt-3 text-sm text-brand-navy/70">
                    This website is currently in private beta.<br>
                    Please enter the password provided to you to continue.
                </p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('beta.attempt') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                @csrf

                {{--
                    Honeypot spam trap — visually hidden from real visitors
                    (off-screen, never display:none/visibility:hidden) and
                    excluded from tab order, same pattern as every other
                    public form in this project (see ContactController).
                --}}
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="hp_website">Website</label>
                    <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                </div>

                <label for="password" class="block text-sm font-medium text-brand-navy">Password *</label>
                <input
                    type="password" id="password" name="password" required autofocus autocomplete="off"
                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                >

                <div class="mt-8">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                    >
                        Enter Website
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.site>
