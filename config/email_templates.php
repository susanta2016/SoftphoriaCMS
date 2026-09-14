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

    // Sent from CreatesLightPostOnRegistration (App\Actions\Registration\
    // Concerns) only when a registrant chose "Share My Light" and their
    // post was actually created — never on "Share Another Time"/a blank
    // message, and never for a Gratitude Journal entry (see
    // gratitude_journal_submitted below).
    'light_post_submitted' => [
        'label' => 'Light Post Shared',
        'recipients' => ['user'],
        'variables' => ['user_name', 'light_post_url', 'site_name'],
        'default_subject' => '{{site_name}} — Your Light Post Has Been Shared',
        'default_html_body' => <<<'HTML'
            Hi {{user_name}},

            Thank you for sharing your light. Your Light Post has been posted successfully and is now live on {{site_name}}.

            <a href="{{light_post_url}}">View your Light Post</a>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            Thank you for sharing your light. Your Light Post has been posted successfully and is now live on {{site_name}}.

            View your Light Post: {{light_post_url}}
            TEXT,
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

    // Double opt-in (docs/SES-SNS-SETUP.md): sent from
    // SubscribeToNewsletterAction on every new/re-subscribing signup, before
    // the subscriber is actually subscribed. 'newsletter_subscribed' below
    // is sent only once ConfirmNewsletterSubscriptionAction verifies the
    // link this email contains — never at signup time.
    'newsletter_confirmation' => [
        'label' => 'Newsletter Confirmation (Double Opt-In)',
        'recipients' => ['user'],
        'variables' => ['confirmation_url', 'site_name'],
    ],

    'newsletter_subscribed' => [
        'label' => 'Newsletter Subscribed',
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
        'variables' => ['name', 'email', 'phone', 'message', 'site_name'],
    ],

    'inspirational_resource_submitted' => [
        'label' => 'Inspirational Resource Submission',
        'recipients' => ['admin'],
        'variables' => ['submitter_name', 'submitter_email', 'subject', 'category', 'site_name'],
    ],

    // Sent to the submitter alongside (never instead of)
    // inspirational_resource_submitted above — CreateResourceSubmissionAction
    // sends both on every submission. Deliberately says "pending review",
    // never "published" — see inspirational_resource_published below for
    // the only email that says that.
    'inspirational_resource_pending' => [
        'label' => 'Inspirational Resource Received (Pending Review)',
        'recipients' => ['user'],
        'variables' => ['submitter_name', 'subject', 'site_name'],
        'default_subject' => '{{site_name}} — We Received Your Submission',
        'default_html_body' => <<<'HTML'
            Hi {{submitter_name}},

            Thank you for submitting "{{subject}}" to {{site_name}}. Your submission has been received and is currently awaiting review.

            We'll let you know as soon as it has been reviewed.
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{submitter_name}},

            Thank you for submitting "{{subject}}" to {{site_name}}. Your submission has been received and is currently awaiting review.

            We'll let you know as soon as it has been reviewed.
            TEXT,
    ],

    // Sent only from ApproveResourceSubmissionAction, the single exclusive
    // writer of ResourceSubmissionStatus::Approved — never sent for
    // Submitted/InReview.
    'inspirational_resource_published' => [
        'label' => 'Inspirational Resource Published',
        'recipients' => ['user'],
        'variables' => ['submitter_name', 'subject', 'resource_url', 'site_name'],
        'default_subject' => '{{site_name}} — Your Submission Has Been Published',
        'default_html_body' => <<<'HTML'
            Hi {{submitter_name}},

            Good news — "{{subject}}" has been approved and is now published on {{site_name}}.

            <a href="{{resource_url}}">View your published submission</a>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{submitter_name}},

            Good news — "{{subject}}" has been approved and is now published on {{site_name}}.

            View your published submission: {{resource_url}}
            TEXT,
    ],

    'review_published' => [
        'label' => 'Review Published',
        'recipients' => ['user'],
        // 'rating' dropped 2026-09-02 — the public form no longer collects
        // one (see App\Actions\Review\SubmitReviewAction's own docblock).
        // Confirmed safe: no EmailTemplate row existed yet for this key in
        // this environment, so no admin-customized copy referenced it.
        'variables' => ['user_name', 'title', 'review_url', 'site_name'],
    ],

    // Sent from CreateGratitudeJournalEntryAction on every successful save,
    // regardless of visibility (Public or Private) — an acknowledgement,
    // never the journal content itself. Distinct from
    // gratitude_journal_reminder below (a scheduled nudge to write an
    // entry, not a response to one).
    'gratitude_journal_submitted' => [
        'label' => 'Gratitude Journal Entry Submitted',
        'recipients' => ['user'],
        'variables' => ['user_name', 'visibility_label', 'journal_url', 'site_name'],
        'default_subject' => '{{site_name}} — Your Gratitude Journal Entry Was Saved',
        'default_html_body' => <<<'HTML'
            Hi {{user_name}},

            Thank you for taking a moment to reflect. Your Gratitude Journal entry has been saved successfully as {{visibility_label}}.

            <a href="{{journal_url}}">View your Gratitude Journal</a>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            Thank you for taking a moment to reflect. Your Gratitude Journal entry has been saved successfully as {{visibility_label}}.

            View your Gratitude Journal: {{journal_url}}
            TEXT,
    ],

    'gratitude_journal_reminder' => [
        'label' => 'Gratitude Journal Reminder',
        'recipients' => ['user'],
        // Sent by SendGratitudeJournalRemindersCommand on the Daily/Weekly
        // cadence a member chose in their Gratitude Journal preferences
        // (never to a member who chose None) — see that command's own
        // docblock and App\Actions\GratitudeJournal\
        // UpdateGratitudeReminderFrequencyAction.
        'variables' => ['user_name', 'journal_url', 'frequency_label', 'site_name'],
    ],

];
