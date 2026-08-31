@php
    // One array of nav items so a future section (Membership, Music
    // Library, My Content) is a 2-line addition here, not a markup
    // restructure — see account layout's own plan reasoning.
    $navItems = [
        [
            'route' => 'account.dashboard',
            'label' => 'Dashboard',
            'icon' => '<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6V11h-6v9Zm0-16v5h6V4h-6Z" stroke-linejoin="round"/>',
        ],
        [
            'route' => 'account.profile.edit',
            'label' => 'Profile',
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7" stroke-linecap="round"/>',
        ],
        [
            'route' => 'account.password.edit',
            'label' => 'Change Password',
            'icon' => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke-linecap="round"/>',
        ],
        // Phase 1: no paid membership — this nav item is left out of the
        // list entirely when disabled (UI only, see config/features.php);
        // /account/subscription itself stays reachable directly so an
        // existing subscriber can still view their own status.
        ...(config('features.member_subscription_enabled') ? [[
            'route' => 'account.subscription',
            'label' => 'Subscription',
            'icon' => '<path d="M20 7 12 3 4 7v10l8 4 8-4V7Z" stroke-linejoin="round"/><path d="M4 7l8 4 8-4M12 11v10" stroke-linecap="round" stroke-linejoin="round"/>',
        ]] : []),
        [
            'route' => 'account.orders',
            'label' => 'Orders',
            'icon' => '<path d="M9 7V5a3 3 0 0 1 6 0v2M5 9h14l-1 11H6L5 9Z" stroke-linejoin="round" stroke-linecap="round"/>',
        ],
        [
            'route' => 'account.downloads',
            'label' => 'Downloads',
            'icon' => '<path d="M12 4v11m0 0 4-4m-4 4-4-4M4 19h16" stroke-linecap="round" stroke-linejoin="round"/>',
        ],
        [
            'route' => 'account.transactions',
            'label' => 'Transaction History',
            'icon' => '<path d="M4 5h16M4 12h16M4 19h10" stroke-linecap="round"/>',
        ],
    ];
@endphp

<nav
    id="account-sidebar"
    data-account-sidebar
    aria-label="Account"
    class="hidden w-full shrink-0 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-brand-navy/5 lg:block lg:w-64"
>
    <ul class="space-y-1">
        @foreach ($navItems as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @if ($active) aria-current="page" @endif
                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-brand-gold/10 text-brand-gold' : 'text-brand-navy hover:bg-brand-gold/10 hover:text-brand-gold' }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-3 border-t border-brand-navy/10 pt-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium text-brand-navy transition hover:bg-red-50 hover:text-red-700"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Log Out
            </button>
        </form>
    </div>
</nav>
