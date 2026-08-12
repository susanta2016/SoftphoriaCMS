<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateMenuAction
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly SyncMenuItemsAction $syncItems,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Menu $menu, array $data, User $actor): Menu
    {
        return DB::transaction(function () use ($menu, $data, $actor): Menu {
            $items = $data['items'] ?? [];

            $menu->fill(collect($data)->except('items')->all());
            $menu->updated_by = $actor->getKey();
            $menu->save();

            $this->syncItems->handle($menu, $items, $actor);

            $this->auditLog->record($actor, 'menu.updated', $menu, ['name' => $menu->name]);

            return $menu;
        });
    }
}
