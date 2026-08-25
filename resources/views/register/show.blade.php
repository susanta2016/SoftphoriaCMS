@php
    // Reuses the same footer background artwork (Settings: footer.background_media_id)
    // rather than a new decorative asset — cropped to its cloud/gold-line edges as
    // two low-opacity corner accents so the registration page reads as part of the
    // same site without competing with the footer's own full-width use of it.
    $registerBackgroundMedia = ($registerBackgroundMediaId = app(\App\Shared\Services\Settings\SettingsRepository::class)->get('footer', 'background_media_id'))
        ? \App\Models\Media::find($registerBackgroundMediaId)
        : null;
    $registerBackgroundUrl = $registerBackgroundMedia
        ? \Illuminate\Support\Facades\Storage::disk($registerBackgroundMedia->disk)->url($registerBackgroundMedia->path)
        : null;
@endphp

<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        @if ($registerBackgroundUrl)
            <div
                aria-hidden="true"
                class="pointer-events-none absolute top-0 -left-20 h-80 w-[40rem] opacity-30 sm:h-[26rem] sm:w-[52rem]"
                style="
                    background-image: url('{{ $registerBackgroundUrl }}'); background-position: left top; background-size: auto 220%; background-repeat: no-repeat;
                    mask-image: radial-gradient(65% 65% at 15% 15%, black 35%, transparent 100%);
                    -webkit-mask-image: radial-gradient(65% 65% at 15% 15%, black 35%, transparent 100%);
                "
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute top-0 -right-20 h-80 w-[40rem] opacity-30 sm:h-[26rem] sm:w-[52rem]"
                style="
                    background-image: url('{{ $registerBackgroundUrl }}'); background-position: right top; background-size: auto 220%; background-repeat: no-repeat;
                    mask-image: radial-gradient(65% 65% at 85% 15%, black 35%, transparent 100%);
                    -webkit-mask-image: radial-gradient(65% 65% at 85% 15%, black 35%, transparent 100%);
                "
            ></div>
        @endif

        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

    <main class="relative mx-auto max-w-xl px-4 pt-32 pb-20 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Create Your Account</h1>
            <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                <span class="h-px w-12 bg-brand-gold/70"></span>
                <span class="text-brand-gold">✦</span>
                <span class="h-px w-12 bg-brand-gold/70"></span>
            </div>
            <p class="text-sm text-brand-navy/70">Join for free, or become a Pro Member for full access.</p>
        </div>

        @if (session('registration_notice'))
            <div class="mt-6 rounded-md border border-brand-gold/40 bg-brand-gold/10 px-4 py-3 text-sm text-brand-navy">
                {{ session('registration_notice') }}
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

        <form method="POST" action="{{ route('register.free') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
            @csrf

            <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-brand-navy">Full Name *</label>
                    <input
                        type="text" id="name" name="name" value="{{ old('name') }}" required autocomplete="name"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-navy">Email Address *</label>
                    <input
                        type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-brand-navy">Password *</label>
                    <input
                        type="password" id="password" name="password" required autocomplete="new-password" minlength="8"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-brand-navy">Confirm Password *</label>
                    <input
                        type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <p class="-mt-3 text-xs text-brand-navy/60 sm:col-span-2">At least 8 characters.</p>

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-brand-navy">Phone Number *</label>
                    <input
                        type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" required autocomplete="tel"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div>
                    <label for="zip_code" class="block text-sm font-medium text-brand-navy">Zip Code *</label>
                    <input
                        type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" required autocomplete="postal-code"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-brand-navy">Address *</label>
                    <textarea
                        id="address" name="address" required rows="2"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >{{ old('address') }}</textarea>
                </div>

                <div class="sm:col-span-2">
                    <label for="bio" class="block text-sm font-medium text-brand-navy">Biography</label>
                    <textarea
                        id="bio" name="bio" rows="3"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >{{ old('bio') }}</textarea>
                </div>
            </div>

            <div class="mt-8 space-y-3">
                <button
                    type="submit"
                    class="flex w-full items-center justify-center rounded-md border border-brand-navy/20 px-6 py-3 text-sm font-semibold tracking-wide text-brand-navy uppercase transition hover:border-brand-gold hover:text-brand-gold"
                >
                    Register Free
                </button>

                <div class="relative">
                    <button
                        type="submit"
                        formaction="{{ route('register.pro') }}"
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                    >
                        Become a Pro Member
                    </button>

                    <div class="mt-2 flex items-center justify-center gap-1.5 text-xs text-brand-navy/60">
                        <span>${{ $proPrice }}/month, billed monthly</span>
                        <button
                            type="button"
                            data-pro-tooltip-toggle
                            aria-label="Pro Membership pricing and cancellation details"
                            aria-expanded="false"
                            class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-brand-navy/30 text-[10px] font-semibold text-brand-navy/70 transition hover:border-brand-gold hover:text-brand-gold"
                        >
                            i
                        </button>
                    </div>

                    <div data-pro-tooltip class="mt-2 hidden rounded-lg border border-brand-navy/10 bg-white p-4 text-left text-xs leading-relaxed text-brand-navy/80 shadow-lg">
                        <p class="font-semibold text-brand-navy">${{ $proPrice }} / month</p>
                        <p class="mt-1">This is the current Pro Member price, set in Website Setup and always shown live — never fixed in the page itself.</p>
                        <p class="mt-2">{{ $cancellationNote }}</p>
                    </div>
                </div>
            </div>
        </form>
    </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
