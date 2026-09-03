{{--
    ADMIN-010 public Contact Us page — shares the real site header/footer
    chrome (x-layouts.site, same as home.blade.php/pages/show.blade.php)
    rather than any client-specific styling. Neutral inline CSS, matching
    resources/views/pages/show.blade.php's own approach, since this is a
    Core page, not JacobCMS content.
--}}
<x-layouts.site :seo="$seo">
    <x-site.header :site-name="$siteName" :tagline="$tagline" :logo="$logo"/>

    <style>
        .contact-page { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; }
        .contact-page main { max-width: 860px; margin: 0 auto; padding: 7rem 1.5rem 4rem; }
        .contact-page h1 { font-size: 2rem; margin: 0 0 1.5rem; }
        .contact-page .contact-info { margin-bottom: 2rem; color: #4b5563; }
        .contact-page .contact-info p { margin: 0.25rem 0; }
        .contact-page .status-message { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .contact-page .error-list { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .contact-page .error-list ul { margin: 0; padding-left: 1.25rem; }
        .contact-page form { display: grid; gap: 1rem; }
        .contact-page label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .contact-page input, .contact-page textarea { width: 100%; padding: 0.625rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font: inherit; }
        .contact-page .honeypot-field { position: absolute; left: -9999px; height: 0; width: 0; overflow: hidden; }
        .contact-page button[type="submit"] { justify-self: start; padding: 0.625rem 1.5rem; background: #111827; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; }
    </style>

    <div class="contact-page">
        <main>
            <h1>Contact Us</h1>

            @if ($contactEmail || $contactAddress)
                <div class="contact-info">
                    @if ($contactEmail)
                        <p>Email: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
                    @endif
                    @if ($contactAddress)
                        <p style="white-space: pre-line">Address: {{ $contactAddress }}</p>
                    @endif
                </div>
            @endif

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

            <form method="POST" action="{{ route('contact.submit') }}">
                @csrf

                {{--
                    Honeypot: a real visitor never sees or fills this in
                    (visually hidden, tabindex -1, no autocomplete). A bot
                    that blindly fills every field trips it — see
                    ContactController::store().
                --}}
                <div class="honeypot-field" aria-hidden="true">
                    <label for="hp_website">Website</label>
                    <input type="text" id="hp_website" name="hp_website" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>

                <div>
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>

                <div>
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}">
                </div>

                <div>
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}">
                </div>

                <div>
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">Select a category (optional)</option>
                        <option value="general" @selected(old('category') === 'general')>General Inquiry</option>
                        <option value="support" @selected(old('category') === 'support')>Support</option>
                        <option value="feedback" @selected(old('category') === 'feedback')>Feedback</option>
                        <option value="other" @selected(old('category') === 'other')>Other</option>
                    </select>
                </div>

                <div>
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                </div>

                <button type="submit">Send Message</button>
            </form>
        </main>
    </div>

    <x-site.footer :site-name="$siteName" :tagline="$tagline"/>
</x-layouts.site>
