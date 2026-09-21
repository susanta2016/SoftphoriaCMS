@if ($hasVideo)
    <div class="mx-auto max-w-2xl overflow-hidden rounded-2xl bg-brand-navy-dark shadow-lg ring-1 ring-brand-navy/10">
        <video controls playsinline preload="none" class="aspect-video w-full">
            <source src="{{ route('media.watch', $video) }}" type="{{ $video->mime_type }}">
        </video>
    </div>
@endif
