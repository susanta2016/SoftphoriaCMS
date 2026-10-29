{{--
    Confirmation body for UserResource::forceLoginAction().

    Filament renders an action modal's window as a form element (wire:submit
    → callMountedAction), and HTML forbids nested forms — a form placed here
    would be silently dropped by the browser and the button would submit
    Filament's own form instead (which does nothing). So the button builds a
    standalone POST form on the document body (outside the modal) carrying
    the CSRF token, and submits that: a normal full-page POST to
    ForceLoginController, which re-checks the feature flag, admin access and
    eligibility.
--}}
<div class="space-y-4 text-sm">
    <p>
        Your admin session will be <strong>logged out</strong>, and this browser will be signed in as
        <strong>{{ $user->name }}</strong> ({{ $user->email }}).
    </p>
    <p>
        You'll see the site exactly as this member does. To return to the admin panel, sign out and sign in again at
        <strong>/admin/login</strong>. This action is recorded in the audit log.
    </p>

    <x-filament::button
        type="button"
        color="danger"
        icon="heroicon-o-arrow-right-end-on-rectangle"
        data-force-login-url="{{ route('admin.users.force-login', $user) }}"
        data-force-login-token="{{ csrf_token() }}"
        x-data="{}"
        x-on:click="
            $el.disabled = true;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = $el.dataset.forceLoginUrl;
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = $el.dataset.forceLoginToken;
            form.appendChild(token);
            document.body.appendChild(form);
            form.submit();
        "
    >
        Log out and sign in as {{ $user->name }}
    </x-filament::button>
</div>
