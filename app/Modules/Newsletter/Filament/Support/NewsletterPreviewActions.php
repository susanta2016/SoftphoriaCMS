<?php

namespace App\Modules\Newsletter\Filament\Support;

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Modules\Newsletter\Actions\SendTestEmailAction;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use App\Modules\Newsletter\Support\NewsletterRenderer;
use App\Modules\Newsletter\Support\NewsletterSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Preview and Send Test actions shared by templates and campaigns. Both
 * render through NewsletterRenderer::preview() — the same pipeline a real
 * send uses, minus tracking — so what the admin sees is what goes out.
 */
final class NewsletterPreviewActions
{
    public static function preview(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Preview')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->schema([
                Select::make('subscriber_id')
                    ->label('Personalize as')
                    ->placeholder('Sample subscriber (Alex Sample)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => NewsletterSubscriber::query()
                        ->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('email', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => NewsletterSubscriber::query()->whereKey($value)->value('email'))
                    ->live(),
                View::make('newsletter-marketing::filament.preview')
                    ->viewData(fn (NewsletterCampaign|NewsletterTemplate $record, Get $get): array => self::render($record, $get('subscriber_id'))),
            ]);
    }

    public static function sendTest(): Action
    {
        return Action::make('sendTest')
            ->label('Send test email')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalDescription('Sends a [TEST] copy to the addresses below only — never to any subscriber list. Links are not tracked and the unsubscribe link does nothing.')
            ->fillForm(fn (): array => ['recipients' => array_filter([app(NewsletterSettings::class)->testRecipient() ?? Auth::user()?->email])])
            ->schema([
                TagsInput::make('recipients')
                    ->label('Send to')
                    ->placeholder('Add an email address')
                    ->nestedRecursiveRules(['email'])
                    ->required()
                    ->helperText('Up to '.SendTestEmailAction::MAX_RECIPIENTS.' addresses.'),
                Select::make('subscriber_id')
                    ->label('Personalize as')
                    ->placeholder('Sample subscriber (Alex Sample)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => NewsletterSubscriber::query()
                        ->where('email', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('email', 'id')
                        ->all()),
            ])
            ->action(function (NewsletterCampaign|NewsletterTemplate $record, array $data, Action $action): void {
                /** @var User $admin */
                $admin = Auth::user();

                try {
                    $count = app(SendTestEmailAction::class)->handle($record, $admin, (array) $data['recipients'], self::personalization($data['subscriber_id'] ?? null));
                } catch (NewsletterException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()->title("Test email sent to {$count} address(es).")->success()->send();
            });
    }

    /**
     * View data for newsletter-marketing::filament.preview. Also used by the
     * campaign wizard's Preview step with an unsaved campaign built from
     * the form state.
     *
     * @return array<string, mixed>
     */
    public static function render(NewsletterCampaign|NewsletterTemplate $record, mixed $subscriberId = null): array
    {
        $settings = app(NewsletterSettings::class);
        $renderer = app(NewsletterRenderer::class);

        [$campaign, $template] = $record instanceof NewsletterCampaign
            ? [$record, $record->template]
            : [SendTestEmailAction::sampleCampaign($record, $settings), $record];

        if ($template === null) {
            return ['html' => '<p style="font-family:sans-serif;padding:24px">Choose an email template to see a preview.</p>'];
        }

        $personalization = self::personalization($subscriberId) ?? NewsletterRenderer::sampleSubscriber();

        try {
            $email = $renderer->preview($campaign, $template, $personalization);
        } catch (Throwable $exception) {
            report($exception);

            return ['html' => '<p style="font-family:sans-serif;padding:24px">This email could not be rendered. Check the template HTML.</p>'];
        }

        return [
            'html' => $email->html,
            'text' => $email->text,
            'subject' => $renderer->subject($campaign, $personalization),
            'preheader' => $campaign->preheader,
            'from' => "{$campaign->from_name} <{$campaign->from_email}>",
            'links' => self::linksIn($email->html),
        ];
    }

    /**
     * @return array{first_name: ?string, last_name: ?string, email: string}|null
     */
    private static function personalization(mixed $subscriberId): ?array
    {
        $subscriber = filled($subscriberId) ? NewsletterSubscriber::query()->find($subscriberId) : null;

        return $subscriber === null ? null : [
            'first_name' => $subscriber->first_name,
            'last_name' => $subscriber->last_name,
            'email' => $subscriber->email,
        ];
    }

    /**
     * Every http(s) link in the email, for the "verify links" check.
     *
     * @return array<int, string>
     */
    private static function linksIn(string $html): array
    {
        preg_match_all('/href\s*=\s*"(https?:\/\/[^"]+)"/i', $html, $matches);

        return array_values(array_unique(array_map(fn (string $url): string => html_entity_decode($url), $matches[1])));
    }
}
