<?php

namespace App\Actions\AuditLog;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams audit log entries as a CSV download — used by the Audit Log
 * table's "Export selected" bulk action and its "Export" header action
 * (which exports whatever the current search/filters match). Rows are
 * read with lazyById() so a full-history export stays memory-flat.
 */
class ExportAuditLogsAction
{
    private const HEADERS = ['ID', 'When (UTC)', 'Actor', 'Actor email', 'Action', 'Entity', 'Entity ID', 'IP address', 'User agent', 'Metadata'];

    /**
     * @param  Builder<AuditLog>  $query
     */
    public function handle(Builder $query): StreamedResponse
    {
        $query = (clone $query)->reorder()->with('user:id,name,email');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADERS);

            foreach ($query->lazyById(500) as $log) {
                fputcsv($out, array_map($this->safeCell(...), [
                    $log->id,
                    $log->created_at?->utc()->toDateTimeString(),
                    $log->user?->name ?? 'System',
                    $log->user?->email,
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->ip_address,
                    $log->user_agent,
                    $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                ]));
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralises spreadsheet formula injection: values that a spreadsheet
     * would evaluate (=, +, -, @, tab, CR) are prefixed with an apostrophe.
     */
    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
