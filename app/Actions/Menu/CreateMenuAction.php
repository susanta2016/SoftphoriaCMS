<?php

namespace App\Actions\Menu;

use App\Models\Menu;
use App\Models\User;
use App\Shared\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class CreateMenuAction
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly SyncMenuItemsAction $syncItems,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Menu
    {
        return DB::transaction(function () use ($data, $actor): Menu {
            $items = $data['items'] ?? [];

            $menu = new Menu;
            $menu->fill(collect($data)->except('items')->all());
            $menu->created_by = $actor->getKey();
            $menu->updated_by = $actor->getKey();
            $menu->save();

            $this->syncItems->handle($menu, $items, $actor);

            $this->auditLog->record($actor, 'menu.created', $menu, ['name' => $menu->name]);

            return $menu;
        });
    }
}
