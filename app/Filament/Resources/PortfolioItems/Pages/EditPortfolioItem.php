<?php

namespace App\Filament\Resources\PortfolioItems\Pages;

use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Filament\Support\Seo\SavesSeoMetadata;
use App\Models\PortfolioItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditPortfolioItem extends EditRecord
{
    use SavesSeoMetadata;

    protected static string $resource = PortfolioItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Save changes')->formId('form'),
            $this->getCancelFormAction(),
            // Admins can also open an unpublished project — it renders as a
            // noindex preview only they can see.
            Action::make('view')
                ->label('View page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (PortfolioItem $record): string => $record->url(), shouldOpenInNewTab: true),
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillSeo($data, $this->record->seo, 'portfolio/'.$this->record->slug);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->pullSeo($data);
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->persistSeo($this->record, 'portfolio/'.$this->record->slug);
    }
}
