<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Actions\Menu\UpdateMenuAction;
use App\Filament\Resources\Menus\MenuResource;
use App\Models\MenuItem;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * The items Repeater isn't a Filament relationship-repeater (see
     * MenuForm's docblock), so its state has to be filled in manually.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items()
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'label' => $item->label,
                'destination_type' => $item->destination_type->value,
                'page_id' => $item->page_id,
                'route_key' => $item->route_key,
                'url' => $item->url,
                'target' => $item->target,
                'parent_id' => $item->parent_id,
                'is_enabled' => $item->is_enabled,
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = Auth::user();

        return app(UpdateMenuAction::class)->handle($record, $data, $actor);
    }
}
