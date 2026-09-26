<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Builds campaign URLs with UTM parameters (source, medium, campaign, term,
 * content), keeping any existing query string. Runs entirely in the browser.
 */
class UtmBuilder extends ToolFunctionality
{
    public const KEY = 'utm-builder';

    public const NAME = 'UTM Builder';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Builds campaign-tracking URLs with UTM parameters for Google Analytics and other analytics tools.';

    public const APPLICATION_CATEGORY = 'BusinessApplication';
}
