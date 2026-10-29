{{-- Confirmation body for UserResource::forceLoginAction(). The confirm button is a real POST form (CSRF-protected) to ForceLoginController, which re-checks the feature flag, admin access and eligibility. --}}
<div class="space-y-4 text-sm">
    <p>
        Your admin session will be <strong>logged out</strong>, and this browser will be signed in as
        <strong>{{ $user->name }}</strong> ({{ $user->email }}).
    </p>
    <p>
        You'll see the site exactly as this member does. To return to the admin panel, sign out and sign in again at
        <strong>/admin/login</strong>. This action is recorded in the audit log.
    </p>

    <form method="POST" action="{{ route('admin.users.force-login', $user) }}">
        @csrf
        <x-filament::button type="submit" color="danger" icon="heroicon-o-arrow-right-end-on-rectangle">
            Log out and sign in as {{ $user->name }}
        </x-filament::button>
    </form>
</div>
