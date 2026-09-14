# SES / SNS Bounce & Complaint Setup

Amazon SES is already the production mailer (`MAIL_MAILER=ses`,
`config/services.php` `ses`). This documents the one-time AWS console setup
required for the bounce/complaint feedback loop to reach
`App\Http\Controllers\Newsletter\SesWebhookController` (`POST /webhooks/ses`).

No AWS console configuration has been performed as part of this change — the
application code (webhook, signature verification, suppression) is ready to
receive events, but SES/SNS must still be wired up per the steps below before
any real event will arrive.

## Steps

1. **Create an SNS topic** in the same region as SES sending (production
   currently uses `us-east-2` — see `AWS_DEFAULT_REGION`). Standard topic,
   e.g. `softphoria-ses-events`.

2. **Configure an SES event destination** for the sending identity (domain or
   address): SES console → **Configuration sets** (or the identity's
   **Notifications** tab) → add a destination publishing to the SNS topic
   from step 1, for the **Bounce** and **Complaint** event types. Delivery
   events are not required.

3. **Subscribe the webhook to the topic**: SNS console → the topic → **Create
   subscription** → protocol `HTTPS`, endpoint
   `https://<production-domain>/webhooks/ses`. The endpoint must be reachable
   over HTTPS from the public internet — no new port or infrastructure change
   is needed, it's served by the existing nginx/app containers.

4. **Confirm the subscription**: SNS sends a `SubscriptionConfirmation`
   message to the endpoint immediately after step 3.
   `SesWebhookController` verifies its signature and automatically requests
   the `SubscribeURL` itself — no manual "click to confirm" step is needed,
   but confirm in the SNS console that the subscription status changes from
   "Pending confirmation" to "Confirmed".

5. **(Recommended) Set `SES_SNS_TOPIC_ARN`** in production's `.env` to the
   topic's ARN from step 1. The webhook always verifies the AWS signature
   first; this additional check rejects a genuine-but-wrong SNS topic (signing
   proves a message came from *some* AWS account's SNS topic, not
   specifically this one).

6. **Test a bounce**: send to SES's
   [mailbox simulator](https://docs.aws.amazon.com/ses/latest/dg/send-an-email-from-console.html)
   bounce address (`bounce@simulator.amazonses.com`) via the newsletter
   confirmation flow, or trigger one from the SES console's test event
   feature for the configuration set. Confirm in the Newsletter Subscribers
   admin resource that the matching row moves to **Bounced** with
   `ses_event_at`/`ses_event_type` populated.

7. **Test a complaint**: same as above using the complaint simulator address
   (`complaint@simulator.amazonses.com`). Confirm the row moves to
   **Complained**.

8. **Confirm suppression**: attempt to resubmit the same address through the
   public newsletter form — it must be refused (`NewsletterSubscriptionOutcome::Suppressed`),
   not silently resubscribed.

9. **Confirm newsletter sends exclude suppressed subscribers**: any future
   newsletter-sending code must query through `NewsletterSubscriber::sendable()`
   (`status = subscribed` only) — see `tests/Feature/Newsletter/NewsletterSendableScopeTest.php`
   for the regression test covering this.

## Notes

- Signature verification uses AWS's own `aws/aws-php-sns-message-validator`
  package (openssl-based, checks the signing certificate's domain and the
  RSA signature) — not a custom implementation.
- Transient/temporary bounces (a full inbox, a greylisting delay, etc.) never
  suppress an address — only a `Permanent` SES bounce type does.
- No SNS signing secret or webhook secret is stored in `.env` — AWS's
  signature scheme uses a public certificate SNS itself serves over HTTPS,
  fetched and verified per request.
