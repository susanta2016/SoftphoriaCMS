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
    ],

    'user_registered' => [
        'label' => 'New Registration / Welcome',
        'recipients' => ['user', 'admin'],
        'variables' => ['user_name', 'user_email', 'site_name'],
    ],

    'pro_member_registered' => [
        'label' => 'Pro Member Registration / Welcome',
        'recipients' => ['user'],
        'variables' => ['user_name', 'user_email', 'site_name'],
    ],

    'password_reset' => [
        'label' => 'Password Reset / Generate New Password',
        'recipients' => ['user'],
        'variables' => ['user_name', 'reset_url', 'site_name'],
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

    'order_confirmation' => [
        'label' => 'Order Confirmation',
        'recipients' => ['user'],
        'variables' => ['user_name', 'order_items', 'order_total', 'account_orders_url', 'site_name'],
    ],

    'guest_download_access' => [
        'label' => 'Guest Download Access',
        'recipients' => ['user'],
        'variables' => ['order_items', 'order_total', 'download_access_url', 'site_name'],
    ],

    'contact_form_submitted' => [
        'label' => 'Contact Form',
        'recipients' => ['user', 'admin'],
        'variables' => ['name', 'email', 'subject', 'message', 'site_name'],
    ],

    'inspirational_resource_submitted' => [
        'label' => 'Inspirational Resource Submission',
        'recipients' => ['admin'],
        'variables' => ['submitter_name', 'submitter_email', 'subject', 'category', 'site_name'],
    ],

    'review_published' => [
        'label' => 'Review Published',
        'recipients' => ['user'],
        'variables' => ['user_name', 'title', 'rating', 'review_url', 'site_name'],
    ],

];
