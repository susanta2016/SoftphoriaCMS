<?php

namespace App\Modules\Newsletter\Filament\Resources\Subscribers\Pages;

use App\Models\User;
use App\Modules\Newsletter\Actions\Subscribers\ImportSubscribersAction;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Filament\Resources\Subscribers\NewsletterSubscriberResource;
use App\Modules\Newsletter\Models\NewsletterGroup;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction(),
            CreateAction::make(),
        ];
    }

    /**
     * CSV import (ImportSubscribersAction). The upload goes to the private
     * local disk and is deleted straight after the import, whatever happens.
     */
    private function importAction(): Action
    {
        return Action::make('import')
            ->label('Import CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalDescription('A CSV with a header row: email (required), first_name, last_name. Existing unsubscribed or suppressed addresses are skipped, never resubscribed.')
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->disk('local')
                    ->directory('newsletter-imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(10240)
                    ->required(),
                Select::make('groups')
                    ->label('Add everyone to these groups')
                    ->multiple()
                    ->options(fn (): array => NewsletterGroup::query()->orderBy('name')->pluck('name', 'id')->all()),
                TextInput::make('source')
                    ->default('import')
                    ->maxLength(50),
                Checkbox::make('consent_confirmed')
                    ->label('Everyone in this file has agreed to receive the newsletter')
                    ->accepted(),
            ])
            ->action(function (array $data, Action $action): void {
                /** @var User $admin */
                $admin = Auth::user();
                $path = (string) $data['file'];

                try {
                    $result = app(ImportSubscribersAction::class)->handle(
                        $admin,
                        Storage::disk('local')->path($path),
                        $data['groups'] ?? [],
                        (bool) ($data['consent_confirmed'] ?? false),
                        filled($data['source'] ?? null) ? (string) $data['source'] : 'import',
                    );
                } catch (NewsletterException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                } finally {
                    Storage::disk('local')->delete($path);
                }

                Notification::make()
                    ->title('Import finished')
                    ->body("{$result['created']} added, {$result['updated']} updated, {$result['skipped']} skipped (unsubscribed or suppressed), {$result['invalid']} invalid.")
                    ->success()
                    ->send();
            });
    }
}
