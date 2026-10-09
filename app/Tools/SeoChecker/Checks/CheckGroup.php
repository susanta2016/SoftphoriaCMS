<?php

namespace App\Tools\SeoChecker\Checks;

use App\Tools\SeoChecker\AuditContext;
use App\Tools\SeoChecker\AuditException;
use App\Tools\SeoChecker\Finding;

/**
 * One report category's checks. run() returns its findings; helpers keep
 * every finding's shape the same (id, evidence, why, fix, limitation).
 */
abstract class CheckGroup
{
    public const CATEGORY = '';

    public const LABEL = '';

    /**
     * @return list<Finding>
     */
    abstract public function run(AuditContext $context): array;

    /**
     * @param  string|list<string>  $evidence
     */
    protected function critical(string $id, string $title, string|array $evidence, string $why, string $fix, ?string $limitation = null): Finding
    {
        return $this->finding($id, Finding::CRITICAL, $title, $evidence, $why, $fix, $limitation);
    }

    /**
     * @param  string|list<string>  $evidence
     */
    protected function warning(string $id, string $title, string|array $evidence, string $why, string $fix, ?string $limitation = null): Finding
    {
        return $this->finding($id, Finding::WARNING, $title, $evidence, $why, $fix, $limitation);
    }

    /**
     * @param  string|list<string>  $evidence
     */
    protected function passed(string $id, string $title, string|array $evidence, string $why, string $fix = 'Nothing to do.', ?string $limitation = null): Finding
    {
        return $this->finding($id, Finding::PASSED, $title, $evidence, $why, $fix, $limitation);
    }

    /**
     * @param  string|list<string>  $evidence
     */
    protected function notChecked(string $id, string $title, string|array $evidence, string $why, string $fix = 'Run the check again later.', ?string $limitation = null): Finding
    {
        return $this->finding($id, Finding::NOT_CHECKED, $title, $evidence, $why, $fix, $limitation);
    }

    /** Live site: blocker. Staging copy: warning. */
    protected function blockerUnlessStaging(AuditContext $context): string
    {
        return $context->isStaging() ? Finding::WARNING : Finding::CRITICAL;
    }

    /**
     * @param  string|list<string>  $evidence
     */
    protected function finding(string $id, string $severity, string $title, string|array $evidence, string $why, string $fix, ?string $limitation = null): Finding
    {
        return new Finding($id, static::CATEGORY, $severity, $title, (array) $evidence, $why, $fix, $limitation);
    }

    protected function failure(AuditException $e): string
    {
        return 'Not checked: '.$e->getMessage();
    }

    protected function quote(string $value, int $limit = 160): string
    {
        $value = trim($value);

        return '"'.(mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1).'…' : $value).'"';
    }
}
