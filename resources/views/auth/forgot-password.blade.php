<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

        <main class="relative mx-auto max-w-md px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Forgot Password</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <p class="text-sm text-brand-navy/70">Enter your email and we'll send you a link to reset your password.</p>
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

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-navy">Email Address *</label>
                    <input
                        type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div class="mt-8">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                    >
                        Send Reset Link
                    </button>
                </div>

                <p class="mt-6 text-center text-sm text-brand-navy/70">
                    <a href="{{ route('login') }}" class="font-medium text-brand-navy transition hover:text-brand-gold">Back to Login</a>
                </p>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
