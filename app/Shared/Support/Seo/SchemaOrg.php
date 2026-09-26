<?php

namespace App\Shared\Support\Seo;

/**
 * Small schema.org JSON-LD builders shared by controller-rendered listing
 * and detail pages (Blog, Services). Pass the result to SeoTagBuilder as
 * $fallbacks['structured_data'] (a full {"@context", "@graph"} document) or
 * as one node inside such a graph.
 */
class SchemaOrg
{
    /**
     * A BreadcrumbList node; "Home" is prepended automatically.
     *
     * @param  array<int, array{0: string, 1: string}>  $crumbs  [label, url] pairs after Home
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        $items = [['Home', route('home')], ...$crumbs];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * A full document: CollectionPage (+ optional ItemList) + BreadcrumbList.
     *
     * @param  array<int, array{0: string, 1: string}>  $crumbs
     * @param  array<int, array{0: string, 1: string}>  $items  [name, url] pairs listed on the page
     * @return array<string, mixed>
     */
    public static function collectionPage(string $name, string $url, array $crumbs, array $items = []): array
    {
        $page = ['@type' => 'CollectionPage', 'name' => $name, 'url' => $url];

        if ($items !== []) {
            $page['mainEntity'] = [
                '@type' => 'ItemList',
                'itemListElement' => array_map(fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item[0],
                    'url' => $item[1],
                ], $items, array_keys($items)),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [$page, self::breadcrumbs($crumbs)],
        ];
    }

    /**
     * A FAQPage node, or null when there are no complete question/answer pairs.
     *
     * @param  array<int, array{question?: string, answer?: string}>|null  $faqs
     * @return array<string, mixed>|null
     */
    public static function faqPage(?array $faqs): ?array
    {
        $entities = collect($faqs ?? [])
            ->filter(fn (array $faq): bool => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ])
            ->values()
            ->all();

        return $entities === [] ? null : ['@type' => 'FAQPage', 'mainEntity' => $entities];
    }
}
