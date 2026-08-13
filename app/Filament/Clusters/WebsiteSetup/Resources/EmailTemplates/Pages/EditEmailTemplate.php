<?php

namespace App\Filament\Clusters\WebsiteSetup\Resources\EmailTemplates\Pages;

use App\Enums\EmailRecipientType;
use App\Filament\Clusters\WebsiteSetup\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Shared\Services\Notifications\TemplatedMailer;
use App\Shared\Services\Settings\SettingsRepository;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * The record this page binds to (via the standard {record} route
 * parameter) is always the `user`-variant EmailTemplate row for a
 * notification_key (docs/ARCHITECTURE.md §16.5) — every currently seeded
 * key has one (see config/email_templates.php). The "Admin" tab, shown only
 * when this key also has an admin-variant row, edits that sibling row
 * within the same screen: the same flatten-on-fill/split-on-save pattern
 * already established for Pages' seo.* fields (see PageForm/EditPage) —
 * not a new convention.
 *
 * Each tab lays its own fields out beside a live Preview panel (subject +
 * rendered HTML body) for that same tab's content — so switching tabs
 * switches which variant's preview is shown, without needing to track
 * "which tab is active" server-side. The preview substitutes readable
 * sample values for {{variables}} via TemplatedMailer::substitute() — the
 * exact same substitution real sends use — never Blade/PHP execution; it
 * exists purely for layout/formatting review, not proof of the exact
 * recipient content.
 */
class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $adminVariant = $this->adminVariant();

        $data['admin'] = $adminVariant ? [
            'is_enabled' => $adminVariant->is_enabled,
            'subject' => $adminVariant->subject,
            'html_body' => $adminVariant->html_body,
            'text_body' => $adminVariant->text_body,
        ] : [];

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $adminVariant = $this->adminVariant();

        // defaultForm() (Filament\Resources\Pages\EditRecord) silently
        // applies ->columns(2) to the root schema unless it already
        // ->hasCustomColumns() — halving the width available to the Grid
        // below (documented pitfall, docs/ARCHITECTURE.md §13/§16.5: "don't
        // repeat it" — repeated it here until caught in review). ->columns(1)
        // here is what makes the Tabs component actually use the page's
        // full width instead of one half of an invisible 2-column split.
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('EmailTemplateTabs')
                    ->tabs(array_filter([
                        Tab::make('User')->schema($this->variantSchema('')),
                        $adminVariant ? Tab::make('Admin')->schema($this->variantSchema('admin.')) : null,
                    ])),
            ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var EmailTemplate $record */
        $record->is_enabled = $data['is_enabled'];
        $record->subject = $data['subject'];
        $record->html_body = $data['html_body'];
        $record->text_body = $data['text_body'];
        $record->updated_by = Auth::id();
        $record->save();

        $adminVariant = $this->adminVariant();

        if ($adminVariant && array_key_exists('admin', $data)) {
            $adminVariant->is_enabled = $data['admin']['is_enabled'];
            $adminVariant->subject = $data['admin']['subject'];
            $adminVariant->html_body = $data['admin']['html_body'];
            $adminVariant->text_body = $data['admin']['text_body'];
            $adminVariant->updated_by = Auth::id();
            $adminVariant->save();
        }

        return $record;
    }

    /**
     * @return array<int, Component>
     */
    private function variantSchema(string $prefix): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    Section::make('Edit')
                        ->schema([
                            Placeholder::make("{$prefix}available_variables_display")
                                ->label('Available Variables')
                                ->content(fn (): HtmlString => new HtmlString(
                                    collect($this->record->available_variables ?? [])
                                        ->map(fn (string $variable): string => '<code>{{'.e($variable).'}}</code>')
                                        ->join(' ') ?: '<span style="color:#6b7280">None</span>'
                                )),
                            Toggle::make("{$prefix}is_enabled")->label('Enabled'),
                            TextInput::make("{$prefix}subject")
                                ->label('Subject')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: false),
                            Textarea::make("{$prefix}html_body")
                                ->label('HTML Body')
                                ->required()
                                ->rows(10)
                                ->live(onBlur: false),
                            Textarea::make("{$prefix}text_body")
                                ->label('Plain-Text Fallback')
                                ->rows(6)
                                ->helperText('Optional. Sent as the plain-text alternative alongside the HTML body.'),
                        ]),
                    Section::make('Preview')
                        ->description('Sample values in place of {{variables}} — for layout review only, not the exact recipient content.')
                        ->schema([
                            Placeholder::make("{$prefix}preview_subject")
                                ->label('Subject')
                                ->content(fn (Get $get): string => $this->renderPreviewSubject($get, $prefix)),
                            Placeholder::make("{$prefix}preview_body")
                                ->label('Body')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->renderPreviewBody($get, $prefix)),
                        ]),
                ]),
        ];
    }

    private function renderPreviewSubject(Get $get, string $prefix): string
    {
        $subject = (string) ($get("{$prefix}subject") ?? '');

        return TemplatedMailer::substitute($subject, $this->sampleVariables());
    }

    private function renderPreviewBody(Get $get, string $prefix): HtmlString
    {
        $html = (string) ($get("{$prefix}html_body") ?? '');
        $rendered = TemplatedMailer::substitute($html, $this->sampleVariables());

        return new HtmlString(
            '<div style="border:1px solid #e5e7eb;border-radius:0.375rem;padding:1rem;background:#fff;max-height:24rem;overflow-y:auto">'
            .$rendered
            .'</div>'
        );
    }

    /**
     * Readable placeholder values for every {{variable}} this key's
     * available_variables list can contain — used only to make the preview
     * legible, never sent to a real recipient.
     *
     * @return array<string, string>
     */
    private function sampleVariables(): array
    {
        $siteName = (string) app(SettingsRepository::class)->get('general', 'site_name', config('app.name'));

        $known = [
            'site_name' => $siteName,
            'user_name' => 'Jane Doe',
            'user_email' => 'jane@example.com',
            'subscriber_email' => 'jane@example.com',
            'verification_url' => url('/#sample-verification-link'),
            'reset_url' => url('/#sample-reset-link'),
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Sample subject line',
            'message' => 'This is a sample message body used for preview purposes only.',
        ];

        $variables = collect($this->record->available_variables ?? [])
            ->mapWithKeys(fn (string $variable): array => [$variable => $known[$variable] ?? '['.$variable.']'])
            ->all();

        return $variables + ['site_name' => $siteName];
    }

    private function adminVariant(): ?EmailTemplate
    {
        /** @var EmailTemplate $record */
        $record = $this->record;

        return EmailTemplate::query()
            ->where('notification_key', $record->notification_key)
            ->where('recipient_type', EmailRecipientType::Admin->value)
            ->first();
    }
}
