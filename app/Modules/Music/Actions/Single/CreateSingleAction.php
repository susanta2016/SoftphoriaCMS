<?php

namespace App\Modules\Music\Actions\Single;

use App\Models\User;
use App\Modules\Music\Actions\Single\Concerns\SavesSingleRelations;
use App\Modules\Music\Enums\ReleaseStatus;
use App\Modules\Music\Models\Single;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateSingleAction
{
    use SavesSingleRelations;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Single
    {
        return DB::transaction(function () use ($data, $actor): Single {
            $single = new Single;
            $single->fill(collect($data)->except(['seo'])->all());
            $single->status ??= ReleaseStatus::Draft;
            $single->created_by = $actor->getKey();
            $single->updated_by = $actor->getKey();
            $single->save();

            $this->saveMusicSeo($single, $data['seo'] ?? []);

            $this->auditLog->record($actor, 'single.created', $single, ['title' => $single->title]);

            return $single;
        });
    }
}
