<?php

namespace App\Modules\Newsletter\Actions\Campaigns;

use App\Modules\Newsletter\Enums\TemplateStatus;
use App\Modules\Newsletter\Exceptions\NewsletterException;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use App\Modules\Newsletter\Support\NewsletterRenderer;
use App\Modules\Newsletter\Support\RecipientResolver;
use App\Modules\Newsletter\Support\SenderIdentity;
use App\Modules\Newsletter\Support\TemplateVariables;

/**
 * Shared by Schedule and Send Now: the readiness checks, then the content
 * snapshot — the rendered HTML/text, tracked-link map and selected group
 * names the campaign will send from no matter what later happens to its
 * template or groups.
 */
class PrepareCampaignContent
{
    public function __construct(
        private readonly NewsletterRenderer $renderer,
        private readonly RecipientResolver $recipients,
        private readonly SenderIdentity $sender,
    ) {}

    /**
     * Everything stopping $campaign from being scheduled or sent.
     *
     * @return array<int, string>
     */
    public function problems(NewsletterCampaign $campaign): array
    {
        $problems = [];
        $template = $campaign->template;

        if ($template === null) {
            $problems[] = 'Choose an email template.';
        } elseif ($template->status !== TemplateStatus::Active) {
            $problems[] = "The template \"{$template->name}\" is not active.";
        }

        if (blank($campaign->subject)) {
            $problems[] = 'The subject is empty.';
        }

        if (($senderProblem = $this->sender->validate((string) $campaign->from_email)) !== null) {
            $problems[] = $senderProblem;
        }

        foreach (array_filter([$campaign->content, $campaign->subject, $template?->html_content]) as $content) {
            if (($unknown = TemplateVariables::unknownIn((string) $content)) !== []) {
                $problems[] = 'Unknown placeholder(s): '.implode(', ', $unknown).'.';
            }
        }

        if (filled($campaign->cta_url) && ! self::isSafeUrl((string) $campaign->cta_url)) {
            $problems[] = 'The call-to-action link must be a full http(s) URL.';
        }

        if ($campaign->includedGroups()->doesntExist()) {
            $problems[] = 'Select at least one subscriber group.';
        } elseif ($this->recipients->countForCampaign($campaign) === 0) {
            $problems[] = 'No eligible recipients: the selected groups have no active, unsuppressed subscribers.';
        }

        return array_values(array_unique($problems));
    }

    /**
     * @throws NewsletterException
     */
    public function snapshot(NewsletterCampaign $campaign): void
    {
        if (($problems = $this->problems($campaign)) !== []) {
            throw NewsletterException::invalid(implode(' ', $problems));
        }

        $rendered = $this->renderer->snapshot($campaign, $campaign->template);

        $campaign->forceFill([
            'rendered_html' => $rendered->html,
            'rendered_text' => $rendered->text,
            'tracked_links' => $rendered->links,
            'groups_snapshot' => [
                'included' => $campaign->includedGroups()->pluck('name')->all(),
                'excluded' => $campaign->excludedGroups()->pluck('name')->all(),
            ],
            'content_snapshot_at' => now(),
        ])->save();
    }

    public static function isSafeUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }
}
