<x-filament-panels::topbar.item
    :url="url('/')"
    icon="heroicon-o-home"
    :should-open-url-in-new-tab="true"
>
    {{ __('View Site') }}
</x-filament-panels::topbar.item>

@livewire(\App\Filament\Livewire\SystemToolsMenu::class)
