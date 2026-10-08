<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Exact Image KB Optimizer: compresses one or more images in the browser to
 * at or below a maximum file size (10 KB … 1 MB or custom), keeping the
 * highest quality and the original dimensions where possible, then verifies
 * the result. Images are never uploaded; ZIP files are built in the browser.
 *
 * Implementation: resources/js/tools/image-kb-optimizer/, mounted by
 * resources/js/tools/image-kb-optimizer.js. A `?target=50kb` query string
 * preselects a target.
 */
class ImageKbOptimizer extends ToolFunctionality
{
    public const KEY = 'image-kb-optimizer';

    public const NAME = 'Exact Image KB Optimizer';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Compresses images in the browser to at or below a target file size (10 KB to 1 MB or custom), with dimension and format options, verification and ZIP download.';

    public const APPLICATION_CATEGORY = 'MultimediaApplication';
}
