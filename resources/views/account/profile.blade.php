<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <style>
        .account-page { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        .account-page main { max-width: 560px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .account-page h1 { font-size: 2rem; margin: 0 0 1.5rem; }
        .account-page .status-message { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .account-page .error-list { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .account-page .error-list ul { margin: 0; padding-left: 1.25rem; }
        .account-page form { display: grid; gap: 1rem; }
        .account-page label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .account-page input, .account-page textarea { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font: inherit; }
        .account-page button[type="submit"] { justify-self: start; padding: 0.625rem 1.5rem; background: #111827; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; }
        .account-page .account-nav { margin-bottom: 2rem; display: flex; gap: 1rem; font-size: 0.875rem; }
        .account-page .account-nav a { color: #4b5563; }
        .account-page .account-nav a[aria-current="page"] { color: #111827; font-weight: 600; }
    </style>

    <div class="account-page">
        <main>
            <nav class="account-nav" aria-label="Account">
                <a href="{{ route('account.profile.edit') }}" aria-current="page">Profile</a>
                <a href="{{ route('account.password.edit') }}">Password</a>
            </nav>

            <h1>Edit Profile</h1>

            @if (session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-list">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('account.profile.update') }}">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                </div>

                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>

                <div>
                    <label for="bio">Biography</label>
                    <textarea id="bio" name="bio" rows="4">{{ old('bio', $profile?->bio) }}</textarea>
                </div>

                <button type="submit">Save Changes</button>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
