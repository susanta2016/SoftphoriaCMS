{{--
    Email preview for the admin (template and campaign Preview actions).
    The rendered email is a full HTML document, so it goes in a sandboxed
    iframe: no scripts, no same-origin access, and none of the admin
    panel's own CSS. Desktop is the usual 600px email width plus margin,
    mobile a typical phone width.
--}}
@include('newsletter-marketing::filament.styles')
<div class="nl-admin nl-stack">
    @if (filled($from ?? null) || filled($subject ?? null) || filled($preheader ?? null))
        <dl class="nl-dl">
            @if (filled($from ?? null))
                <dt>From</dt><dd>{{ $from }}</dd>
            @endif
            @if (filled($subject ?? null))
                <dt>Subject</dt><dd>{{ $subject }}</dd>
            @endif
            @if (filled($preheader ?? null))
                <dt>Preheader</dt><dd>{{ $preheader }}</dd>
            @endif
        </dl>
    @endif

    <div x-data="{ view: 'desktop' }" class="nl-stack">
        <div class="nl-tabs" role="tablist" aria-label="Preview size">
            <button type="button" class="nl-tab" role="tab" x-on:click="view = 'desktop'" x-bind:aria-selected="view === 'desktop'">Desktop</button>
            <button type="button" class="nl-tab" role="tab" x-on:click="view = 'mobile'" x-bind:aria-selected="view === 'mobile'">Mobile</button>
        </div>
        <div class="nl-frame-wrap">
            <iframe sandbox="" title="Email preview" class="nl-frame" srcdoc="{{ $html }}"
                    style="width: 660px" x-bind:style="view === 'desktop' ? 'width: 660px' : 'width: 375px'"></iframe>
        </div>
    </div>

    @if (filled($text ?? null))
        <details>
            <summary>Plain-text version</summary>
            <pre class="nl-text">{{ $text }}</pre>
        </details>
    @endif

    @if (! empty($links))
        <details>
            <summary>Links in this email ({{ count($links) }})</summary>
            <ul class="nl-links">
                @foreach ($links as $link)
                    <li><a href="{{ $link }}" target="_blank" rel="noopener noreferrer">{{ $link }}</a></li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
