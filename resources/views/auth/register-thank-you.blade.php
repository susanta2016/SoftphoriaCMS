<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <style>
        .auth-page { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        .auth-page main { max-width: 480px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .auth-page h1 { font-size: 2rem; margin: 0 0 1rem; }
        .auth-page p { color: #4b5563; }
        .auth-page .auth-links { margin-top: 1.5rem; font-size: 0.875rem; }
        .auth-page .auth-links a { color: #111827; }
    </style>

    <div class="auth-page">
        <main>
            <h1>Thank You</h1>
            <p>Thank you for registering! Please check your email and click the verification link to activate your account.</p>
            @if (Route::has('login'))
                <p class="auth-links">Already verified? <a href="{{ route('login') }}">Log in</a></p>
            @endif
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
