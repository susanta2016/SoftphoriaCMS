// Image Requirements Checker — platform / placement profiles.
//
// The ONLY place platform requirements live. The checker engine (src/check.js)
// and the interface read everything from here; nothing else hard-codes a size.
//
// Every rule has a level:
//   hard         a documented platform limit: not meeting it is a FAIL
//   recommended  documented or widely published guidance: not meeting it is a WARNING
//   advisory     useful context: shown as INFO, never affects the result
// A value we could not confirm on an official page is never 'hard'.
//
// Fields (all optional except id, platform, placement, frame, ratio):
//   frame        recommended dimensions { width, height } — also the crop target shape
//   ratio        { min, max } accepted width ÷ height range (inclusive, ±tolerance),
//                level, and onMismatch: 'crop' (the platform crops) | 'letterbox' (bars/fit)
//   minWidth / minHeight   { value, level }
//   maxBytes     list of { value, level, label }
//   formats      { allowed: ['jpeg','png','webp'], level }
//   display      'rect' | 'circle' (shown inside a circle, e.g. profile photos)
//   views        other ways the platform shows the image: { label, ratio: [w, h], level, note }
//   safeZone     { level, source, label, safe: [l, t, r, b], covered?: [[l, t, r, b], ...], note }
//                fractions of the frame
//   notes        advisory sentences; sources: [{ label, url }]
//
// Reviewed against the sources below on 2026-10-08. Platforms change these
// without notice: re-check them when updating, and bump PROFILES_VERSION.

export const PROFILES_VERSION = '1.0.0';
export const REVIEWED = '2026-10-08';

const MB = 1024 * 1024;
const r = (w, h) => w / h;

const SRC = {
    instagram: { label: 'Instagram Help Center: photo resolution (via search copies, 1.91:1 to 4:5, 320–1080 px wide)', url: 'https://help.instagram.com/1631821640426723' },
    metaStory: { label: 'Meta Ads Guide: Instagram Stories image ads', url: 'https://www.facebook.com/business/ads-guide/update/image/instagram-story' },
    metaFeed: { label: 'Meta Ads Guide: Facebook Feed image ads', url: 'https://www.facebook.com/business/ads-guide/update/image/facebook-feed' },
    fbCover: { label: 'Facebook Help Center: cover photo sizes', url: 'https://www.facebook.com/help/125379114252045' },
    linkedinPages: { label: 'LinkedIn Help: image specifications for Pages', url: 'https://www.linkedin.com/help/linkedin/answer/70781' },
    linkedinShare: { label: 'LinkedIn Help: make your website shareable (5 MB, 1.91:1)', url: 'https://www.linkedin.com/help/linkedin/answer/46687' },
    xProfile: { label: 'X Help Center: uploading a profile photo and header', url: 'https://help.x.com/en/managing-your-account/common-issues-when-uploading-profile-photo' },
    ytThumb: { label: 'YouTube Help: add video thumbnails', url: 'https://support.google.com/youtube/answer/72431' },
    ytBanner: { label: 'YouTube Help: manage your channel branding', url: 'https://support.google.com/youtube/answer/10456525' },
    ogImage: { label: 'Meta for Developers: images in link shares', url: 'https://developers.facebook.com/docs/sharing/webmasters/images/' },
    thirdParty: { label: 'Widely published size guides (no official page found)', url: null },
    softphoria: { label: 'Softphoria guidance for fast-loading web pages', url: null },
};

