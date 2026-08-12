<?php

namespace App\Actions\Menu;

use App\Actions\Menu\Concerns\ValidatesMenuItemDestination;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use App\Shared\Services\AuditLogService;

class CreateMenuItemAction
{
    use ValidatesMenuItemDestination;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Menu $menu, array $data, User $actor): MenuItem
    {
        $this->validateDestination($data);

        $item = new MenuItem;
        $item->menu_id = $menu->id;
        $item->fill($data);
        // The `target` column defaults to '_self' at the DB level, but
        // that default is invisible to this in-memory instance until a
        // fresh reload (same class of bug as CreatePageAction's status
        // default) — set explicitly rather than relying on it.
        $item->target ??= '_self';
        $item->created_by = $actor->getKey();
        $item->updated_by = $actor->getKey();
        $item->save();

        $this->auditLog->record($actor, 'menu_item.created', $item, [
            'label' => $item->label,
            'menu' => $menu->name,
        ]);

        return $item;
    }
}
