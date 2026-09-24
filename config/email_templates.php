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
            Hi {{user_name}},

            Thanks for joining {{site_name}}. Please verify your email address to activate your account.

            <a href="{{verification_url}}">Verify My Email</a>

            This link will expire in 24 hours. If you didn't create this account, you can safely ignore this email.
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
        'default_subject' => '{{site_name}} — Reset Your Password',
        'default_html_body' => <<<'HTML'
            Hi {{user_name}},

            We received a request to reset your password on {{site_name}}. Click the link below to choose a new password.

            <a href="{{reset_url}}">Reset My Password</a>

            This link will expire in 60 minutes. If you didn't request this, you can safely ignore this email — your password will remain unchanged.
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            We received a request to reset your password on {{site_name}}. Use the link below to choose a new password.

            Reset my password: {{reset_url}}

            This link will expire in 60 minutes. If you didn't request this, you can safely ignore this email — your password will remain unchanged.
            TEXT,
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
        // Per-recipient content (EmailTemplateSeeder supports a
        // recipient-keyed array here) — the submitter gets a plain
        // acknowledgement, the admin gets the actual submitted details so
        // they can act on it; a single shared body can't serve both.
        'default_subject' => [
            'user' => '{{site_name}} — We Received Your Message',
            'admin' => '[{{site_name}}] New Contact Form Submission',
        ],
        'default_html_body' => [
            'user' => <<<'HTML'
                Hi {{name}},

                Thank you for reaching out to {{site_name}}. We've received your message and will get back to you as soon as we can.

                Here's a copy of what you sent:

                "{{message}}"
                HTML,
            'admin' => <<<'HTML'
                A new contact form submission was received on {{site_name}}.

                Name: {{name}}
                Email: {{email}}
                Phone: {{phone}}

                Message:
                {{message}}
                HTML,
        ],
        'default_text_body' => [
            'user' => <<<'TEXT'
                Hi {{name}},

                Thank you for reaching out to {{site_name}}. We've received your message and will get back to you as soon as we can.

                Here's a copy of what you sent:

                "{{message}}"
                TEXT,
            'admin' => <<<'TEXT'
                A new contact form submission was received on {{site_name}}.

                Name: {{name}}
                Email: {{email}}
                Phone: {{phone}}

                Message:
                {{message}}
                TEXT,
        ],
    ],

    'inspirational_resource_submitted' => [
        'label' => 'Inspirational Resource Submission',
        'recipients' => ['admin'],
        'variables' => ['submitter_name', 'submitter_email', 'subject', 'category', 'site_name'],
        'default_subject' => '[{{site_name}}] New Inspirational Resource Submission',
        'default_html_body' => <<<'HTML'
            A new inspirational resource submission was received on {{site_name}}.

            Submitted by: {{submitter_name}} ({{submitter_email}})
            Category: {{category}}
            Subject: {{subject}}

            Please review it in the admin panel.
            HTML,
        'default_text_body' => <<<'TEXT'
            A new inspirational resource submission was received on {{site_name}}.

            Submitted by: {{submitter_name}} ({{submitter_email}})
            Category: {{category}}
            Subject: {{subject}}

            Please review it in the admin panel.
            TEXT,
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

    // Poetry/Prose (Light Posts) "Submit Your Writing" form — sent by
    // CreatePoetryProseSubmissionAction, same pair as the Inspirational
    // Resources ones above. There is no "published" counterpart: approving
    // a Poetry/Prose submission publishes nothing.
    'poetry_prose_submitted' => [
        'label' => 'Poetry/Prose Writing Submission',
        'recipients' => ['admin'],
        'variables' => ['submitter_name', 'submitter_email', 'subject', 'category', 'site_name'],
        'default_subject' => '[{{site_name}}] New Poetry/Prose Writing Submission',
        'default_html_body' => <<<'HTML'
            A new Poetry/Prose writing submission was received on {{site_name}}.

            Submitted by: {{submitter_name}} ({{submitter_email}})
            Category: {{category}}
            Subject: {{subject}}

            Please review it in the admin panel under Poetry/Prose → Submissions.
            HTML,
        'default_text_body' => <<<'TEXT'
            A new Poetry/Prose writing submission was received on {{site_name}}.

            Submitted by: {{submitter_name}} ({{submitter_email}})
            Category: {{category}}
            Subject: {{subject}}

            Please review it in the admin panel under Poetry/Prose → Submissions.
            TEXT,
    ],

    'poetry_prose_submission_pending' => [
        'label' => 'Poetry/Prose Writing Received (Pending Review)',
        'recipients' => ['user'],
        'variables' => ['submitter_name', 'subject', 'site_name'],
        'default_subject' => '{{site_name}} — We Received Your Writing',
        'default_html_body' => <<<'HTML'
            Hi {{submitter_name}},

            Thank you for sharing "{{subject}}" with {{site_name}}. Your writing has been received and is currently awaiting review.
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{submitter_name}},

            Thank you for sharing "{{subject}}" with {{site_name}}. Your writing has been received and is currently awaiting review.
            TEXT,
    ],

    // Sent only when an admin approves a Poetry/Prose submission
    // (ChangePoetryProseSubmissionStatusAction) — the counterpart of
    // inspirational_resource_published, minus a link: approving a
    // Poetry/Prose submission publishes no page of its own.
    'poetry_prose_submission_approved' => [
        'label' => 'Poetry/Prose Writing Approved',
        'recipients' => ['user'],
        'variables' => ['submitter_name', 'subject', 'site_name'],
        'default_subject' => '{{site_name}} — Your Writing Has Been Approved',
        'default_html_body' => <<<'HTML'
            Hi {{submitter_name}},

            Good news — "{{subject}}" has been reviewed and approved by the {{site_name}} team.

            Thank you for sharing your words with us.
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{submitter_name}},

            Good news — "{{subject}}" has been reviewed and approved by the {{site_name}} team.

            Thank you for sharing your words with us.
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
        'default_subject' => '{{site_name}} — Your Review Has Been Published',
        'default_html_body' => <<<'HTML'
            Hi {{user_name}},

            Good news — your review of "{{title}}" has been published on {{site_name}}.

            <a href="{{review_url}}">View it now</a>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            Good news — your review of "{{title}}" has been published on {{site_name}}.

            View it now: {{review_url}}
            TEXT,
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

    // Sent from App\Actions\Review\FlagReviewAction whenever a member 🚩
    // reports a comment for the first time (a repeat report from the same
    // user is a silent no-op, never re-sent) — to both the comment's author
    // and every admin-role user, per-recipient content like
    // contact_form_submitted above (the author gets a neutral notice, the
    // admin gets the actual report details so they can act on it).
    'review_comment_flagged' => [
        'label' => 'Comment Flagged',
        'recipients' => ['user', 'admin'],
        'variables' => ['commenter_name', 'commenter_email', 'comment_content', 'content_title', 'content_url', 'flagged_by_name', 'flagged_by_email', 'site_name'],
        'default_subject' => [
            'user' => '{{site_name}} — Your Comment Has Been Reported for Review',
            'admin' => '[{{site_name}}] A Comment Was Reported',
        ],
        'default_html_body' => [
            'user' => <<<'HTML'
                Hi {{commenter_name}},

                A member has reported your comment on "{{content_title}}" for review. Our team will take a look and follow up if any action is needed.

                Your comment:

                "{{comment_content}}"
                HTML,
            'admin' => <<<'HTML'
                A comment was reported on {{site_name}}.

                Reported by: {{flagged_by_name}} ({{flagged_by_email}})
                Comment by: {{commenter_name}} ({{commenter_email}})
                On: {{content_title}}

                Comment:
                "{{comment_content}}"

                Please review it in the admin panel under Community &rarr; Admin Reviews.
                HTML,
        ],
        'default_text_body' => [
            'user' => <<<'TEXT'
                Hi {{commenter_name}},

                A member has reported your comment on "{{content_title}}" for review. Our team will take a look and follow up if any action is needed.

                Your comment:

                "{{comment_content}}"
                TEXT,
            'admin' => <<<'TEXT'
                A comment was reported on {{site_name}}.

                Reported by: {{flagged_by_name}} ({{flagged_by_email}})
                Comment by: {{commenter_name}} ({{commenter_email}})
                On: {{content_title}}

                Comment:
                "{{comment_content}}"

                Please review it in the admin panel under Community -> Admin Reviews.
                TEXT,
        ],
    ],

    // Sent from App\Actions\GratitudeJournal\FlagGratitudeJournalEntryAction
    // whenever a member 🚩 reports a Gratitude Journal shared-feed entry
    // for the first time (a repeat report from the same user is a silent
    // no-op, never re-sent) — to both the entry's author and every
    // admin-role user, mirroring review_comment_flagged above exactly
    // (Gratitude Journal has no separate comment row to flag, so the entry
    // itself is the reported content).
    'gratitude_journal_entry_flagged' => [
        'label' => 'Gratitude Journal Entry Flagged',
        'recipients' => ['user', 'admin'],
        'variables' => ['entry_author_name', 'entry_author_email', 'entry_content', 'content_url', 'flagged_by_name', 'flagged_by_email', 'site_name'],
        'default_subject' => [
            'user' => '{{site_name}} — Your Gratitude Journal Entry Has Been Reported for Review',
            'admin' => '[{{site_name}}] A Gratitude Journal Entry Was Reported',
        ],
        'default_html_body' => [
            'user' => <<<'HTML'
                Hi {{entry_author_name}},

                A member has reported your Gratitude Journal entry on the shared feed for review. Our team will take a look and follow up if any action is needed.

                Your entry:

                "{{entry_content}}"
                HTML,
            'admin' => <<<'HTML'
                A Gratitude Journal entry was reported on {{site_name}}.

                Reported by: {{flagged_by_name}} ({{flagged_by_email}})
                Entry by: {{entry_author_name}} ({{entry_author_email}})

                Entry:
                "{{entry_content}}"

                Please review it in the admin panel under Community &rarr; Flagged Journal Entries.
                HTML,
        ],
        'default_text_body' => [
            'user' => <<<'TEXT'
                Hi {{entry_author_name}},

                A member has reported your Gratitude Journal entry on the shared feed for review. Our team will take a look and follow up if any action is needed.

                Your entry:

                "{{entry_content}}"
                TEXT,
            'admin' => <<<'TEXT'
                A Gratitude Journal entry was reported on {{site_name}}.

                Reported by: {{flagged_by_name}} ({{flagged_by_email}})
                Entry by: {{entry_author_name}} ({{entry_author_email}})

                Entry:
                "{{entry_content}}"

                Please review it in the admin panel under Community -> Flagged Journal Entries.
                TEXT,
        ],
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
        'default_subject' => '{{site_name}} — A Gentle Reminder for Your Gratitude Journal',
        'default_html_body' => <<<'HTML'
            Hi {{user_name}},

            This is your {{frequency_label}} reminder to take a moment for gratitude. Even a few words can help you reflect on the good in your day.

            <a href="{{journal_url}}">Write in your Gratitude Journal</a>
            HTML,
        'default_text_body' => <<<'TEXT'
            Hi {{user_name}},

            This is your {{frequency_label}} reminder to take a moment for gratitude. Even a few words can help you reflect on the good in your day.

            Write in your Gratitude Journal: {{journal_url}}
            TEXT,
    ],

];
