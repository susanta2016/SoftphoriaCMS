{{-- Visible breadcrumb trail; the matching BreadcrumbList JSON-LD is built in BlogController. --}}
@props(['items', 'dark' => false])

<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol @class(['flex flex-wrap items-center gap-x-2 gap-y-1 text-sm', 'text-white/70' => $dark, 'text-brand-navy/55' => ! $dark])>
        <li><a href="{{ route('home') }}" @class(['transition', 'hover:text-white' => $dark, 'hover:text-brand-accent' => ! $dark])>Home</a></li>
        @foreach ($items as $item)
            <li aria-hidden="true">/</li>
            <li class="min-w-0">
                @if ($loop->last)
                    <span aria-current="page" @class(['block truncate font-medium', 'text-white' => $dark, 'text-brand-navy' => ! $dark, 'max-w-[16rem] sm:max-w-md'])>{{ $item['label'] }}</span>
                @else
                    <a href="{{ $item['url'] }}" @class(['transition', 'hover:text-white' => $dark, 'hover:text-brand-accent' => ! $dark])>{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
