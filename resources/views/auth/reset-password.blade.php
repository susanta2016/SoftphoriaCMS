<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <style>
        .auth-page { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        .auth-page main { max-width: 480px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .auth-page h1 { font-size: 2rem; margin: 0 0 1.5rem; }
        .auth-page .error-list { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .auth-page .error-list ul { margin: 0; padding-left: 1.25rem; }
        .auth-page form { display: grid; gap: 1rem; }
        .auth-page label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .auth-page input { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font: inherit; }
        .auth-page button[type="submit"] { justify-self: start; padding: 0.625rem 1.5rem; background: #111827; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; }
    </style>

    <div class="auth-page">
        <main>
            <h1>Reset Password</h1>

            @if ($errors->any())
                <div class="error-list">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required>
                </div>

                <div>
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div>
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                </div>

                <button type="submit">Reset Password</button>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
