<?php

namespace App\Actions\Reaction;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared toggle entry point for any reactable model (Track, PodcastEpisode,
 * PoetryProse) — no existing reaction from this user on this item creates
 * one; an existing one removes it. This single action is the only place a
 * Reaction row is ever created or deleted, called by each module's own
 * thin ReactionController (mirrors App\Actions\Review\SubmitReviewAction's
 * shared-action-per-thin-controller shape). The `reactions` table's own
 * unique index on (reactable_type, reactable_id, user_id) is the
 * server-side backstop against a double-click/retry race creating two rows
 * for the same user on the same item.
 */
class ToggleReactionAction
{
    /**
     * @return bool true when a reaction now exists (just created), false when it was just removed
     */
    public function handle(Model $reactable, User $user): bool
    {
        $existing = Reaction::query()
            ->where('reactable_type', $reactable->getMorphClass())
            ->where('reactable_id', $reactable->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        Reaction::query()->create([
            'reactable_type' => $reactable->getMorphClass(),
            'reactable_id' => $reactable->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return true;
    }
}
