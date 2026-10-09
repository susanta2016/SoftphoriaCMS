<?php

namespace App\Tools\SeoChecker;

/**
 * One check's result. Evidence is only what was actually detected; every
 * string is cleaned (single line, no control characters, bounded) here and
 * the browser inserts it as text, never as HTML.
 */
final class Finding
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const PASSED = 'passed';

    public const NOT_CHECKED = 'not_checked';

    /**
     * @param  list<string>  $evidence
     */
    public function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $severity,
        public readonly string $title,
        public readonly array $evidence,
        public readonly string $why,
        public readonly string $fix,
        public readonly ?string $limitation = null,
    ) {}

    /**
     * @return array{id: string, category: string, severity: string, title: string, evidence: list<string>, why: string, fix: string, limitation: ?string, weight: int|float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'severity' => $this->severity,
            'title' => PageDocument::clean($this->title, 160),
            'evidence' => array_values(array_map(fn (string $line): string => PageDocument::clean($line, 400), array_slice($this->evidence, 0, 12))),
            'why' => $this->why,
            'fix' => $this->fix,
            'limitation' => $this->limitation,
            'weight' => Scorer::weight($this->id),
        ];
    }
}
