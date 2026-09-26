<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legal pages go live (content: database/seeders/LegalPagesSeeder.php):
 *
 * - the footer's "Terms of Service" link ("#") now points at
 *   /terms-of-service, and a "Cookie Policy" link is added to the Footer
 *   Legal Links menu after it;
 * - cookie banner texts an admin saved while they still held the old
 *   default wording (which claimed tracking and targeted ads the site does
 *   not use) are replaced with the new, accurate defaults from
 *   config/cookies_policy.php. Custom wording is left alone.
 *
 * Plain query-builder code on purpose.
 */
return new class extends Migration
{
    /** The previous config/cookies_policy.php defaults, verbatim. */
    private const OLD_COOKIE_COPY = [
        'banner_title' => 'We use cookies',
        'banner_description' => 'We use cookies and other tracking technologies to improve your browsing experience on our website, to show you personalized content and targeted ads, to analyze our website traffic, and to understand where our visitors are coming from.',
        'privacy_title' => 'Your privacy is important to us',
        'privacy_description' => 'Cookies are very small text files that are stored on your computer when you visit a website. We use cookies for a variety of purposes and to enhance your online experience on our website (for example, to remember your account login details).
        
        You can change your preferences and decline certain types of cookies to be stored on your computer while browsing our website. You can also remove any cookies already stored on your computer, but keep in mind that deleting cookies may prevent you from using parts of our website.',
        'necessary_title' => 'Strictly necessary cookies',
        'necessary_description' => 'These cookies are essential to provide you with services available through our website and to enable you to use certain features of our website.
        
        Without these cookies, we cannot provide you certain services on our website.',
        'functionality_title' => 'Functionality cookies',
        'functionality_description' => 'These cookies are used to provide you with a more personalized experience on our website and to remember choices you make when you use our website.
        
        For example, we may use functionality cookies to remember your language preferences or remember your login details.',
        'tracking_title' => 'Tracking cookies',
        'tracking_description' => 'These cookies are used to collect information to analyze the traffic to our website and how visitors are using our website.
        
        For example, these cookies may track things such as how long you spend on the website or the pages you visit which helps us to understand how we can improve our website for you.
        
        The information collected through these tracking and performance cookies do not identify any individual visitor.',
        'targeting_title' => 'Targeting and advertising cookies',
        'targeting_description' => 'These cookies are used to show advertising that is likely to be of interest to you based on your browsing habits.
        
        These cookies, as served by our content and/or advertising providers, may combine information they collected from our website with other information they have independently collected relating to your web browser\'s activities across their network of websites.
        
        If you choose to remove or disable these targeting or advertising cookies, you will still see adverts but they may not be relevant to you.',
        'more_info_title' => 'More information',
        'more_info_description' => 'For any queries in relation to our policy on cookies and your choices, please contact us.',
    ];

    public function up(): void
    {
        DB::table('menu_items')->where('label', 'Terms of Service')->where('url', '#')->update(['url' => '/terms-of-service']);

        $legalMenuId = DB::table('menus')->where('slug', 'footer-legal')->value('id');

        if ($legalMenuId && ! DB::table('menu_items')->where('menu_id', $legalMenuId)->where('url', '/cookie-policy')->exists()) {
            $termsOrder = DB::table('menu_items')->where('menu_id', $legalMenuId)->where('url', '/terms-of-service')->value('sort_order');
            $position = $termsOrder !== null ? $termsOrder + 1 : (int) DB::table('menu_items')->where('menu_id', $legalMenuId)->max('sort_order') + 1;

            DB::table('menu_items')->where('menu_id', $legalMenuId)->whereNull('parent_id')->where('sort_order', '>=', $position)->increment('sort_order');
            DB::table('menu_items')->insert([
                'menu_id' => $legalMenuId,
                'parent_id' => null,
                'label' => 'Cookie Policy',
                'destination_type' => 'url',
                'url' => '/cookie-policy',
                'sort_order' => $position,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $new = config('cookies_policy');

        foreach (self::OLD_COOKIE_COPY as $key => $old) {
            DB::table('settings')
                ->where('group', 'cookies')
                ->where('key', $key)
                ->where('value', $old)
                ->update(['value' => $new[$key] ?? $old, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->where('label', 'Terms of Service')->where('url', '/terms-of-service')->update(['url' => '#']);
        DB::table('menu_items')->where('label', 'Cookie Policy')->where('url', '/cookie-policy')->delete();
    }
};
