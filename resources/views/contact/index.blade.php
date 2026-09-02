<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory pt-28 pb-16 sm:pt-32">
        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Contact Us'],
            ]"/>

            <div class="mt-4 max-w-xl">
                <span class="text-xs font-semibold tracking-[0.2em] text-brand-gold uppercase">Get In Touch</span>
                <h1 class="mt-3 font-serif text-4xl leading-tight text-brand-navy sm:text-5xl">We'd love to hear from you.</h1>
                <div class="my-6 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px w-16 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                </div>
                <p class="max-w-xl text-base leading-relaxed text-brand-navy/75">
                    Questions, feedback, or just want to say hello? Send us a message and our team will get back to you soon.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <div class="space-y-6 lg:sticky lg:top-28">
                        <div class="rounded-2xl bg-brand-ivory p-6 ring-1 ring-brand-navy/5">
                            <h2 class="font-serif text-lg text-brand-navy">Contact Information</h2>

                            @if ($contactEmail)
                                <div class="mt-5 flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-brand-gold text-brand-gold">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Email</p>
                                        <a href="mailto:{{ $contactEmail }}" class="mt-0.5 block text-sm break-all text-brand-navy transition hover:text-brand-gold">{{ $contactEmail }}</a>
                                    </div>
                                </div>
                            @endif

                            @if ($contactAddress)
                                <div class="mt-5 flex items-start gap-3">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-brand-gold text-brand-gold">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21Z" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.3"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold tracking-wide text-brand-navy/50 uppercase">Address</p>
                                        <p class="mt-0.5 text-sm leading-relaxed whitespace-pre-line text-brand-navy">{{ $contactAddress }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.submit') }}" class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                        @csrf

                        {{--
                            Honeypot spam trap — visually hidden from real
                            visitors (off-screen, never via display:none/
                            visibility:hidden, which some bots detect and
                            skip) and excluded from tab order. A bot that
                            fills every input trips it; ContactController::
                            store() silently discards a non-empty submission
                            here rather than saving/emailing anything.
                        --}}
                        <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                            <label for="hp_website">Website</label>
                            <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-brand-navy">Name *</label>
                                <input
                                    type="text" id="name" name="name" value="{{ old('name') }}" required
                                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                >
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-brand-navy">Email Address *</label>
                                <input
                                    type="email" id="email" name="email" value="{{ old('email') }}" required
                                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                >
                            </div>

                            <div class="sm:col-span-2">
                                <label for="phone" class="block text-sm font-medium text-brand-navy">Phone Number</label>
                                <input
                                    type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                >
                            </div>

                            <div class="sm:col-span-2">
                                <label for="message" class="block text-sm font-medium text-brand-navy">Message *</label>
                                <textarea
                                    id="message" name="message" rows="6" required
                                    class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                >{{ old('message') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-8">
                            <button
                                type="submit"
                                class="flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light sm:w-auto"
                            >
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
