<?php

/*
|--------------------------------------------------------------------------
| Email Template registry (Website Setup, docs/ARCHITECTURE.md §16.5/§16.6)
|--------------------------------------------------------------------------
|
| The single source of truth for which notification_key/recipient_type
| rows EmailTemplateSeeder creates and which {{variable}} tokens
| EmailTemplateResource displays as available for each key. Only events
| that map to functionality that already exists or is explicitly approved
| for Phase 1 are listed here — a future module adds its own entry (and a
| seeder row) only once it ships and its notification copy is approved,
| never ahead of time.
|
| 'recipients' lists which EmailRecipientType variants this key has.
|
*/

return [

    'email_verification' => [
        'label' => 'Verify Email',
        'recipients' => ['user'],
        'variables' => ['user_name', 'verification_url', 'site_name'],
        'default_subject' => '{{site_name}} — Verify Your Email Address',
        'default_html_body' => <<<'HTML'
            <p>Hi {{user_name}},</p>
            <p>Thanks for joining {{site_name}}. Please verify your email address to activate your account.</p>
            <p><a href="{{verification_url}}">Verify My Email</a></p>
            <p>This link will expire in 24 hours. If you didn't create this account, you can safely ignore this email.</p>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            Thanks for joining {{site_name}}. Please verify your email address to activate your account.

            Verify my email: {{verification_url}}

            This link will expire in 24 hours. If you didn't create this account, you can safely ignore this email.
            TEXT,
    ],

    'user_registered' => [
        'label' => 'New Registration / Welcome',
        'recipients' => ['user', 'admin'],
        'variables' => ['user_name', 'user_email', 'site_name'],
    ],

    'password_reset' => [
        'label' => 'Password Reset / Generate New Password',
        'recipients' => ['user'],
        'variables' => ['user_name', 'reset_url', 'site_name'],
        'default_subject' => '{{site_name}} — Reset Your Password',
        'default_html_body' => <<<'HTML'
            <p>Hi {{user_name}},</p>
            <p>We received a request to reset your password on {{site_name}}. Click the link below to choose a new password.</p>
            <p><a href="{{reset_url}}">Reset My Password</a></p>
            <p>If you didn't request this, you can safely ignore this email — your password will remain unchanged.</p>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            We received a request to reset your password on {{site_name}}. Use the link below to choose a new password.

            Reset my password: {{reset_url}}

            If you didn't request this, you can safely ignore this email — your password will remain unchanged.
            TEXT,
    ],

    'profile_updated' => [
        'label' => 'Profile Update',
        'recipients' => ['user'],
        'variables' => ['user_name', 'site_name'],
    ],

    'newsletter_subscribed' => [
        'label' => 'Newsletter Confirmation/Registration',
        'recipients' => ['user'],
        'variables' => ['subscriber_email', 'site_name'],
    ],

    'contact_form_submitted' => [
        'label' => 'Contact Form',
        'recipients' => ['user', 'admin'],
        'variables' => ['name', 'email', 'subject', 'message', 'site_name'],
    ],

];
