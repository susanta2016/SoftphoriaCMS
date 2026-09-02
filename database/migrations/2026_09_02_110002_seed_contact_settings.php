<?php

use App\Shared\Services\Settings\SettingsRepository;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds the initial Contact Us details (client-provided, 2026-09-02) into
 * the `contact` settings group so the public Contact page has real content
 * from the start, rather than only showing Settings::loadFormState()'s
 * code-level fallback until an admin happens to open Website Setup and
 * click Save. An admin can still edit both values afterward — Settings'
 * Contact tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = app(SettingsRepository::class);

        $settings->set('contact', 'email', 'jacobdiawarii@gmail.com');
        $settings->set('contact', 'address', "1372 Pheasant Chase Circle\nBeecher, IL 60401\nUS");
    }

    public function down(): void
    {
        \App\Models\Setting::query()->forGroup('contact')->delete();
    }
};
