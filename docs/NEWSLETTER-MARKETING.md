# Newsletter Marketing module

`app/Modules/Newsletter` — subscriber groups, reusable HTML email templates,
campaigns, scheduled background sending, a global suppression list and
delivery reports, in **Admin > Newsletter Marketing**.

Branch: `feature/newsletter-marketing` (cut from `JacobCMS`). Off by default.

---

## 1. What the switch controls

`NEWSLETTER_MARKETING_ENABLED` (read only through `config('newsletter.enabled')`)
is the module's single on/off switch. No database setting can turn it on.

| | Off (default) | On |
|---|---|---|
| Newsletter Marketing menu | hidden | shown to admins |
| `admin/newsletter*` URLs | **404** (panel middleware + each page's `canAccess()`) | normal admin auth |
| Scheduler (`newsletter:dispatch-due`) | runs, does nothing | queues due campaigns, restarts stalled ones |
| Queued campaign jobs | exit without sending | send |
| `NewsletterDeliveryService` / `NewsletterMailer` | throw, never send | send |
| Data | untouched | untouched |

**Always on, by decision (2026-10-30/31):** the subscriber list itself —
with the module off it is the top-level **Newsletter Subscribers** item at
its original URL `/admin/newsletter-subscribers` (add, edit, import,
unsubscribe/resubscribe, suppress, delete; no groups); with it on, the same
page sits under Newsletter Marketing as **Subscribers**, with groups. Also
the existing footer signup + double opt-in confirmation link, the SES/SNS webhook (`/webhooks/ses`), the
unsubscribe link in sent emails and click redirects in sent emails. An
admin-side switch must never stop someone unsubscribing from email they
already received, or break links they already have. Tracking records
nothing while the module is off.

Turning the module off mid-send: jobs stop within a few recipients; the
campaign stays *Sending*. Turning it back on: the scheduler revalidates it
(sender still verified, content snapshot present, not older than
`NEWSLETTER_STALE_AFTER_HOURS`, default 24) and resumes it, or marks it
*Failed* with the reason. A scheduled campaign whose time passed while the
module was off is treated the same way — never sent late beyond that window.

## 2. Data model

| Table | Purpose |
|---|---|
| `newsletter_subscribers` | **existing** table, extended with `first_name`, `last_name`, `source`, `profile_fields` (backfilled from `name`) |
| `newsletter_groups`, `newsletter_group_subscriber` | groups, many-to-many membership |
| `newsletter_suppressions` | global suppression list, keyed by email (survives subscriber deletion) |
| `newsletter_templates` | reusable HTML/CSS templates |
| `newsletter_campaigns`, `newsletter_campaign_group` | campaigns + include/exclude group selection, content snapshot, schedule (UTC) |
| `newsletter_campaign_recipients` | recipient snapshot + per-recipient delivery state; unique (campaign, email) |
| `newsletter_delivery_logs` | every send attempt and provider response |
| `newsletter_events` | delivery/bounce/complaint/open/click/unsubscribe; unique `dedupe_key` |

Subscriber statuses keep the values the footer signup already writes —
`pending`, `subscribed` (shown as **Active**), `unsubscribed`, `bounced`,
`complained` — plus `suppressed` (admin) and `invalid`. Only `subscribed`
and not on the suppression list is ever sendable
(`NewsletterSubscriber::scopeSendable()`).

## 3. How a send works

1. **Schedule / Send now** (campaign page): readiness checks, then the
   **content snapshot** — rendered HTML + text, tracked-link map, group
   names. Later template edits never change it.
2. **Dispatcher** (`newsletter:dispatch-due`, every minute,
   `withoutOverlapping`): atomically moves due campaigns *Scheduled → Queued*
   (only one process can win) and dispatches `StartCampaignJob`.
3. **StartCampaignJob**: takes the **recipient snapshot** in 1,000-row chunks
   (included groups − excluded groups, sendable only, one row per address),
   *Queued → Sending*, starts the batch chain.
4. **SendCampaignBatchJob**: claims up to `NEWSLETTER_BATCH_SIZE` pending
   recipients, sends at ≤ the configured rate, re-queues itself. One live
   chain per campaign (`send_chain`).
5. **Per recipient** (`NewsletterDeliveryService`): atomic claim
   (*pending → sending*), **consent re-check** (still Active, not suppressed,
   same address), personalize, send, log. Provider errors retry up to 3
   attempts with a 60 s backoff, then *Failed*.
6. Finish: *Sent*, *Partially Sent* (some failed) or *Failed* (all failed).

Duplicate protection: compare-and-set status transitions, the per-recipient
claim, unique (campaign, email), and the chain id. A recipient left at
*Sending* by a dead worker is marked *Failed* ("outcome unknown"), never
re-sent — a gap is better than a duplicate.

**Sent ≠ delivered.** *Sent (submitted)* means the provider accepted it.
*Delivered* only comes from provider delivery events (SES). Opens are
approximate (Apple Mail Privacy Protection and image proxies inflate them;
blocked images hide them).

## 4. Templates and safety

- Placeholders are a fixed allowlist (`TemplateVariables`):
  `{{ subscriber.first_name | "there" }}`, `{{ campaign_content }}`,
  `{{ campaign.cta_button }}`, `{{ unsubscribe_url }}`, `{{ footer }}`, …
  Anything else is rejected on save. Substitution is plain string lookup —
  never Blade, PHP or eval.
- Subscriber/campaign values are HTML-escaped. Template and campaign HTML is
  sanitized at render (`EmailHtmlSanitizer`): scripts, iframes, forms,
  external stylesheets, `on*` handlers, `javascript:`/`data:` URLs removed.
- CSS is inlined (`tijsverkoyen/css-to-inline-styles`, already a Laravel
  dependency); `@media` rules stay in `<style>` for mobile.
- Every template must contain `{{ unsubscribe_url }}` or `{{ footer }}`;
  if one somehow doesn't, the default footer is appended anyway.
- Admin previews render in a sandboxed `<iframe sandbox="">`.
- CTA links must be http(s). The click redirect only goes to URLs stored in
  that campaign's own link map — no open redirect.

## 5. Environment variables

```dotenv
NEWSLETTER_MARKETING_ENABLED=false          # the switch
NEWSLETTER_MAILER=                          # empty = site Settings > Email SMTP; "newsletter" = below; "ses" needs aws/aws-sdk-php
NEWSLETTER_SMTP_HOST=                       # e.g. email-smtp.us-east-1.amazonaws.com
NEWSLETTER_SMTP_PORT=587
NEWSLETTER_SMTP_USERNAME=                   # SES SMTP credentials (not IAM keys)
NEWSLETTER_SMTP_PASSWORD=
NEWSLETTER_FROM_ADDRESS=                    # fallback until Newsletter Settings is saved
NEWSLETTER_FROM_NAME=
NEWSLETTER_ALLOWED_SENDER_DOMAINS=          # comma-separated, the SES-verified domains
NEWSLETTER_SES_CONFIGURATION_SET=           # SES configuration set publishing events to SNS
NEWSLETTER_MAX_RATE_PER_SECOND=14           # ceiling; <= SES account max send rate
# optional: NEWSLETTER_BATCH_SIZE=100, NEWSLETTER_STALE_AFTER_HOURS=24, NEWSLETTER_QUEUE=default
SES_SNS_TOPIC_ARN=                          # existing; pins the webhook to your topic
```

Credentials live only in the server `.env`, never in the database.

## 6. Deployment

1. Merge the branch (when approved) and deploy as usual. Migrations run with
   the normal `php artisan migrate --force` (they are additive; the only
   change to an existing table adds four nullable columns and backfills
   names).
2. **Queue**: nothing new — jobs use the existing Redis `default` queue and
   the existing `queue` container (`queue:work --tries=3`). Make sure it is
   running.
3. **Scheduler**: nothing new — the existing `scheduler` container
   (`schedule:work`) picks up `newsletter:dispatch-due` every minute.
4. Leave `NEWSLETTER_MARKETING_ENABLED=false`. Config is cached in prod, so
   after any `.env` change: `php artisan config:clear && php artisan config:cache`
   (or the existing recreate procedure — remember nginx after recreating
   `app`).

### Before turning sending on in production

**Amazon SES**
- Verify the sending **domain** in SES (not only an address) in the region
  you send from, and request **production access** (out of the sandbox).
- **SPF**: include SES in the domain's SPF (`include:amazonses.com`), or use
  a custom MAIL FROM domain with its own SPF/MX.
- **DKIM**: enable Easy DKIM; publish the three CNAMEs.
- **DMARC**: publish `_dmarc` (start with `p=none; rua=mailto:…`, tighten
  later). Gmail/Yahoo require DMARC for bulk senders.
- Create **SMTP credentials** for `NEWSLETTER_SMTP_*`.
- Create a **configuration set** with an event destination → the SNS topic
  already used by `/webhooks/ses` (events: Send, Delivery, Bounce,
  Complaint, Reject, DeliveryDelay, Rendering Failure; Open/Click only if
  you want SES-side tracking). Put its name in
  `NEWSLETTER_SES_CONFIGURATION_SET`. See `docs/SES-SNS-SETUP.md` for the
  SNS subscription itself.
- Set `NEWSLETTER_MAX_RATE_PER_SECOND` to at most the account's max send rate.

**Webhook reachability**: `/webhooks/ses` is in the `web` middleware group,
so if the Beta Access Gate (`BETA_ENABLED`) is on, SNS requests are
redirected to the password page. Check before relying on delivery reports.
(The module's own unsubscribe/tracking routes are deliberately outside that
group.)

### Activation

1. Set `NEWSLETTER_MARKETING_ENABLED=true`, the `NEWSLETTER_*` provider
   variables, refresh the config cache.
2. Admin > Newsletter Marketing > **Newsletter Settings**: sender, postal
   address (shown in the footer), timezone (default Asia/Kolkata), rate,
   test recipient, tracking defaults. Save.
3. Create groups, add/import subscribers (consent required), create a
   template (starts from a responsive layout).
4. Create a campaign → Preview (desktop/mobile, sample or a real
   subscriber) → **Send test email** to yourself → check subject, preheader,
   links, unsubscribe link, sender.
5. Send a first real campaign to a small internal group; confirm in the
   report that *Delivered* rises (proves SES events arrive), then click the
   unsubscribe link and check the subscriber became Unsubscribed.

### Deactivation

Set `NEWSLETTER_MARKETING_ENABLED=false` and refresh the config cache.
Nothing is deleted; scheduled campaigns wait; an in-flight one pauses and is
revalidated when the module comes back.

## 7. Testing procedure (local)

```bash
docker exec softphoria_app php artisan test tests/Feature/NewsletterMarketing
docker exec softphoria_app php artisan test tests/Feature/Newsletter
```

Run folders separately — never the whole suite in one process (it
exhausts memory in the local Docker setup).

Manual: set the flag in `.env`, `NEWSLETTER_MAILER=log` (emails land in
`storage/logs/laravel.log`), run `php artisan queue:work` and
`php artisan schedule:work` (or `newsletter:dispatch-due` by hand).

## 8. Known limitations

- Sender-domain verification is declared by `NEWSLETTER_ALLOWED_SENDER_DOMAINS`;
  the app cannot query SES for verified identities without the AWS SDK.
- The SES *API* mailer needs `composer require aws/aws-sdk-php`; the SES
  SMTP interface works without it.
- Delivered/bounce/complaint figures need SES event publishing; with another
  provider only Sent/Failed are known (add a webhook adapter for it).
- The recipient list is fixed when sending starts; people who join a group
  after that don't receive that campaign (by design).
- Preview/test use the saved template — save before previewing edits.
- Campaign content is edited in the rich editor; layouts belong in the
  template HTML.
- The CSV import runs in the request (streamed, max 20,000 rows per file).
- Open tracking is approximate; both tracking options are off by default.
