@props([
    'heading' => 'Join Our Newsletter',
    'description' => null,
    'idPrefix' => 'newsletter',
])

<div>
    <h3 class="text-xs font-semibold tracking-wider text-brand-navy uppercase">{{ $heading }}</h3>
    @if ($description)
        <p class="mt-3 text-sm leading-relaxed text-brand-navy/70">{{ $description }}</p>
    @endif

    @if (session('newsletter_status'))
        <p class="mt-3 rounded-md border border-brand-gold/30 bg-brand-gold/10 px-3 py-2.5 text-sm text-brand-navy">
            {{ session('newsletter_status') }}
        </p>
    @else
        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3">
            @csrf
            <div @class([
                'flex overflow-hidden rounded-md border bg-white',
                'border-red-400' => $errors->has('email'),
                'border-brand-navy/20' => ! $errors->has('email'),
            ])>
                <label for="{{ $idPrefix }}-newsletter-email" class="sr-only">Email address</label>
                <input
                    id="{{ $idPrefix }}-newsletter-email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="Enter your email"
                    required
                    class="w-full min-w-0 border-0 px-3 py-2.5 text-sm text-brand-navy placeholder:text-brand-navy/40 focus:outline-none"
                >
                <button
                    type="submit"
                    class="shrink-0 bg-brand-gold px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-gold-light"
                >
                    Subscribe
                </button>
            </div>
            @error('email')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </form>
    @endif
</div>
