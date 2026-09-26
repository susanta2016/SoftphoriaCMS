<?php

/**
 * Default copy for the Cookies Policy banner and Preferences Center
 * (docs/Cookies Policy popup.docx layout). Both App\Filament\Pages\CookiesPolicy
 * (admin form defaults) and the public x-site.cookie-consent component
 * (fallback when an admin has never saved the `cookies` settings group) read
 * from here, so the banner is fully worded out of the box.
 *
 * The wording stays true whatever is switched on in Website Setup →
 * Analytics & Tracking: category descriptions are neutral and
 * x-site.cookie-consent appends the tools actually in use
 * (AnalyticsIntegrations), while banner_description_with_analytics replaces
 * the default banner text whenever an analytics/marketing tool is active.
 */
return [
    'enabled' => true,

    'banner_title' => 'We use cookies',
    'banner_description' => 'We use only the cookies needed to run this website securely and remember your choices. We do not use advertising or tracking cookies. You can review your preferences at any time.',

    // Shown instead of banner_description while analytics or marketing tools
    // are switched on — unless an admin has written their own banner text.
    'banner_description_with_analytics' => "We use essential cookies to run this website. With your permission, we'd also like to use analytics and marketing cookies to understand how the site is used and improve our services. Accept, decline, or choose in your preferences.",

    'privacy_title' => 'Your privacy is important to us',
    'privacy_description' => "Cookies are small text files stored on your device when you visit a website. We keep them to a minimum: by default this website uses only the cookies it needs to work securely (for example, to keep you signed in and to protect our forms) and to remember your cookie choices.\n\nOptional cookies are never set without your consent. You can change your preferences below, and you can delete cookies at any time in your browser settings — but blocking strictly necessary cookies may stop parts of the website, such as forms and signing in, from working.",

    'necessary_title' => 'Strictly necessary cookies',
    'necessary_description' => "These cookies are essential for the website to work securely — for example, to keep your session active, to protect our forms against misuse, and to remember your cookie choices.\n\nThey cannot be switched off, and they do not collect information for marketing.",

    'functionality_title' => 'Functionality cookies',
    'functionality_description' => "Functionality cookies remember choices you make to give you a more personalised experience.\n\nThey are only set if you switch this category on.",

    'tracking_title' => 'Tracking cookies',
    'tracking_description' => "Tracking (analytics) cookies measure how visitors use a website, such as which pages are visited and for how long. They help us understand what works and improve the website.\n\nThey are only set if you switch this category on.",

    'targeting_title' => 'Targeting and advertising cookies',
    'targeting_description' => "Targeting and advertising cookies are used by advertising platforms to measure how our ads perform and to show ads based on your browsing habits.\n\nThey are only set if you switch this category on.",

    'more_info_title' => 'More information',
    'more_info_description' => 'For details of every cookie we use, see our Cookie Policy. For any questions about cookies or your choices, please contact us.',
];
