<?php

namespace App\Tools\SeoChecker;

/**
 * Hostnames that look like a staging, development or temporary platform
 * address. A heuristic — the report always says "looks like".
 */
final class StagingHosts
{
    /** Hosting/preview platforms' temporary domains. */
    private const PLATFORM_SUFFIXES = [
        'netlify.app', 'vercel.app', 'pages.dev', 'herokuapp.com', 'kinsta.cloud', 'wpengine.com',
        'wpenginepowered.com', 'flywheelsites.com', 'flywheelstaging.com', 'wpcomstaging.com',
        'cloudwaysapps.com', 'ngrok.io', 'ngrok-free.app', 'ngrok.app', 'instawp.xyz', 'instawp.co',
        'myftpupload.com', 'onrender.com', 'fly.dev', 'github.io', 'gitlab.io', 'azurewebsites.net',
        'web.app', 'firebaseapp.com', 'surge.sh', 'glitch.me', 'repl.co', 'replit.app', 'webflow.io',
        'wixsite.com', 'squarespace.com', 'hostingersite.com', 'stackstaging.com', 'pantheonsite.io',
        'platformsh.site', 'lndo.site', 'ddev.site', 'trycloudflare.com', 'elementor.cloud',
    ];

    /** Words that, as a host label (or label prefix/suffix), usually mean "not production". */
    private const WORDS = ['staging', 'stage', 'stg', 'dev', 'develop', 'development', 'test', 'testing', 'uat', 'qa', 'preview', 'sandbox', 'preprod', 'pre-prod', 'beta', 'temp', 'tmp', 'demo'];

    public static function looksLikeStaging(string $host): bool
    {
        $host = strtolower(trim($host, '[].'));

        foreach (self::PLATFORM_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        // Subdomain labels only: the registered name itself ("devon-bakery.com")
        // and the TLD never decide it.
        $labels = array_slice(explode('.', $host), 0, -2);

        $words = implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), self::WORDS));

        foreach ($labels as $label) {
            if (preg_match('/^(?:'.$words.')(?:[-_]?\d+)?$/', $label) || preg_match('/^(?:'.$words.')[-_]/', $label) || preg_match('/[-_](?:'.$words.')$/', $label)) {
                return true;
            }
        }

        return false;
    }
}