export const PROFILES = [
    // --- Instagram --------------------------------------------------------
    {
        id: 'instagram-feed-portrait', platform: 'Instagram', placement: 'Feed post — portrait', category: 'social',
        frame: { width: 1080, height: 1350 },
        ratio: { min: r(4, 5), max: r(4, 5), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 320, level: 'recommended' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        notes: [
            'Instagram keeps photos 320–1080 px wide at their original resolution and resizes wider ones to 1080 px.',
            'Instagram announced support for taller 3:4 photos in 2025; its help page still lists 4:5 as the tallest feed shape.',
        ],
        sources: [SRC.instagram],
    },
    {
        id: 'instagram-feed-square', platform: 'Instagram', placement: 'Feed post — square', category: 'social',
        frame: { width: 1080, height: 1080 },
        ratio: { min: 1, max: 1, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 320, level: 'recommended' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        sources: [SRC.instagram],
    },
    {
        id: 'instagram-feed-landscape', platform: 'Instagram', placement: 'Feed post — landscape', category: 'social',
        frame: { width: 1080, height: 566 },
        ratio: { min: 1.91, max: 1.91, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 320, level: 'recommended' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        sources: [SRC.instagram],
    },
    {
        id: 'instagram-story', platform: 'Instagram', placement: 'Story', category: 'social',
        frame: { width: 1080, height: 1920 },
        ratio: { min: r(9, 16), max: r(9, 16), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 500, level: 'recommended' },
        maxBytes: [{ value: 30 * MB, level: 'recommended', label: "Meta's limit for Stories ads" }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        safeZone: {
            level: 'advisory', label: "Meta's Stories ad guidance",
            safe: [0.06, 0.14, 0.94, 0.65],
            note: 'Meta advises keeping about 14% of the top, 35% of the bottom and 6% of each side of a Stories ad free of text and logos. Organic Stories show less interface, so this is a cautious guide.',
        },
        sources: [SRC.metaStory],
    },
    {
        id: 'instagram-reel-cover', platform: 'Instagram', placement: 'Reel cover', category: 'social',
        frame: { width: 1080, height: 1920 },
        ratio: { min: r(9, 16), max: r(9, 16), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 500, level: 'recommended' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        views: [{ label: 'Profile grid preview', ratio: [3, 4], level: 'advisory', note: 'Instagram shows a 3:4 preview of the cover on your profile grid; keep the title and faces in the middle.' }],
        notes: ['To see what the Reels buttons and caption cover while the Reel plays, use the Social Video Safe Zone Checker.'],
        sources: [SRC.thirdParty],
    },

    // --- Facebook ---------------------------------------------------------
    {
        id: 'facebook-feed', platform: 'Facebook', placement: 'Feed post', category: 'social',
        frame: { width: 1080, height: 1350 },
        ratio: { min: r(4, 5), max: 1.91, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 600, level: 'recommended' },
        maxBytes: [{ value: 30 * MB, level: 'recommended', label: "Meta's limit for Feed image ads" }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        notes: ['Shapes between landscape 1.91:1 and portrait 4:5 show in full in the feed; Meta recommends 4:5 for ads.'],
        sources: [SRC.metaFeed],
    },
    {
        id: 'facebook-cover', platform: 'Facebook', placement: 'Cover photo', category: 'social',
        frame: { width: 851, height: 315 },
        ratio: { min: r(851, 315), max: r(851, 315), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 400, level: 'hard' },
        minHeight: { value: 150, level: 'hard' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        views: [
            { label: 'On computers', ratio: [16, 9], level: 'advisory', note: "Facebook's help page says cover photos show at 16:9 on computers." },
            { label: 'On phones', ratio: [2.4, 1], level: 'advisory', note: "Facebook's help page says cover photos show at 2.4:1 on smartphones, and the profile picture overlaps about 40 px of the cover." },
        ],
        notes: ['For the fastest loading, Facebook suggests an sRGB JPG, 851 × 315 px and under 100 KB.'],
        sources: [SRC.fbCover],
    },
    {
        id: 'facebook-profile', platform: 'Facebook', placement: 'Profile picture', category: 'social',
        frame: { width: 320, height: 320 },
        ratio: { min: 1, max: 1, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 320, level: 'recommended' },
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        display: 'circle',
        notes: ['Profile pictures are shown inside a circle, so the corners are hidden.'],
        sources: [SRC.thirdParty],
    },

    // --- LinkedIn ---------------------------------------------------------
    {
        id: 'linkedin-post', platform: 'LinkedIn', placement: 'Post image', category: 'social',
        frame: { width: 1200, height: 627 },
        ratio: { min: 1, max: 1.91, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 200, level: 'recommended' },
        maxBytes: [{ value: 5 * MB, level: 'recommended', label: "LinkedIn's limit for link-preview and ad images" }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        notes: ['LinkedIn recommends 1.91:1 (1200 × 627 px) for Page posts; images under 200 px wide show as a small thumbnail.'],
        sources: [SRC.linkedinPages, SRC.linkedinShare],
    },
    {
        id: 'linkedin-profile', platform: 'LinkedIn', placement: 'Profile photo', category: 'social',
        frame: { width: 400, height: 400 },
        ratio: { min: 1, max: 1, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 400, level: 'recommended' },
        maxBytes: [{ value: 8 * MB, level: 'recommended', label: 'Widely published limit for profile photos' }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        display: 'circle',
        notes: ['Profile photos are shown inside a circle, so the corners are hidden.'],
        sources: [SRC.thirdParty],
    },
    {
        id: 'linkedin-page-cover', platform: 'LinkedIn', placement: 'Company Page cover', category: 'social',
        frame: { width: 1512, height: 256 },
        ratio: { min: r(1512, 256), max: r(1512, 256), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 1512, level: 'hard' },
        minHeight: { value: 256, level: 'hard' },
        maxBytes: [{ value: 3 * MB, level: 'hard', label: "LinkedIn's limit for Page images" }],
        formats: { allowed: ['jpeg', 'png'], level: 'hard' },
        sources: [SRC.linkedinPages],
    },

    // --- X ----------------------------------------------------------------
    {
        id: 'x-post', platform: 'X', placement: 'Post image', category: 'social',
        frame: { width: 1200, height: 675 },
        ratio: { min: r(16, 9), max: r(16, 9), level: 'recommended', onMismatch: 'crop' },
        maxBytes: [{ value: 5 * MB, level: 'recommended', label: 'Widely published limit for photos' }],
        formats: { allowed: ['jpeg', 'png', 'webp'], level: 'recommended' },
        notes: ['Timeline previews of other shapes may be cropped; the full image opens when tapped.'],
        sources: [SRC.thirdParty],
    },
    {
        id: 'x-header', platform: 'X', placement: 'Header', category: 'social',
        frame: { width: 1500, height: 500 },
        ratio: { min: 3, max: 3, level: 'recommended', onMismatch: 'crop' },
        maxBytes: [{ value: 2 * MB, level: 'recommended', label: 'Published limit for profile images' }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        safeZone: {
            level: 'recommended', label: "X's cropping note",
            safe: [0, 0.12, 1, 0.88], covered: [[0, 0, 1, 0.12], [0, 0.88, 1, 1]],
            note: 'X says about 60 px at the top and bottom of a 1500 × 500 header can be cropped on different screens.',
        },
        sources: [SRC.xProfile],
    },

    // --- YouTube ----------------------------------------------------------
    {
        id: 'youtube-thumbnail', platform: 'YouTube', placement: 'Video thumbnail', category: 'social',
        frame: { width: 1280, height: 720 },
        ratio: { min: r(16, 9), max: r(16, 9), level: 'recommended', onMismatch: 'letterbox' },
        minWidth: { value: 640, level: 'hard' },
        maxBytes: [
            { value: 50 * MB, level: 'hard', label: "YouTube's limit when uploading from a computer" },
            { value: 2 * MB, level: 'recommended', label: "YouTube's limit when uploading from the mobile app" },
        ],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        notes: ['YouTube lists 3840 × 2160 as the resolution to aim for; 1280 × 720 remains a widely used size.'],
        sources: [SRC.ytThumb],
    },
    {
        id: 'youtube-banner', platform: 'YouTube', placement: 'Channel banner', category: 'social',
        frame: { width: 2560, height: 1440 },
        ratio: { min: r(16, 9), max: r(16, 9), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 2048, level: 'hard' },
        minHeight: { value: 1152, level: 'hard' },
        maxBytes: [{ value: 6 * MB, level: 'hard', label: "YouTube's limit for banner images" }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        safeZone: {
            level: 'recommended', label: "YouTube's safe area for text and logos",
            safe: [(2560 - 1235) / 2 / 2560, (1440 - 338) / 2 / 1440, 1 - (2560 - 1235) / 2 / 2560, 1 - (1440 - 338) / 2 / 1440],
            note: 'Only the middle 1235 × 338 px is guaranteed to show on every device; TVs show the whole banner.',
        },
        sources: [SRC.ytBanner],
    },

    // --- Pinterest --------------------------------------------------------
    {
        id: 'pinterest-pin', platform: 'Pinterest', placement: 'Standard Pin', category: 'social',
        frame: { width: 1000, height: 1500 },
        ratio: { min: r(2, 3), max: r(2, 3), level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 600, level: 'recommended' },
        maxBytes: [{ value: 20 * MB, level: 'recommended', label: 'Widely published limit for Pin images' }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        sources: [SRC.thirdParty],
    },

    // --- Website ----------------------------------------------------------
    {
        id: 'website-og', platform: 'Website', placement: 'Open Graph (link preview) image', category: 'website',
        frame: { width: 1200, height: 630 },
        ratio: { min: 1.91, max: 1.91, level: 'recommended', onMismatch: 'crop' },
        minWidth: { value: 200, level: 'hard' },
        minHeight: { value: 200, level: 'hard' },
        maxBytes: [{ value: 8 * MB, level: 'hard', label: "Facebook's limit for shared link images" }],
        formats: { allowed: ['jpeg', 'png'], level: 'recommended' },
        notes: ['Images smaller than 600 × 315 px still work but show as a small preview. JPG or PNG is the safest choice for every app that shows link previews.'],
        sources: [SRC.ogImage],
    },
    {
        id: 'website-hero', platform: 'Website', placement: 'Full-width hero image', category: 'website',
        frame: { width: 1920, height: 1080 },
        ratio: { min: r(16, 9), max: r(21, 9), level: 'advisory', onMismatch: 'crop' },
        maxBytes: [{ value: 500 * 1024, level: 'recommended', label: 'Softphoria guidance for a fast hero image' }],
        formats: { allowed: ['jpeg', 'png', 'webp'], level: 'recommended' },
        notes: ['Hero images are usually cropped differently on phones; keep the subject near the middle.'],
        sources: [SRC.softphoria],
    },
    {
        id: 'website-content', platform: 'Website', placement: 'Content image', category: 'website',
        frame: { width: 1200, height: 800 },
        ratio: { min: 0.5, max: 2.5, level: 'advisory', onMismatch: 'crop' },
        maxBytes: [{ value: 300 * 1024, level: 'recommended', label: 'Softphoria guidance for a fast content image' }],
        formats: { allowed: ['jpeg', 'png', 'webp'], level: 'recommended' },
        sources: [SRC.softphoria],
    },
];

export const getProfile = (id) => PROFILES.find((p) => p.id === id) || null;
