<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Converts pixels to rem (and back) against a chosen root font size, with a
 * quick reference table. Runs entirely in the browser.
 */
class PxToRem extends ToolFunctionality
{
    public const KEY = 'px-to-rem';

    public const NAME = 'PX to REM Converter';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Converts px to rem and rem to px for any root font size, with a copyable reference table.';

    public const APPLICATION_CATEGORY = 'DeveloperApplication';
}
