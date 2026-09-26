<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Analytics & Tracking makes the legal texts conditional:
 *
 * - published Cookie / Privacy Policy pages get the reworded sentences from
 *   database/seeders/data/legal-analytics-wording.php (they no longer claim
 *   "no analytics", and point to the automatic "Analytics and marketing
 *   tools" section), only where the old sentence is still word for word;
 * - saved cookie-banner texts still equal to the previous defaults get the
 *   new, neutral defaults from config/cookies_policy.php. Custom text is
 *   left alone.
 *
 * Plain query-builder code on purpose.
 */
return new class extends Migration
{
    /** The previous config/cookies_policy.php defaults, verbatim. */
    private const PREVIOUS_COOKIE_COPY = [
        'banner_description' => 'We use only the cookies needed to run this website securely and remember your choices. We do not use advertising or tracking cookies. You can review your preferences at any time.',
        'privacy_description' => 'Cookies are small text files stored on your device when you visit a website. We keep them to a minimum: this website uses only the cookies it needs to work securely (for example, to keep you signed in and to protect our forms) and to remember your cookie choices.
        
        We will never set optional cookies without your consent. You can change your preferences below, and you can delete cookies at any time in your browser settings — but blocking strictly necessary cookies may stop parts of the website, such as forms and signing in, from working.',
        'functionality_description' => 'Functionality cookies remember choices you make to give you a more personalised experience.
        
        We do not currently use any functionality cookies beyond those that are strictly necessary. If we introduce them, we will ask for your consent first.',
        'tracking_description' => 'Tracking (analytics) cookies measure how visitors use a website, such as which pages are visited and for how long.
        
        We do not currently use any tracking or analytics cookies. If we introduce them, they will only be set with your consent.',
        'targeting_description' => 'Targeting and advertising cookies are used by advertising networks to show ads based on your browsing habits.
        
        We do not use any targeting or advertising cookies, and we do not allow advertising networks to place cookies on this website.',
    ];

    public function up(): void
    {
        $this->swapLegal(false);

        $new = config('cookies_policy');

        foreach (self::PREVIOUS_COOKIE_COPY as $key => $old) {
            DB::table('settings')
                ->where('group', 'cookies')
                ->where('key', $key)
                ->where('value', $old)
                ->update(['value' => $new[$key] ?? $old, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $this->swapLegal(true);
    }

    private function swapLegal(bool $reverse): void
    {
        foreach (require database_path('seeders/data/legal-analytics-wording.php') as $slug => $pairs) {
            $pageId = DB::table('pages')->where('slug', $slug)->value('id');

            if (! $pageId) {
                continue;
            }

            foreach (DB::table('page_sections')->where('page_id', $pageId)->get() as $section) {
                $content = json_decode($section->content_json ?? '[]', true) ?: [];
                $body = $content['body'] ?? null;

                if (! is_string($body)) {
                    continue;
                }

                foreach ($pairs as [$old, $new]) {
                    [$from, $to] = $reverse ? [$new, $old] : [$old, $new];

                    if (str_contains($body, $from) && ! str_contains($body, $to)) {
                        $body = str_replace($from, $to, $body);
                    }
                }

                if ($body !== $content['body']) {
                    $content['body'] = $body;
                    DB::table('page_sections')->where('id', $section->id)->update(['content_json' => json_encode($content)]);
                }
            }
        }
    }
};
