<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Validates SRT and WebVTT subtitle files — structure, timestamps, timing
 * and readability — and writes a corrected copy on request. Runs entirely
 * in the browser (resources/js/tools/subtitle-validator/): subtitle files
 * are never uploaded, stored or sent with analytics.
 */
class SubtitleValidator extends ToolFunctionality
{
    public const KEY = 'subtitle-validator';

    public const NAME = 'Subtitle Validator';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Checks SRT and WebVTT files for structure, timestamp, timing and readability problems, and downloads a corrected copy.';

    public const APPLICATION_CATEGORY = 'MultimediaApplication';
}
