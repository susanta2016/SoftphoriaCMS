<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <style>
        .auth-page { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        .auth-page main { max-width: 480px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .auth-page h1 { font-size: 2rem; margin: 0 0 1rem; }
        .auth-page p { color: #4b5563; }
        .auth-page .auth-links { margin-top: 1.5rem; font-size: 0.875rem; }
        .auth-page .auth-links a { color: #111827; }
        .auth-page .status-message { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .auth-page form { display: grid; gap: 0.75rem; margin-top: 1rem; }
        .auth-page label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .auth-page input { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font: inherit; }
        .auth-page button[type="submit"] { justify-self: start; padding: 0.625rem 1.5rem; background: #111827; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; }
    </style>

    <div class="auth-page">
        <main>
            @if (session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if ($verified)
                <h1>Email Verified</h1>
                <p>Your email address has been verified. You can now log in.</p>
                @if (Route::has('login'))
                    <p class="auth-links"><a href="{{ route('login') }}">Log in</a></p>
                @endif
            @else
                <h1>Link Invalid or Expired</h1>
                <p>This verification link is invalid, has already been used, or has expired. Enter your email below to request a new one.</p>

                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <button type="submit">Resend Verification Link</button>
                </form>

                @if (Route::has('login'))
                    <p class="auth-links"><a href="{{ route('login') }}">Log in</a></p>
                @endif
            @endif
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
