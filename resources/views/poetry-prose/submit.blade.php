<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

        <main class="relative mx-auto max-w-3xl px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <x-site.breadcrumbs :items="[
                ['label' => 'Home', 'url' => route('home')],
                ['label' => 'Light Posts', 'url' => route('poetry-prose.index')],
                ['label' => $heading],
            ]"/>

            <div class="mt-4 text-center">
                <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">{{ $heading }}</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <p class="mx-auto max-w-xl text-sm whitespace-pre-line text-brand-navy/70">{{ $intro }}</p>
            </div>

            @if (session('status'))
                <div class="mt-6 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('poetry-prose.submit') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                @csrf

                {{-- Honeypot: hidden from real visitors; see PoetryProseSubmissionController::store(). --}}
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="hp_website">Website</label>
                    <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                </div>

                @auth
                    <p class="mb-5 text-sm text-brand-navy/60">
                        Submitting as <span class="font-medium text-brand-navy">{{ auth()->user()->name }}</span> ({{ auth()->user()->email }}).
                    </p>
                @endauth

                <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                    @guest
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
                    @endguest

                    <div>
                        <label for="subject" class="block text-sm font-medium text-brand-navy">Subject</label>
                        <input
                            type="text" id="subject" name="subject" value="{{ old('subject') }}"
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-brand-navy">Category *</label>
                        <select
                            id="category" name="category" required
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 bg-white px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                            <option value="" disabled @selected(! old('category'))>Select a category</option>
                            @foreach (\App\Modules\PoetryProse\Models\PoetryProseSubmission::CATEGORY_OPTIONS as $option)
                                <option value="{{ $option }}" @selected(old('category') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="theme" class="block text-sm font-medium text-brand-navy">Theme *</label>
                        <select
                            id="theme" name="theme" required
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 bg-white px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                            <option value="" disabled @selected(! old('theme'))>Select a theme</option>
                            @foreach (\App\Modules\PoetryProse\Models\PoetryProseSubmission::THEME_OPTIONS as $option)
                                <option value="{{ $option }}" @selected(old('theme') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="reference_url" class="block text-sm font-medium text-brand-navy">Reference Website URL</label>
                        <input
                            type="url" id="reference_url" name="reference_url" value="{{ old('reference_url') }}" placeholder="https://example.com/your-writing"
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                    </div>

                    <div class="sm:col-span-2">
                        <label for="message" class="block text-sm font-medium text-brand-navy">Message *</label>
                        <textarea
                            id="message" name="message" rows="8" required
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >{{ old('message') }}</textarea>
                    </div>
                </div>

                <div class="mt-8">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light sm:w-auto"
                    >
                        Submit
                    </button>
                </div>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
