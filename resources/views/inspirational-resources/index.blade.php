<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

        <main class="relative mx-auto max-w-3xl px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Inspirational Resources</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <p class="mx-auto max-w-xl text-sm text-brand-navy/70">
                    Has a song, an album, or a moment of reflection touched your life in a meaningful way?
                    We'd love to hear about it. Share your story below — our team reads every submission.
                </p>
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

            <form method="POST" action="{{ route('inspirational-resources.submit') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                @csrf

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

                    <div>
                        <label for="subject" class="block text-sm font-medium text-brand-navy">Subject</label>
                        <input
                            type="text" id="subject" name="subject" value="{{ old('subject') }}"
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-brand-navy">Category *</label>
                        <input
                            type="text" id="category" name="category" value="{{ old('category') }}" required placeholder="e.g. Testimony, Encouragement"
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                    </div>

                    @if ($albums->isNotEmpty())
                        <div>
                            <label for="related_album_id" class="block text-sm font-medium text-brand-navy">Related Album</label>
                            <select id="related_album_id" name="related_album_id" class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                <option value="">None</option>
                                @foreach ($albums as $album)
                                    <option value="{{ $album->id }}" @selected(old('related_album_id') == $album->id)>{{ $album->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($tracks->isNotEmpty())
                        <div>
                            <label for="related_track_id" class="block text-sm font-medium text-brand-navy">Related Song</label>
                            <select id="related_track_id" name="related_track_id" class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none">
                                <option value="">None</option>
                                @foreach ($tracks as $track)
                                    <option value="{{ $track->id }}" @selected(old('related_track_id') == $track->id)>{{ $track->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <label for="message" class="block text-sm font-medium text-brand-navy">Message *</label>
                        <textarea
                            id="message" name="message" rows="5" required
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
