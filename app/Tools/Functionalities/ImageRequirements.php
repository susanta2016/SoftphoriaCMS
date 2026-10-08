<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Image Requirements Checker: reads an image in the browser (format by
 * content, size, shape, transparency, colour, EXIF/GPS) and checks it
 * against platform placement profiles or the user's own requirements, with
 * crop and safe-zone previews. The file is never uploaded or stored.
 *
 * Implementation: resources/js/tools/image-requirements/ (requirements data
 * in data/profiles.js), mounted by resources/js/tools/image-requirements.js.
 */
class ImageRequirements extends ToolFunctionality
{
    public const KEY = 'image-requirements';

    public const NAME = 'Image Requirements Checker';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Checks an image against platform placement requirements (size, shape, format, file size) with crop and safe-zone previews, in the browser.';

    public const APPLICATION_CATEGORY = 'MultimediaApplication';
}
