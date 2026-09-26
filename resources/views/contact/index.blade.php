{{--
    ADMIN-010 public Contact Us page — shares the real site header/footer
    chrome (x-layouts.site, same as home.blade.php/pages/show.blade.php).
    Rebuilt as a hero band + two-column layout (contact details beside the
    form). Contact details come from the shared x-site.contact-info
    component, which never puts the full email/phone/WhatsApp in the HTML
    (masked + on-demand reveal); the form is the shared x-site.contact-form,
    so the CMS contact_form section never needs a second copy.
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1">
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand-navy-dark via-brand-navy to-brand-accent-dark pt-32 pb-28 text-white sm:pt-40 sm:pb-36">
            <div class="pointer-events-none absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-brand-accent/40 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-20 -z-10 h-80 w-80 rounded-full bg-brand-accent-light/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.08)_1px,transparent_0)] [background-size:28px_28px]" aria-hidden="true"></div>

            <div class="mx-auto max-w-6xl px-4 text-center sm:px-6">
                <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-widest text-white/85 uppercase backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    Contact Us
                </p>
                <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">Let's start a conversation</h1>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">
                    Have a project in mind, a question about our services, or need support? Tell us a little about it and the {{ $siteName }} team will get back to you.
                </p>

                <ul class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-white/80">
                    @foreach (['Your details stay private', 'Spam-protected form', 'A real person replies'] as $point)
                        <li class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-4 w-4 text-emerald-300" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Details + form --}}
        <section class="relative flow-root bg-brand-mist pb-20">
            <div class="mx-auto -mt-16 grid max-w-6xl gap-8 px-4 sm:-mt-20 sm:px-6 lg:grid-cols-5">
                <aside class="lg:col-span-2" aria-labelledby="contact-details-heading">
                    <div class="rounded-3xl border border-brand-navy/5 bg-white p-6 shadow-xl shadow-brand-navy/5 sm:p-8">
                        <h2 id="contact-details-heading" class="text-xl font-bold text-brand-navy">Reach us directly</h2>
                        <p class="mt-2 text-sm leading-relaxed text-brand-navy/60">
                            To keep our inbox free of spam bots, contact details are partly hidden. Select <span class="font-semibold text-brand-navy/80">Reveal</span> to see them in full.
                        </p>

                        <x-site.contact-info
                            class="mt-6"
                            :email="$contactEmail"
                            :phone="$contactPhone"
                            :whatsapp="$contactWhatsapp"
                            :address="$contactAddress"
                        />
                    </div>
                </aside>

                <div class="lg:col-span-3">
                    <div class="rounded-3xl border border-brand-navy/5 bg-white p-6 shadow-xl shadow-brand-navy/5 sm:p-10">
                        <h2 class="text-2xl font-bold text-brand-navy">Send us a message</h2>
                        <p class="mt-2 text-sm text-brand-navy/60">Fields marked <span class="text-brand-accent">*</span> are required.</p>

                        @if (session('status'))
                            <div role="status" class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>{{ session('status') }}</span>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div role="alert" class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <p class="font-semibold">Please check the highlighted fields:</p>
                                <ul class="mt-1 list-disc space-y-1 pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <x-site.contact-form class="mt-8"/>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
