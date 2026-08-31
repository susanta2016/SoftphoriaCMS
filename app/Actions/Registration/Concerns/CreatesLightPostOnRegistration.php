<?php

namespace App\Actions\Registration\Concerns;

use App\Models\User;

/**
 * Shared by Free/Pro registration — creates the visitor's first Light Post
 * (the "Leave a Little Light ✨" registration prompt) only when they
 * explicitly chose "Share My Light" (light_post_action === 'share') and
 * actually wrote something. "Share Another Time" (or a blank message even
 * with 'share' selected) creates nothing — an empty Light Post is never
 * created either way. Always public, per the prompt's own copy telling the
 * visitor up front the post will be shared publicly.
 */
trait CreatesLightPostOnRegistration
{
    /**
     * @param  array{light_post_action?: ?string, light_message?: ?string}  $data
     */
    protected function createLightPostIfRequested(User $user, array $data): void
    {
        if (($data['light_post_action'] ?? null) !== 'share') {
            return;
        }

        $content = trim((string) ($data['light_message'] ?? ''));

        if ($content === '') {
            return;
        }

        $user->lightPosts()->create([
            'content' => $content,
            'is_public' => true,
        ]);
    }
}
