<x-layouts.account :seo="$seo" :site-name="$siteName" :tagline="$tagline" :logo="$logo">
    <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5 sm:p-8">
        <h1 class="font-serif text-2xl text-brand-navy sm:text-3xl">Welcome back, {{ explode(' ', $user->name)[0] }}</h1>
        <p class="mt-1 text-sm text-brand-navy/70">Here's a look at your account.</p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5">
            <h2 class="font-serif text-lg text-brand-navy">Profile</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-brand-navy/60">Name</dt>
                    <dd class="text-right font-medium text-brand-navy">{{ $user->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-brand-navy/60">Email</dt>
                    <dd class="text-right font-medium text-brand-navy">{{ $user->email }}</dd>
                </div>
            </dl>
            <a href="{{ route('account.profile.edit') }}" class="mt-4 inline-block text-sm font-medium text-brand-gold transition hover:text-brand-gold-light">
                Edit Profile →
            </a>
        </div>

        {{-- Phase 1: no paid membership (UI only, config/features.php) --}}
        @if (config('features.member_subscription_enabled'))
            <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5">
                <h2 class="font-serif text-lg text-brand-navy">Membership</h2>
                <p class="mt-4 text-sm text-brand-navy">
                    @if ($hasActiveMembership)
                        <span class="inline-flex items-center rounded-full bg-brand-gold/10 px-3 py-1 text-xs font-semibold tracking-wide text-brand-gold uppercase">Pro Member</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-brand-navy/10 px-3 py-1 text-xs font-semibold tracking-wide text-brand-navy/70 uppercase">Free Member</span>
                    @endif
                </p>
                @if ($subscriptionStatusLabel)
                    <p class="mt-3 text-sm text-brand-navy/60">Status: {{ $subscriptionStatusLabel }}</p>
                @endif
                <a href="{{ route('account.subscription') }}" class="mt-4 inline-block text-sm font-medium text-brand-gold transition hover:text-brand-gold-light">
                    View Subscription →
                </a>
            </div>
        @endif
    </div>

    <div class="mt-6 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-brand-navy/5">
        <h2 class="font-serif text-lg text-brand-navy">Quick Actions</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('account.profile.edit') }}" class="rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                Edit Profile
            </a>
            <a href="{{ route('account.password.edit') }}" class="rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                Change Password
            </a>
            @if (config('features.member_subscription_enabled'))
                <a href="{{ route('account.subscription') }}" class="rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                    View Subscription
                </a>
            @endif
            <a href="{{ route('account.transactions') }}" class="rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold">
                Transaction History
            </a>
        </div>
    </div>
</x-layouts.account>
