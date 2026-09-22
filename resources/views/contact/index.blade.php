{{--
    ADMIN-010 public Contact Us page — shares the real site header/footer
    chrome (x-layouts.site, same as home.blade.php/pages/show.blade.php)
    rather than any client-specific styling. WEB-101 rebuilt this on the
    Softphoria design system (Tailwind utilities + resources/views/components/site/*)
    in place of the old inline <style> block, and reuses the shared
    x-site.contact-form component so the CMS page contact_form section
    (resources/views/pages/show.blade.php) never needs a second copy of
    this markup.
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="pt-24 pb-12 sm:pt-32">
        <x-site.section>
            <h1 class="text-3xl font-bold text-brand-navy sm:text-4xl">Contact Us</h1>

            <x-site.contact-info
                class="mt-6"
                :email="$contactEmail"
                :phone="$contactPhone"
                :whatsapp="$contactWhatsapp"
                :address="$contactAddress"
            />

            @if (session('status'))
                <div class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-site.contact-form class="mt-8"/>
        </x-site.section>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
