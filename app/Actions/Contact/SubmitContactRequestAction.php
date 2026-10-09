<?php

namespace App\Actions\Contact;

use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\ContactRequest;
use App\Models\Role;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Support\Contact\LeadContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The public Contact Us form's only entry point (ADMIN-010). Sends the
 * existing "contact_form_submitted" Email Template (config/email_templates.php)
 * to the submitter and to every active admin-role user — the same
 * admin-resolution pattern used elsewhere in this codebase (no separate
 * "admin notification email" setting exists anywhere). The submission row
 * is always saved first — a broken SMTP config must never turn a
 * successful submission into a 500 for the visitor, per the platform's
 * save-before-notify rule.
 *
 * notify() is also what Admin → Contact Requests → "Resend emails" calls,
 * for submissions whose emails failed (e.g. a mail-server outage).
 */
class SubmitContactRequestAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, ?string>  $context  already-validated lead context (LeadContext::fromRequest())
     */
    public function handle(array $data, ?string $ipAddress, ?string $userAgent, array $context = []): ContactRequest
    {
        $contactRequest = new ContactRequest;
        $contactRequest->fill($data);
        $contactRequest->ip_address = $ipAddress;
        $contactRequest->user_agent = $userAgent;
        // Set explicitly (not #[Fillable]): never mass-assigned from input.
        foreach (['page_url', 'page_title', 'source', 'cta_label', 'referrer'] as $field) {
            $contactRequest->{$field} = $context[$field] ?? null;
        }
        $contactRequest->save();

        $this->notify($contactRequest);

        return $contactRequest;
    }

    /**
     * Sends the submitter's receipt and/or the admin notifications. A failed
     * email is logged and counted, never thrown.
     *
     * @return array{sent: int, failed: int}
     */
    public function notify(ContactRequest $contactRequest, bool $submitter = true, bool $admins = true): array
    {
        $variables = $this->variables($contactRequest);
        $result = ['sent' => 0, 'failed' => 0];

        if ($submitter) {
            $this->tally($result, $this->notifySubmitter($contactRequest, $variables));
        }

        if ($admins) {
            foreach ($this->notifyAdmins($contactRequest, $variables) as $ok) {
                $this->tally($result, $ok);
            }
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function variables(ContactRequest $contactRequest): array
    {
        return [
            'name' => $contactRequest->name,
            'email' => $contactRequest->email,
            'phone' => $contactRequest->phone ?? '',
            'subject' => $contactRequest->subject ?? '',
            'message' => $contactRequest->message,
            'page_url' => $contactRequest->page_url ?? '',
            'page_title' => $contactRequest->page_title ?? '',
            'lead_source' => LeadContext::sourceLabel($contactRequest->source) ?? '',
            'cta_label' => $contactRequest->cta_label ?? '',
            'referrer' => $contactRequest->referrer ?? '',
        ];
    }

    /**
     * @param  array{sent: int, failed: int}  $result
     */
    private function tally(array &$result, bool $ok): void
    {
        $result[$ok ? 'sent' : 'failed']++;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifySubmitter(ContactRequest $contactRequest, array $variables): bool
    {
        try {
            $this->mailer->send('contact_form_submitted', EmailRecipientType::User, $contactRequest->email, $variables);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Contact request submitter receipt email failed to send', [
                'contact_request_id' => $contactRequest->id,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<bool> one result per admin
     */
    private function notifyAdmins(ContactRequest $contactRequest, array $variables): array
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN_SLUG)->first();

        if ($adminRole === null) {
            return [];
        }

        $results = [];

        foreach ($adminRole->users()->where('status', UserStatus::Active->value)->get() as $admin) {
            try {
                $this->mailer->send('contact_form_submitted', EmailRecipientType::Admin, $admin->email, $variables);
                $results[] = true;
            } catch (Throwable $exception) {
                Log::warning('Contact request admin notification failed to send', [
                    'contact_request_id' => $contactRequest->id,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
                $results[] = false;
            }
        }

        return $results;
    }
}
