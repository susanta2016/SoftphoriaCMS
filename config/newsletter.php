<?php

/*
|--------------------------------------------------------------------------
| Newsletter Marketing module (app/Modules/Newsletter)
|--------------------------------------------------------------------------
|
| `enabled` is the module's one authoritative switch. When false, every
| Newsletter Marketing admin screen 404s and is hidden from navigation, the
| scheduler dispatches nothing, queued campaign jobs exit without sending
| and NewsletterDeliveryService refuses to send. No database setting can
| turn it on — the Newsletter Settings page only holds defaults.
|
| Deliberately NOT gated (client decision, 2026-10-30/31): the subscriber
| list (top-level "Newsletter Subscribers" without groups when off), the
| footer signup + double opt-in confirmation link and the SES/SNS webhook —
| all live before this module existed. Also not gated: the unsubscribe
| link and click redirects in emails that were already sent — a recipient's
| right to unsubscribe can't depend on an admin-side flag, and turning the
| module off must not break links people already have.
|
| Turning the module off never deletes or changes any data. A campaign that
| was scheduled or mid-send stays where it was and is revalidated by
| DispatchDueCampaignsCommand when the module is turned back on.
|
*/

return [

    'enabled' => (bool) env('NEWSLETTER_MARKETING_ENABLED', false),

    /*
    | The Laravel mailer campaigns are sent through (config/mail.php
    | `mailers`). Null = the site's own mail configuration from Website
    | Setup > Settings > Email, the same one transactional email uses —
    | fine for testing, not recommended for bulk marketing. Set to
    | "newsletter" to use the dedicated NEWSLETTER_SMTP_* mailer below
    | (e.g. the Amazon SES SMTP interface), or "ses" once aws/aws-sdk-php is
    | installed.
    */
    'mailer' => env('NEWSLETTER_MAILER'),

    /*
    | Domains campaign sender addresses may use — the domains verified with
    | the delivery provider (e.g. SES verified identities). Comma-separated.
    | Empty = only the domain of the default sender email.
    */
    'allowed_sender_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NEWSLETTER_ALLOWED_SENDER_DOMAINS', '')),
    ))),

    /*
    | Fallback sender, used until Newsletter Settings has its own values.
    */
    'from' => [
        'address' => env('NEWSLETTER_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'name' => env('NEWSLETTER_FROM_NAME', env('MAIL_FROM_NAME')),
    ],

    /*
    | Amazon SES configuration set added to every campaign email
    | (X-SES-CONFIGURATION-SET), so SES publishes Delivery/Bounce/Complaint
    | (and optionally Open/Click) events to the SNS topic behind
    | /webhooks/ses. Empty = header not sent.
    */
    'ses_configuration_set' => env('NEWSLETTER_SES_CONFIGURATION_SET'),

    /*
    | Upper bound on sends per second, whatever Newsletter Settings says —
    | keep at or below the provider's own sending rate (SES "maximum send
    | rate").
    */
    'max_rate_per_second' => (int) env('NEWSLETTER_MAX_RATE_PER_SECOND', 14),

    // Recipients claimed per queued batch job before it re-queues itself.
    'batch_size' => (int) env('NEWSLETTER_BATCH_SIZE', 100),

    // Send attempts per recipient before it is marked Failed.
    'max_attempts' => 3,

    /*
    | A scheduled campaign picked up more than this many hours after its
    | scheduled time (e.g. the module was off) is marked Failed instead of
    | being sent late. A Queued/Sending campaign with no progress for
    | `stalled_after_minutes` is re-dispatched.
    */
    'stale_after_hours' => (int) env('NEWSLETTER_STALE_AFTER_HOURS', 24),
    'stalled_after_minutes' => 15,

    'default_timezone' => 'Asia/Kolkata',

    // Queue the campaign jobs run on (the existing worker listens to "default").
    'queue' => env('NEWSLETTER_QUEUE', 'default'),

];
