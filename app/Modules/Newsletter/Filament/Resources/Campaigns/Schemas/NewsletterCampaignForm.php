<?php

namespace App\Modules\Newsletter\Filament\Resources\Campaigns\Schemas;

use App\Modules\Newsletter\Actions\Campaigns\PrepareCampaignContent;
use App\Modules\Newsletter\Enums\TemplateStatus;
use App\Modules\Newsletter\Filament\Resources\Templates\NewsletterTemplateResource;
use App\Modules\Newsletter\Filament\Support\NewsletterPreviewActions;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterGroup;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\NewsletterSettings;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\SenderIdentity;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * The campaign wizard (create and edit-draft): Details, Template &
 * Content, Recipients, Preview. Scheduling / Send Now — step 5 — happen on
 * the campaign page afterwards, behind a confirmation summary, so nothing
 * is ever sent by saving this form.
 */
class NewsletterCampaignForm
{
    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        $settings = app(NewsletterSettings::class);

        return [
            Step::make('Details')
                ->description('Name, subject, sender')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Campaign name')
                        ->helperText('Internal only — subscribers never see it.')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    TextInput::make('subject')
                        ->required()
                        ->maxLength(200)
                        ->rules(['not_regex:/[\r\n]/', NewsletterTemplateResource::knownPlaceholders()])
                        ->helperText('Can be personalized, e.g. "{{ subscriber.first_name | "Friend" }}, our October news".')
                        ->columnSpanFull(),
                    TextInput::make('preheader')
                        ->maxLength(255)
                        ->helperText('Shown after the subject in most inboxes.')
                        ->columnSpanFull(),
                    TextInput::make('from_name')
                        ->label('Sender name')
                        ->default($settings->fromName())
                        ->required()
                        ->maxLength(100),
                    TextInput::make('from_email')
                        ->label('Sender email')
                        ->email()
                        ->default($settings->fromEmail())
                        ->required()
                        ->maxLength(255)
                        ->rules([self::verifiedSender()])
                        ->helperText(fn (): string => 'Must use a verified domain: '.(implode(', ', app(SenderIdentity::class)->allowedDomains()) ?: 'none configured').'.'),
                ]),

            Step::make('Template & content')
                ->description('Layout and this campaign\'s own content')
                ->schema([
                    Select::make('newsletter_template_id')
                        ->label('Email template')
                        ->options(fn (): array => NewsletterTemplate::query()->where('status', TemplateStatus::Active->value)->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            $template = $state ? NewsletterTemplate::query()->find($state) : null;

                            if ($template !== null && blank($get('preheader')) && filled($template->preheader)) {
                                $set('preheader', $template->preheader);
                            }
                        })
                        ->helperText(new HtmlString('Content edited here never changes the template itself. <a href="'.e(NewsletterTemplateResource::getUrl('create')).'" target="_blank" rel="noopener" class="underline">Create a new template</a> (opens in a new tab).')),
                    RichEditor::make('content')
                        ->label('Campaign content')
                        ->helperText('Inserted where the template has {{ campaign_content }}. Placeholders such as {{ subscriber.first_name }} work here too.')
                        ->required()
                        ->columnSpanFull(),
                    Section::make('Call to action')
                        ->columns(2)
                        ->compact()
                        ->schema([
                            TextInput::make('cta_text')->label('Button text')->maxLength(100),
                            TextInput::make('cta_url')
                                ->label('Button link')
                                ->url()
                                ->maxLength(2048)
                                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (filled($value) && ! PrepareCampaignContent::isSafeUrl((string) $value)) {
                                        $fail('Use a full http:// or https:// link.');
                                    }
                                }])
                                ->requiredWith('cta_text'),
                        ]),
                    Section::make('Tracking')
                        ->columns(2)
                        ->compact()
                        ->schema([
                            Toggle::make('track_opens')
                                ->default($settings->trackOpensByDefault())
                                ->helperText('Adds an invisible image. Approximate — many mail apps block or pre-load images.'),
                            Toggle::make('track_clicks')
                                ->default($settings->trackClicksByDefault())
                                ->helperText('Links go through this site first to be counted. Only links in this email can be redirected to.'),
                        ]),
                ]),

            Step::make('Recipients')
                ->description('Who receives it')
                ->schema([
                    CheckboxList::make('included_groups')
                        ->label('Send to these groups')
                        ->options(fn (): array => self::groupOptions())
                        ->required()
                        ->columns(2)
                        ->live()
                        ->bulkToggleable(),
                    CheckboxList::make('excluded_groups')
                        ->label('But not to anyone in these groups')
                        ->options(fn (): array => self::groupOptions())
                        ->columns(2)
                        ->live(),
                    TextEntry::make('recipient_estimate')
                        ->label('Eligible recipients')
                        ->state(fn (Get $get): string => self::estimate((array) $get('included_groups'), (array) $get('excluded_groups')))
                        ->helperText('Each address counted once, however many groups it is in. Unsubscribed, suppressed and invalid addresses are left out — and everyone is checked again at the moment of sending.'),
                ]),

            Step::make('Preview')
                ->description('Check before saving')
                ->schema([
                    View::make('newsletter-marketing::filament.preview')
                        ->viewData(fn (Get $get): array => NewsletterPreviewActions::render(self::draftFromState($get))),
                    TextEntry::make('after_save')
                        ->hiddenLabel()
                        ->state('Saving keeps this as a draft. From the campaign page you can preview with a real subscriber, send a test email, then schedule it or send it now.'),
                ]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function groupOptions(): array
    {
        return NewsletterGroup::query()
            ->withCount(['subscribers as sendable_count' => fn ($query) => $query->sendable()])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (NewsletterGroup $group): array => [$group->id => "{$group->name} ({$group->sendable_count})"])
            ->all();
    }

    /**
     * @param  array<int, int|string>  $included
     * @param  array<int, int|string>  $excluded
     */
    private static function estimate(array $included, array $excluded): string
    {
        if ($included === []) {
            return 'Select at least one group.';
        }

        return number_format(app(RecipientResolver::class)->count($included, $excluded)).' eligible recipient(s)';
    }

    /**
     * An unsaved campaign carrying the wizard's current values, for the
     * Preview step.
     */
    private static function draftFromState(Get $get): NewsletterCampaign
    {
        $campaign = new NewsletterCampaign([
            'name' => $get('name'),
            'subject' => (string) $get('subject'),
            'preheader' => $get('preheader'),
            'from_name' => (string) $get('from_name'),
            'from_email' => (string) $get('from_email'),
            'newsletter_template_id' => $get('newsletter_template_id'),
            'content' => is_string($get('content')) ? $get('content') : '',
            'cta_text' => $get('cta_text'),
            'cta_url' => $get('cta_url'),
        ]);

        return $campaign;
    }

    private static function verifiedSender(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (($problem = app(SenderIdentity::class)->validate((string) $value)) !== null) {
                $fail($problem);
            }
        };
    }
}
