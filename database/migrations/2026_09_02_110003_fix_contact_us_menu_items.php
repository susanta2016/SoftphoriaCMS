<?php

use App\Enums\MenuItemDestinationType;
use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * The two pre-existing "Contact Us" nav items (header menu id 5, footer
 * menu id 6) were placeholder Page links pointing at About/Home — there
 * was no real Contact page to point at until now. Repoints both to the
 * new public /contact route (App\Http\Controllers\ContactController).
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuItem::query()
            ->where('label', 'Contact Us')
            ->update([
                'destination_type' => MenuItemDestinationType::Url,
                'page_id' => null,
                'url' => '/contact',
            ]);
    }

    public function down(): void
    {
        // Not reversible — the original (incorrect) page_id values that
        // predate this fix aren't recoverable.
    }
};
