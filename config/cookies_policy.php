<?php

/**
 * Default copy for the Cookies Policy banner and Preferences Center
 * (docs/Cookies Policy popup.docx) — the exact wording from the approved
 * screenshots. Both App\Filament\Pages\CookiesPolicy (admin form defaults)
 * and the public x-site.cookie-consent component (fallback when an admin
 * has never saved the `cookies` settings group yet) read from here, so the
 * banner is fully worded out of the box instead of needing a first save.
 */
return [
    'enabled' => true,

    'banner_title' => 'We use cookies',
    'banner_description' => 'We use cookies and other tracking technologies to improve your browsing experience on our website, to show you personalized content and targeted ads, to analyze our website traffic, and to understand where our visitors are coming from.',

    'privacy_title' => 'Your privacy is important to us',
    'privacy_description' => "Cookies are very small text files that are stored on your computer when you visit a website. We use cookies for a variety of purposes and to enhance your online experience on our website (for example, to remember your account login details).\n\nYou can change your preferences and decline certain types of cookies to be stored on your computer while browsing our website. You can also remove any cookies already stored on your computer, but keep in mind that deleting cookies may prevent you from using parts of our website.",

    'necessary_title' => 'Strictly necessary cookies',
    'necessary_description' => "These cookies are essential to provide you with services available through our website and to enable you to use certain features of our website.\n\nWithout these cookies, we cannot provide you certain services on our website.",

    'functionality_title' => 'Functionality cookies',
    'functionality_description' => "These cookies are used to provide you with a more personalized experience on our website and to remember choices you make when you use our website.\n\nFor example, we may use functionality cookies to remember your language preferences or remember your login details.",

    'tracking_title' => 'Tracking cookies',
    'tracking_description' => "These cookies are used to collect information to analyze the traffic to our website and how visitors are using our website.\n\nFor example, these cookies may track things such as how long you spend on the website or the pages you visit which helps us to understand how we can improve our website for you.\n\nThe information collected through these tracking and performance cookies do not identify any individual visitor.",

    'targeting_title' => 'Targeting and advertising cookies',
    'targeting_description' => "These cookies are used to show advertising that is likely to be of interest to you based on your browsing habits.\n\nThese cookies, as served by our content and/or advertising providers, may combine information they collected from our website with other information they have independently collected relating to your web browser's activities across their network of websites.\n\nIf you choose to remove or disable these targeting or advertising cookies, you will still see adverts but they may not be relevant to you.",

    'more_info_title' => 'More information',
    'more_info_description' => 'For any queries in relation to our policy on cookies and your choices, please contact us.',
];
