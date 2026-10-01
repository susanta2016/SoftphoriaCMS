<?php

/**
 * UTM channels offered in Admin → UTM Links (utm_source value => label and
 * default utm_medium). Add a channel here — no migration needed; the admin
 * form's "Custom" option covers one-off sources without a code change.
 */
return [
    'channels' => [
        'apple_music' => ['label' => 'Apple Music', 'medium' => 'music'],
        'podcast' => ['label' => 'Podcast', 'medium' => 'podcast'],
        'instagram' => ['label' => 'Instagram', 'medium' => 'social'],
        'facebook' => ['label' => 'Facebook', 'medium' => 'social'],
        'email' => ['label' => 'Email', 'medium' => 'email'],
    ],
];
