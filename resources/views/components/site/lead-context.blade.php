{{--
    Hidden lead-context fields for a contact form: the page it's submitted
    from, which form it is (`source`), which call-to-action opened it, and
    where the visitor arrived from. resources/js/app.js fills page_url /
    page_title / referrer at submit time (so they stay right after in-page
    navigation) and lead_cta when a CTA opens the popup; the server
    validates everything in App\Shared\Support\Contact\LeadContext.
--}}
@props(['source'])

<input type="hidden" name="page_url" value="{{ url()->current() }}" data-lead-field="page_url">
<input type="hidden" name="page_title" value="" data-lead-field="page_title">
<input type="hidden" name="referrer" value="" data-lead-field="referrer">
<input type="hidden" name="lead_source" value="{{ $source }}">
<input type="hidden" name="lead_cta" value="" data-lead-field="lead_cta">
