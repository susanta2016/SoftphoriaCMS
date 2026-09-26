<?php

use App\Filament\Pages\BlogSettings;
use App\Filament\Pages\ServiceSettings;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogComments\BlogCommentResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogReactions\BlogReactionResource;
use App\Filament\Resources\BlogTags\BlogTagResource;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Testimonials\TestimonialResource;

return [
    /*
    |--------------------------------------------------------------------------
    | Frontend Features (Admin → Website Setup → Features Activation)
    |--------------------------------------------------------------------------
    |
    | The registry of switchable frontend modules and features. The on/off
    | state itself lives in the `settings` table (group "features"), edited on
    | the Features Activation page; `default` applies until an admin saves.
    | Checked at runtime through App\Shared\Support\Features\Features —
    | never read these keys directly.
    |
    | - requires:   other feature keys that must be on for this one to work.
    |               A feature whose requirement is off is treated as off.
    | - toggleable: false for configuration-only rows (always available).
    | - edit:       the Filament Page/Resource class its "Edit" button opens.
    | - links:      site paths (wildcards allowed) belonging to the feature —
    |               menu items pointing at them are hidden while it is off.
    |
    | Switching a feature off only affects the public website: its pages
    | 404, its links/sections disappear, its endpoints refuse requests.
    | Admin screens stay available so content can be prepared in advance.
    |
    */

    'groups' => [
        'blog' => [
            'label' => 'Blog System',
            'features' => [
                'blog.settings' => [
                    'label' => 'Blog Settings',
                    'description' => 'Blog configuration including layout, card styles, and display options.',
                    'toggleable' => false,
                    'edit' => BlogSettings::class,
                ],
                'blog.posts' => [
                    'label' => 'Blog Posts',
                    'description' => 'Blog post creation and management, the public /blog pages, RSS feed and the homepage "Latest Insights" section.',
                    'default' => true,
                    'edit' => BlogPostResource::class,
                    'links' => ['/blog', '/blog/*', '/#insights'],
                ],
                'blog.categories' => [
                    'label' => 'Blog Categories',
                    'description' => 'Blog category organization, with an indexable archive page per category.',
                    'default' => true,
                    'requires' => ['blog.posts'],
                    'edit' => BlogCategoryResource::class,
                ],
                'blog.tags' => [
                    'label' => 'Blog Tags',
                    'description' => 'Blog post tagging system, with a tag archive page per tag.',
                    'default' => true,
                    'requires' => ['blog.posts'],
                    'edit' => BlogTagResource::class,
                ],
                'blog.comments' => [
                    'label' => 'Blog Comments',
                    'description' => 'Logged-in members can comment on posts; comments publish instantly.',
                    'default' => true,
                    'requires' => ['blog.posts'],
                    'edit' => BlogCommentResource::class,
                ],
                'blog.comment_reports' => [
                    'label' => 'Comment Reporting',
                    'description' => 'Members can flag a comment; reported comments are marked for review so you can verify or revoke them.',
                    'default' => true,
                    'requires' => ['blog.comments'],
                    'edit' => BlogCommentResource::class,
                ],
                'blog.reactions' => [
                    'label' => 'Emoji Reactions',
                    'description' => 'A reaction bar (👍 ❤️ 🔥 👏 💡) under each post. Guests see the counts; members can react.',
                    'default' => true,
                    'requires' => ['blog.posts'],
                    'edit' => BlogReactionResource::class,
                ],
            ],
        ],

        'services' => [
            'label' => 'Services',
            'features' => [
                'services.settings' => [
                    'label' => 'Services Settings',
                    'description' => 'Services landing page copy, SEO and the call to action on every service page.',
                    'toggleable' => false,
                    'edit' => ServiceSettings::class,
                ],
                'services' => [
                    'label' => 'Services Pages',
                    'description' => 'The /services landing page, a detail page per service, and the homepage Services section.',
                    'default' => true,
                    'edit' => ServiceResource::class,
                    'links' => ['/services', '/services/*', '/#services'],
                ],
            ],
        ],

        'website' => [
            'label' => 'Website',
            'features' => [
                'portfolio' => [
                    'label' => 'Portfolio',
                    'description' => 'The homepage Featured Portfolio section and the /portfolio page.',
                    'default' => true,
                    'edit' => PortfolioItemResource::class,
                    'links' => ['/portfolio', '/#portfolio'],
                ],
                'testimonials' => [
                    'label' => 'Testimonials',
                    'description' => 'The testimonials slider section.',
                    'default' => true,
                    'edit' => TestimonialResource::class,
                    'links' => ['/#testimonials'],
                ],
                'newsletter' => [
                    'label' => 'Newsletter Signup',
                    'description' => 'The footer and blog newsletter signup forms.',
                    'default' => true,
                    'edit' => NewsletterSubscriberResource::class,
                ],
                'contact_popup' => [
                    'label' => 'Contact Popup',
                    'description' => 'Call-to-action links to the Contact page ("Start a Conversation", "Let\'s Talk"…) open a quick contact form in a popup. Menu links still open the Contact page.',
                    'default' => true,
                    'edit' => ContactRequestResource::class,
                ],
                'contact_widget' => [
                    'label' => 'Contact Widget',
                    'description' => 'The site-wide "Contact Us" tab on the right edge of every page.',
                    'default' => true,
                ],
            ],
        ],
    ],
];
