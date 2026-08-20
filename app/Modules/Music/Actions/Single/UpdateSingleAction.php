<?php

namespace App\Modules\Music\Actions\Single;

use App\Models\User;
use App\Modules\Music\Actions\Single\Concerns\SavesSingleRelations;
use App\Modules\Music\Models\Single;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateSingleAction
{
    use SavesSingleRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Single $single, array $data, User $actor): Single
    {
        return DB::transaction(function () use ($single, $data, $actor): Single {
            $single->fill(collect($data)->except(['links', 'seo'])->all());
            $single->updated_by = $actor->getKey();
            $single->save();

            $this->syncStreamingLinks($single, $data['links'] ?? []);
            $this->saveMusicSeo($single, $data['seo'] ?? []);

            $this->auditLog->record($actor, 'single.updated', $single, ['title' => $single->title]);

            return $single;
        });
    }
}
