<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Change Password</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Choose a new password for your account.</p>

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

        <form method="POST" action="{{ route('account.password.update') }}" class="mt-8 max-w-md">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-y-5">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-brand-navy">Current Password *</label>
                    <div class="relative mt-1.5">
                        <input
                            type="password" id="current_password" name="current_password" required autocomplete="current-password"
                            class="block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 pr-11 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                        >
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="#current_password"
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

                <div>
                    <label for="password" class="block text-sm font-medium text-brand-navy">New Password *</label>
                    <div class="relative mt-1.5">
                        <input
                            type="password" id="password" name="password" required autocomplete="new-password" minlength="8"
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

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-brand-navy">Confirm New Password *</label>
                    <input
                        type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <p class="-mt-3 text-xs text-brand-navy/60">At least 8 characters.</p>
            </div>

            <div class="mt-8">
                <button
                    type="submit"
                    class="rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                >
                    Change Password
                </button>
            </div>
        </form>
    </div>
</x-layouts.account>
