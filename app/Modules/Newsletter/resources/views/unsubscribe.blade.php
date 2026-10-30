{{--
    Campaign unsubscribe page (UnsubscribeController). Self-contained on
    purpose: served outside the "web" middleware group (no session, no
    beta gate/maintenance mode), so it must not depend on the site layout.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Unsubscribe — {{ $siteName }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f3f4f6; color: #111827; }
        main { max-width: 480px; margin: 12vh auto 0; padding: 32px 24px; background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); text-align: center; }
        h1 { font-size: 1.375rem; margin: 0 0 12px; }
        p { line-height: 1.6; margin: 0 0 20px; color: #4b5563; }
        button { font: inherit; font-weight: 600; padding: 12px 24px; border: 0; border-radius: 8px; background: #111827; color: #ffffff; cursor: pointer; }
        button:focus-visible { outline: 3px solid #2563eb; outline-offset: 2px; }
        @media (max-width: 520px) { main { margin: 24px 16px; } }
        @media (prefers-color-scheme: dark) {
            body { background: #111827; color: #f9fafb; }
            main { background: #1f2937; box-shadow: none; }
            p { color: #d1d5db; }
            button { background: #f9fafb; color: #111827; }
        }
    </style>
</head>
<body>
<main>
    @if (! $valid)
        <h1>This link isn’t valid</h1>
        <p>We couldn’t find this unsubscribe link. It may be incomplete — try copying the whole link from the email.</p>
    @elseif ($done)
        <h1>You’re unsubscribed</h1>
        <p>You won’t receive newsletter emails from {{ $siteName }} any more. You can subscribe again from our website at any time.</p>
    @else
        <h1>Unsubscribe from {{ $siteName }}?</h1>
        <p>You’ll stop receiving our newsletter emails.</p>
        <form method="post" action="{{ route('newsletter.unsubscribe.confirm', ['token' => $token]) }}">
            <button type="submit">Unsubscribe</button>
        </form>
    @endif
</main>
</body>
</html>
