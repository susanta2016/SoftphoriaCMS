@props(['seo', 'siteName', 'tagline', 'logo'])

<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <div class="bg-brand-ivory">
        <main class="mx-auto max-w-6xl px-4 pt-28 pb-20 sm:px-6 lg:px-8">
            <button
                type="button"
                data-account-sidebar-toggle
                aria-label="Toggle account menu"
                aria-expanded="false"
                class="mb-4 inline-flex items-center gap-2 rounded-md border border-brand-navy/20 px-4 py-2 text-sm font-medium text-brand-navy transition hover:border-brand-gold hover:text-brand-gold lg:hidden"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                </svg>
                Account Menu
            </button>

            <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
                <x-account.sidebar/>

                <div class="min-w-0 flex-1">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
