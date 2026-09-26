<?php

/*
|--------------------------------------------------------------------------
| "Specialised Expertise" spotlight section
|--------------------------------------------------------------------------
|
| A Services section (style "spotlight") highlighting AI Development, Cloud
| & DevOps and Digital Marketing. Shared by HomePageSeeder, AboutPageSeeder
| and the 2026_09_26_190000 migration so all three insert the same content.
| Editable afterwards in Admin → Pages.
|
*/

return [
    'section_type' => 'services',
    'title' => 'Specialised Expertise',
    'is_enabled' => true,
    'content_json' => [
        'style' => 'spotlight',
        'anchor' => 'specialised-expertise',
        'eyebrow' => 'Specialised Expertise',
        'heading' => "AI, cloud and growth —\nall under one roof.",
        'description' => 'Beyond websites and custom software, we help businesses adopt AI, run reliable cloud infrastructure and reach more customers online.',
        'link_label' => 'All Services',
        'link_url' => '/services',
        'item_link_label' => 'Explore service',
        'service_slugs' => ['ai-development', 'cloud-devops', 'digital-marketing'],
    ],
];
