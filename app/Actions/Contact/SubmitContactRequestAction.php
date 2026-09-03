<?php

namespace App\Actions\Contact;

use App\Enums\EmailRecipientType;
use App\Enums\UserStatus;
use App\Models\ContactRequest;
use App\Models\Role;
use App\Shared\Services\Notifications\TemplatedMailer;
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
 */
class SubmitContactRequestAction
{
    public function __construct(private readonly TemplatedMailer $mailer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $ipAddress, ?string $userAgent): ContactRequest
    {
        $contactRequest = new ContactRequest;
        $contactRequest->fill($data);
        $contactRequest->ip_address = $ipAddress;
        $contactRequest->user_agent = $userAgent;
        $contactRequest->save();

        $variables = [
            'name' => $contactRequest->name,
            'email' => $contactRequest->email,
            'subject' => $contactRequest->subject ?? '',
            'message' => $contactRequest->message,
        ];

        $this->notifySubmitter($contactRequest, $variables);
        $this->notifyAdmins($contactRequest, $variables);

        return $contactRequest;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifySubmitter(ContactRequest $contactRequest, array $variables): void
    {
        try {
            $this->mailer->send('contact_form_submitted', EmailRecipientType::User, $contactRequest->email, $variables);
        } catch (Throwable $exception) {
            Log::warning('Contact request submitter receipt email failed to send', [
                'contact_request_id' => $contactRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function notifyAdmins(ContactRequest $contactRequest, array $variables): void
    {
        $adminRole = Role::query()->where('slug', Role::ADMIN_SLUG)->first();

        if ($adminRole === null) {
            return;
        }

        $admins = $adminRole->users()->where('status', UserStatus::Active->value)->get();

        foreach ($admins as $admin) {
            try {
                $this->mailer->send('contact_form_submitted', EmailRecipientType::Admin, $admin->email, $variables);
            } catch (Throwable $exception) {
                Log::warning('Contact request admin notification failed to send', [
                    'contact_request_id' => $contactRequest->id,
                    'admin_id' => $admin->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
