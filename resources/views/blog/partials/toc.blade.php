<ol class="mt-3 space-y-1 text-sm" data-blog-toc>
    @foreach ($toc as $entry)
        <li @class(['pl-4' => $entry['level'] === 3])>
            <a href="#{{ $entry['id'] }}" class="block rounded-lg border-l-2 border-transparent px-3 py-1.5 text-brand-navy/65 transition hover:border-brand-accent hover:bg-brand-mist hover:text-brand-navy data-[active]:border-brand-accent data-[active]:bg-brand-sky data-[active]:font-semibold data-[active]:text-brand-accent">{{ $entry['text'] }}</a>
        </li>
    @endforeach
</ol>
