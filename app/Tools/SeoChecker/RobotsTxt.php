<?php

namespace App\Tools\SeoChecker;

/**
 * robots.txt read the way Google documents it (RFC 9309): a crawler obeys
 * only the most specific group naming it, else the "*" group; within the
 * group the longest matching rule wins and Allow wins a tie; "*" and "$"
 * are wildcards. Other crawlers can interpret files differently — the
 * report says so.
 */
final class RobotsTxt
{
    /** @var list<array{agents: list<string>, rules: list<array{allow: bool, path: string}>}> */
    private array $groups = [];

    /** @var list<string> */
    public array $sitemaps = [];

    public function __construct(string $content)
    {
        $current = null;
        $lastWasAgent = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'sitemap') {
                if ($value !== '') {
                    $this->sitemaps[] = $value;
                }

                continue;
            }

            if ($field === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $this->groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($this->groups);
                }
                $this->groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;

            if ($current !== null && in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $this->groups[$current]['rules'][] = ['allow' => $field === 'allow', 'path' => $value];
            }
        }
    }

    /**
     * @return array{allowed: bool, rule: ?string, group: ?string}
     */
    public function check(string $agent, string $pathAndQuery): array
    {
        $agent = strtolower($agent);
        $rules = [];
        $matchedGroup = null;

        foreach ([$agent, '*'] as $candidate) {
            foreach ($this->groups as $group) {
                if (in_array($candidate, $group['agents'], true)) {
                    $rules = [...$rules, ...$group['rules']];
                    $matchedGroup = $candidate;
                }
            }
            if ($matchedGroup !== null) {
                break;
            }
        }

        $best = null;
        foreach ($rules as $rule) {
            if (! $this->matches($rule['path'], $pathAndQuery)) {
                continue;
            }
            $length = strlen($rule['path']);
            if ($best === null || $length > strlen($best['path']) || ($length === strlen($best['path']) && $rule['allow'])) {
                $best = $rule;
            }
        }

        return [
            'allowed' => $best === null || $best['allow'],
            'rule' => $best === null ? null : ($best['allow'] ? 'Allow: ' : 'Disallow: ').$best['path'],
            'group' => $matchedGroup,
        ];
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $pattern = $anchored ? substr($pattern, 0, -1) : $pattern;
        $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
