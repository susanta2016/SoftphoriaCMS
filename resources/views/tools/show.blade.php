{{--
    /tools/{slug} — one tool, and also its admin preview (same template,
    noindex, with a banner). Order keeps the tool itself near the top:
    heading + short intro, the functionality, then How it works / Use cases /
    additional content, FAQ, related tools and the call to action.

    The functionality area renders the selected module's own view
    (App\Tools\ToolFunctionality::view()); this template holds no
    tool-specific logic. Its scripts are loaded here only.
--}}
<x-layouts.site :seo="$seo">
    <x-slot:head>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/js/tools/page.js', ...($functionality?->assets() ?? [])])
        @endif
    </x-slot:head>

    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <main class="flex-1" data-tool-page data-tool-slug="{{ $tool->slug }}" data-tool-functionality="{{ $tool->functionality }}" data-tool-preview="{{ $isPreview ? 'true' : 'false' }}">
        @if ($isPreview)
            <div class="fixed inset-x-0 bottom-0 z-40 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950" role="status">
                Preview ({{ $tool->status->getLabel() }}) — {{ $tool->isLive() ? 'this is how the published page looks.' : 'not visible to the public.' }}
            </div>
        @endif

        {{-- Heading + short introduction --}}
        <header class="relative isolate overflow-hidden bg-gradient-to-b from-brand-sky to-white pt-28 pb-8 sm:pt-32 sm:pb-10">
            <div class="pointer-events-none absolute -top-32 right-0 -z-10 h-96 w-[40rem] rounded-full bg-brand-accent/10 blur-3xl" aria-hidden="true"></div>
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <x-blog.breadcrumbs :items="[['label' => 'Tools', 'url' => route('tools.index')], ['label' => $tool->name, 'url' => $tool->url()]]"/>

                <div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-start">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-accent text-white shadow-lg shadow-brand-accent/25" aria-hidden="true">
                        <x-site.icon :name="$tool->icon ?: 'code'" :mono="true" class="h-7 w-7"/>
                    </span>
                    <div class="max-w-3xl">
                        @if ($tool->category)
                            <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">{{ $tool->category->name }}</p>
                        @endif
                        <h1 class="mt-1 text-3xl leading-tight font-bold tracking-tight text-brand-navy sm:text-4xl">{{ $tool->heading ?: $tool->name }}</h1>
                        @if ($tool->introduction)
                            <p class="mt-3 text-lg leading-relaxed text-brand-navy/70">{{ $tool->introduction }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        {{-- The tool --}}
        <section class="mx-auto max-w-6xl px-4 sm:px-6" aria-label="{{ $tool->name }}">
            <div class="rounded-3xl border border-brand-navy/10 bg-white p-5 shadow-xl shadow-brand-navy/5 sm:p-8" data-tool-app>
                @if ($functionality)
                    @include($functionality->view(), $functionality->viewData() + ['tool' => $tool])
                @else
                    <p class="rounded-2xl bg-red-50 p-5 text-sm font-medium text-red-800" role="alert">
                        This tool's functionality ({{ $tool->functionality ?: 'none selected' }}) is not available in this deployment, so the page cannot be published.
                    </p>
                @endif
            </div>
        </section>

        @if ($importantNotes)
            <aside class="mx-auto mt-6 max-w-6xl px-4 sm:px-6" aria-label="Important notes">
                <div class="flex gap-4 rounded-2xl border border-amber-300/60 bg-amber-50 p-5 text-amber-950">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div class="blog-prose text-sm [&_p]:text-amber-950">
                        <p class="font-semibold">Important notes</p>
                        {!! $importantNotes !!}
                    </div>
                </div>
            </aside>
        @endif

        {{-- Supporting content + related service --}}
        @if ($sections->isNotEmpty() || $service)
            <div class="mx-auto mt-16 grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-12">
                <div class="min-w-0 space-y-12 lg:col-span-8">
                    @foreach ($sections as $id => [$heading, $html])
                        <section id="{{ $id }}" @if ($heading) aria-labelledby="{{ $id }}-heading" @endif>
                            @if ($heading)
                                <h2 id="{{ $id }}-heading" class="text-2xl font-bold tracking-tight text-brand-navy sm:text-3xl">{{ $heading }}</h2>
                            @endif
                            <div @class(['blog-prose', 'mt-4' => $heading])>{!! $html !!}</div>
                        </section>
                    @endforeach
                </div>

                @if ($service)
                    <aside class="lg:col-span-4" aria-label="Related service">
                        <div class="rounded-2xl border border-brand-navy/10 bg-white p-6 shadow-sm lg:sticky lg:top-28">
                            <p class="text-xs font-semibold tracking-[0.15em] text-brand-navy/50 uppercase">Related service</p>
                            <div class="mt-4 flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-sky text-brand-accent" aria-hidden="true">
                                    <x-site.icon :name="$service->icon ?: 'monitor'" class="h-5 w-5"/>
                                </span>
                                <div>
                                    <p class="font-bold text-brand-navy">{{ $service->title }}</p>
                                    @if ($service->summary)
                                        <p class="mt-1 text-sm leading-relaxed text-brand-navy/70">{{ \Illuminate\Support\Str::limit($service->summary, 160) }}</p>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ $service->url() }}" data-tool-cta="service" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-brand-navy/15 px-5 py-3 text-sm font-semibold text-brand-navy transition hover:border-brand-accent hover:text-brand-accent">
                                Explore {{ $service->title }} <x-site.arrow class="h-4 w-4"/>
                            </a>
                        </div>
                    </aside>
                @endif
            </div>
        @endif

        {{-- FAQ (only visible items; the FAQPage structured data matches) --}}
        @if ($faqs->isNotEmpty())
            <section class="mt-20 bg-brand-mist py-16 sm:py-20" aria-labelledby="faq-heading">
                <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <p class="text-sm font-semibold tracking-[0.15em] text-brand-accent uppercase">FAQ</p>
                        <h2 id="faq-heading" class="mt-2 text-3xl font-bold tracking-tight text-brand-navy">Common questions</h2>
                    </div>
                    <div class="space-y-3 lg:col-span-8">
                        @foreach ($faqs as $faq)
                            <details class="group rounded-2xl border border-brand-navy/10 bg-white px-6 py-5 shadow-sm open:border-brand-accent/30 open:shadow-md" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand-navy [&::-webkit-details-marker]:hidden">
                                    {{ $faq->question }}
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-sky text-brand-accent transition group-open:rotate-45" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-4 w-4"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
                                    </span>
                                </summary>
                                <p class="mt-3 leading-relaxed whitespace-pre-line text-brand-navy/70">{{ $faq->answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <div class="h-16"></div>
        @endif

        {{-- Related tools --}}
        @if ($related->isNotEmpty())
            <section class="bg-white py-16 sm:py-20" aria-labelledby="related-heading">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="related-heading" class="text-2xl font-bold text-brand-navy">Related tools</h2>
                        <a href="{{ route('tools.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-accent hover:text-brand-accent-dark">All tools <x-site.arrow class="h-3.5 w-3.5"/></a>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($related as $relatedTool)
                            <x-tool.card :tool="$relatedTool"/>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <x-tool.cta :cta="$cta"/>
    </main>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
