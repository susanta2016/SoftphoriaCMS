<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="relative isolate overflow-hidden bg-brand-ivory">
        <span aria-hidden="true" class="pointer-events-none absolute top-40 left-8 hidden text-lg text-brand-gold/30 sm:block">✦</span>
        <span aria-hidden="true" class="pointer-events-none absolute top-64 right-10 hidden text-sm text-brand-gold/30 sm:block">✦</span>

        <main class="relative mx-auto max-w-md px-4 pt-32 pb-20 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="font-serif text-3xl text-brand-navy sm:text-4xl">Log In</h1>
                <div class="my-5 flex items-center justify-center gap-3" aria-hidden="true">
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                    <span class="text-brand-gold">✦</span>
                    <span class="h-px w-12 bg-brand-gold/70"></span>
                </div>
                <p class="text-sm text-brand-navy/70">Welcome back. Enter your details below.</p>
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

            <form method="POST" action="{{ route('login') }}" class="mt-8 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
                @csrf

                <div class="grid grid-cols-1 gap-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium text-brand-navy">Email Address *</label>
                        <input
                            type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                            class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-brand-navy">Password *</label>
                        <div class="relative mt-1.5">
                            <input
                                type="password" id="password" name="password" required autocomplete="current-password"
                                class="block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 pr-11 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                            >
                            <button
                                type="button"
                                data-password-toggle
                                data-password-target="#password"
                                aria-label="Show password"
                                aria-pressed="false"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-brand-navy/50 transition hover:text-brand-gold"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                                    <path d="M2.05 12a10.94 10.94 0 0 1 19.9 0 10.94 10.94 0 0 1-19.9 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember" class="flex items-center gap-2 text-sm text-brand-navy">
                            <input type="checkbox" id="remember" name="remember" class="rounded border-brand-navy/30 text-brand-gold focus:ring-brand-gold">
                            Remember me
                        </label>

                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-navy/70 transition hover:text-brand-gold">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <div class="mt-8">
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                    >
                        Log In
                    </button>
                </div>

                <p class="mt-6 text-center text-sm text-brand-navy/70">
                    Don't have an account?
                    <a href="{{ route('register.show') }}" class="font-medium text-brand-navy transition hover:text-brand-gold">Register</a>
                </p>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
