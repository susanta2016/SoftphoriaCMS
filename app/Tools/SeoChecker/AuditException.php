<?php

namespace App\Tools\SeoChecker;

use RuntimeException;

/**
 * An expected reason why an address can't be checked (invalid, blocked,
 * unreachable, too large…). The message is always the public, safe text —
 * it never contains internal IPs, credentials or raw transport errors.
 */
class AuditException extends RuntimeException
{
    public const MESSAGES = [
        'invalid_url' => 'Enter a full web address, for example https://www.example.com/.',
        'unsupported_scheme' => 'Only http:// and https:// addresses can be checked.',
        'credentials_in_url' => 'Remove the username and password from the address and try again.',
        'port_not_allowed' => 'Only the standard web ports (80, 443, 8080 and 8443) can be checked.',
        'blocked_destination' => 'This address points to a local, private or reserved network, so it can\'t be checked.',
        'dns_failed' => 'We couldn\'t find this domain. Check the spelling and that its DNS records are set up.',
        'connect_failed' => 'The website didn\'t accept a connection. Check that it is online and publicly reachable.',
        'timeout' => 'The website took too long to respond, so the check was stopped.',
        'tls_failed' => 'The secure (HTTPS) connection failed. The SSL/TLS certificate may be missing, expired or issued for a different domain.',
        'too_many_redirects' => 'The address redirects too many times. Check the redirect rules on your server.',
        'redirect_loop' => 'The address redirects in a loop and never reaches a page. Check the redirect rules on your server.',
        'redirect_blocked' => 'The address redirects to a destination that can\'t be checked (a private network or a non-web address).',
        'too_large' => 'The response is larger than this checker reads, so it was stopped.',
        'decode_failed' => 'The response was compressed in a way this checker can\'t read.',
        'not_html' => 'The address didn\'t return an HTML page, so there is nothing to audit.',
        'time_budget' => 'Skipped because the audit reached its time limit.',
        'fetch_failed' => 'The website couldn\'t be fetched.',
    ];

    public function __construct(public readonly string $reason, ?string $message = null)
    {
        parent::__construct($message ?? (self::MESSAGES[$reason] ?? self::MESSAGES['fetch_failed']));
    }
}
