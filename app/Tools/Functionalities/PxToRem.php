<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Converts pixels to rem (and back) against a chosen root font size, with a
 * reference table and a list converter (maths in
 * resources/js/tools/px-to-rem/src/convert.js). Runs entirely in the browser.
 */
class PxToRem extends ToolFunctionality
{
    public const KEY = 'px-to-rem';

    public const NAME = 'PX to REM Converter';

    public const VERSION = '1.1.0';

    public const DESCRIPTION = 'Converts px to rem and rem to px for any root font size, one value or a whole list, with a copyable reference table.';

    public const APPLICATION_CATEGORY = 'DeveloperApplication';
}
