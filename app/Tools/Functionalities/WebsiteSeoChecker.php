<?php

namespace App\Tools\Functionalities;

use App\Tools\ToolFunctionality;

/**
 * Website SEO/Metadata Pre-launch Checker — audits one public page for
 * launch blockers: status and redirects, metadata, canonical, robots
 * directives, robots.txt, sitemap, social tags, on-page structure and basic
 * response timing.
 *
 * Unlike the other tools this one needs the server: browsers can't read
 * other sites' HTML or headers. The engine is app/Tools/SeoChecker (SSRF
 * guarded, bounded, nothing stored), reached through the throttled
 * tools.seo-checker.audit endpoint (WebsiteSeoCheckerController); the
 * browser script only sends the address and renders the report.
 */
class WebsiteSeoChecker extends ToolFunctionality
{
    public const KEY = 'website-seo-checker';

    public const NAME = 'Website SEO/Metadata Pre-launch Checker';

    public const VERSION = '1.0.0';

    public const DESCRIPTION = 'Audits a public page before launch: indexability, robots.txt, sitemap, canonical, metadata, social tags, headings, links and server response.';

    public const APPLICATION_CATEGORY = 'DeveloperApplication';

    public function viewData(): array
    {
        return ['auditUrl' => route('tools.seo-checker.audit')];
    }
}
