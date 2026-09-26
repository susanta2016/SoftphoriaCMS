<?php

/**
 * Default copy for the Cookies Policy banner and Preferences Center
 * (docs/Cookies Policy popup.docx layout). Both App\Filament\Pages\CookiesPolicy
 * (admin form defaults) and the public x-site.cookie-consent component
 * (fallback when an admin has never saved the `cookies` settings group) read
 * from here, so the banner is fully worded out of the box.
 *
 * The wording describes what the site actually does: only strictly
 * necessary cookies are used today, and the optional categories are
 * described as not currently in use (see resources/legal/cookie-policy.html).
 * Update both if analytics or advertising cookies are ever added.
 */
return [
    'enabled' => true,

    'banner_title' => 'We use cookies',
    'banner_description' => 'We use only the cookies needed to run this website securely and remember your choices. We do not use advertising or tracking cookies. You can review your preferences at any time.',

    'privacy_title' => 'Your privacy is important to us',
    'privacy_description' => "Cookies are small text files stored on your device when you visit a website. We keep them to a minimum: this website uses only the cookies it needs to work securely (for example, to keep you signed in and to protect our forms) and to remember your cookie choices.\n\nWe will never set optional cookies without your consent. You can change your preferences below, and you can delete cookies at any time in your browser settings — but blocking strictly necessary cookies may stop parts of the website, such as forms and signing in, from working.",

    'necessary_title' => 'Strictly necessary cookies',
    'necessary_description' => "These cookies are essential for the website to work securely — for example, to keep your session active, to protect our forms against misuse, and to remember your cookie choices.\n\nThey cannot be switched off, and they do not collect information for marketing.",

    'functionality_title' => 'Functionality cookies',
    'functionality_description' => "Functionality cookies remember choices you make to give you a more personalised experience.\n\nWe do not currently use any functionality cookies beyond those that are strictly necessary. If we introduce them, we will ask for your consent first.",

    'tracking_title' => 'Tracking cookies',
    'tracking_description' => "Tracking (analytics) cookies measure how visitors use a website, such as which pages are visited and for how long.\n\nWe do not currently use any tracking or analytics cookies. If we introduce them, they will only be set with your consent.",

    'targeting_title' => 'Targeting and advertising cookies',
    'targeting_description' => "Targeting and advertising cookies are used by advertising networks to show ads based on your browsing habits.\n\nWe do not use any targeting or advertising cookies, and we do not allow advertising networks to place cookies on this website.",

    'more_info_title' => 'More information',
    'more_info_description' => 'For details of every cookie we use, see our Cookie Policy. For any questions about cookies or your choices, please contact us.',
];
