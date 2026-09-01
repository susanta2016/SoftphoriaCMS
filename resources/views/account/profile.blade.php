<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Profile</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Update your account details.</p>

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

        <form method="POST" action="{{ route('account.profile.update') }}" class="mt-8">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-brand-navy">Full Name *</label>
                    <input
                        type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-brand-navy">Email Address *</label>
                    <input
                        type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >
                    <p class="mt-1.5 text-xs text-brand-navy/60">Changing your email will require you to verify the new address again.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="bio" class="block text-sm font-medium text-brand-navy">Biography</label>
                    <textarea
                        id="bio" name="bio" rows="3"
                        class="mt-1.5 block w-full rounded-md border border-brand-navy/20 px-3.5 py-2.5 text-sm text-brand-navy shadow-sm focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                    >{{ old('bio', $profile?->bio) }}</textarea>
                </div>
            </div>

            <div class="mt-8">
                <button
                    type="submit"
                    class="rounded-md bg-brand-gold px-6 py-3 text-sm font-semibold tracking-wide text-white uppercase shadow-sm transition hover:bg-brand-gold-light"
                >
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</x-layouts.account>
