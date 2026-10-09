<?php

namespace Tests\Unit\Tools\SeoChecker;

use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\HostResolver;
use App\Tools\SeoChecker\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * URL normalization and the SSRF rules: schemes, ports, credentials,
 * local names and every non-public IPv4/IPv6 range — including addresses a
 * public-looking hostname resolves to.
 */
class UrlGuardTest extends TestCase
{
    private function guard(array $dns = []): UrlGuard
    {
        return new UrlGuard(new class($dns) implements HostResolver
        {
            public function __construct(private array $dns) {}

            public function resolve(string $host): array
            {
                return $this->dns[$host] ?? ['93.184.216.34'];
            }
        });
    }

    public static function normalized(): array
    {
        return [
            ['example.com', 'https://example.com/'],
            ['  https://Example.COM  ', 'https://example.com/'],
            ['HTTP://example.com:80/a?b=1#frag', 'http://example.com/a?b=1'],
            ['https://example.com:443/path/', 'https://example.com/path/'],
            ['https://example.com:8443/', 'https://example.com:8443/'],
            ['www.example.com/page', 'https://www.example.com/page'],
            ['example.com:8080/x', 'https://example.com:8080/x'],
            ['https://bücher.de/', 'https://xn--bcher-kva.de/'],
            ['https://example.com./', 'https://example.com/'],
        ];
    }

    #[DataProvider('normalized')]
    public function test_it_normalizes_addresses(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->guard()->normalize($input));
    }

    public static function rejected(): array
    {
        return [
            ['', 'invalid_url'],
            ['https://', 'invalid_url'],
            ['https://exa mple.com/', 'invalid_url'],
            ["https://exa\nmple.com/", 'invalid_url'],
            ['https://shop.example/', 'blocked_destination'],
            ['https://'.str_repeat('a', 2050).'.com/', 'invalid_url'],
            ['ftp://example.com/', 'unsupported_scheme'],
            ['file:///etc/passwd', 'unsupported_scheme'],
            ['javascript:alert(1)', 'unsupported_scheme'],
            ['gopher://example.com/', 'unsupported_scheme'],
            ['https://user:pass@example.com/', 'credentials_in_url'],
            ['https://example.com:22/', 'port_not_allowed'],
            ['http://example.com:6379/', 'port_not_allowed'],
            ['http://localhost/', 'blocked_destination'],
            ['http://app.localhost/', 'blocked_destination'],
            ['http://printer.local/', 'blocked_destination'],
            ['http://metadata.google.internal/', 'blocked_destination'],
            ['http://intranet/', 'blocked_destination'],
            ['http://2130706433/', 'blocked_destination'],
            ['http://0x7f.0.0.1/', 'blocked_destination'],
            ['http://127.1/', 'blocked_destination'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_it_rejects_unsupported_or_local_addresses(string $input, string $reason): void
    {
        try {
            $this->guard()->normalize($input);
            $this->fail("{$input} was accepted");
        } catch (AuditException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public static function privateIps(): array
    {
        return array_map(fn (string $ip): array => [$ip], [
            '127.0.0.1', '127.255.255.254', '10.1.2.3', '172.16.0.1', '172.31.255.255', '192.168.1.1',
            '169.254.169.254', '100.64.0.1', '0.0.0.0', '192.0.2.10', '198.18.0.1', '224.0.0.1', '255.255.255.255',
            '::1', '::', 'fe80::1', 'fc00::1', 'fd12:3456::1', 'ff02::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1',
            '::ffff:169.254.169.254', '64:ff9b::7f00:1', '2002:7f00:1::1', '2001:db8::1', '2001::1',
        ]);
    }

    #[DataProvider('privateIps')]
    public function test_non_public_ip_addresses_are_blocked(string $ip): void
    {
        $guard = $this->guard();
        $this->assertFalse($guard->isPublicIp($ip), $ip);

        $literal = str_contains($ip, ':') ? "http://[{$ip}]/" : "http://{$ip}/";
        try {
            $guard->target($literal);
            $this->fail("{$literal} was allowed");
        } catch (AuditException $e) {
            $this->assertContains($e->reason, ['blocked_destination', 'invalid_url']);
        }
    }

    public function test_public_addresses_are_allowed_and_pinned(): void
    {
        $guard = $this->guard(['example.com' => ['2606:2800:220:1::1', '93.184.216.34']]);

        $this->assertTrue($guard->isPublicIp('8.8.8.8'));
        $this->assertTrue($guard->isPublicIp('2606:4700:4700::1111'));

        $target = $guard->target('https://example.com/a');
        $this->assertSame('93.184.216.34', $target['ip'], 'IPv4 preferred');
        $this->assertSame(443, $target['port']);
        $this->assertSame('example.com', $target['host']);
    }

    public function test_a_public_name_resolving_to_a_private_address_is_blocked(): void
    {
        foreach ([['127.0.0.1'], ['93.184.216.34', '10.0.0.7'], ['::ffff:192.168.0.1'], ['169.254.169.254']] as $answers) {
            try {
                $this->guard(['rebind.example.com' => $answers])->target('https://rebind.example.com/');
                $this->fail('allowed '.implode(',', $answers));
            } catch (AuditException $e) {
                $this->assertSame('blocked_destination', $e->reason);
                $this->assertStringNotContainsString($answers[0], $e->getMessage(), 'internal addresses are never echoed');
            }
        }
    }

    public function test_an_unresolvable_name_is_reported_as_dns_failure(): void
    {
        $this->expectExceptionObject(new AuditException('dns_failed'));
        $this->guard(['nowhere.example.com' => []])->target('https://nowhere.example.com/');
    }
}
