<?php

use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * The "contact_form_submitted" Email Template was seeded (2026-08-13)
 * against a "subject" form field that the actual Contact Us form
 * (built 2026-09-02, config/email_templates.php) never collects — it
 * collects phone instead. Only the `available_variables` metadata (the
 * variable list shown in EditEmailTemplate's editor) is corrected here;
 * the admin's already-customized subject/html_body content is left
 * untouched since neither actually references {{subject}}.
 */
return new class extends Migration
{
    public function up(): void
    {
        EmailTemplate::query()
            ->where('notification_key', 'contact_form_submitted')
            ->update(['available_variables' => ['name', 'email', 'phone', 'message', 'site_name']]);
    }

    public function down(): void
    {
        EmailTemplate::query()
            ->where('notification_key', 'contact_form_submitted')
            ->update(['available_variables' => ['name', 'email', 'subject', 'message', 'site_name']]);
    }
};
