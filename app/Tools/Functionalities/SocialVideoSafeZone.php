<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Social Video Safe Zone Checker: overlays the measured Instagram Reels and
 * YouTube Shorts interface zones (TikTok provisional) on a vertical video or
 * image, flags key elements they hide and suggests fixes. Runs entirely in
 * the browser — the file is never uploaded.
 *
 * The checker itself is the accepted P1a implementation in
 * resources/js/tools/social-video-safe-zone/ (one implementation, mounted by
 * resources/js/tools/social-video-safe-zone.js). Bump VERSION only when that
 * implementation's behaviour changes.
 */
class SocialVideoSafeZone extends ToolFunctionality
{
    public const KEY = 'social-video-safe-zone';

    public const NAME = 'Social Video Safe Zone Checker';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Checks a vertical video or image against the measured Instagram Reels and YouTube Shorts interface zones, in the browser.';

    public const APPLICATION_CATEGORY = 'MultimediaApplication';
}
