<?php

namespace App\Shared\Support\Notifications;

use App\Shared\Services\Notifications\NewContentAlerter;
use Illuminate\Database\Eloquent\Model;

/**
 * Hooks `saved` rather than each Create/Update Action so every path that
 * publishes the item — admin create, admin edit, the publish-due scheduler
 * commands, admin "Add Resource" — is covered by one mechanism. Firing on
 * every save of visible content is intentional: NewContentAlerter's
 * new_content_alerts claim makes all but the first a no-op, so edits to
 * already-announced content never re-send.
 *
 * @mixin Model
 */
trait AlertsMembersWhenPublished
{
    public static function bootAlertsMembersWhenPublished(): void
    {
        static::saved(function (Model&AnnouncesNewContent $content): void {
            if ($content->isMemberVisible()) {
                app(NewContentAlerter::class)->announce($content);
            }
        });
    }
}
