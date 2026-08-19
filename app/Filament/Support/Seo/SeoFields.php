<?php

namespace App\Filament\Support\Seo;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * ADMIN-006 review fix — the reusable convention every Admin module using
 * seo_metadata (Database Specification §18.6) must build its SEO fields
 * with, instead of ad hoc TextInput/Textarea maxLength calls per module.
 * See docs/ARCHITECTURE.md §15. Meta title/description limits are enforced
 * both client-side (native maxlength + a live "n/limit" counter) and
 * server-side (Filament's ->maxLength() adds the matching Laravel `max:`
 * validation rule, checked by the same schema on submit).
 */
class SeoFields
{
    public const META_TITLE_MAX = 60;

    public const META_DESCRIPTION_MAX = 160;

    public static function metaTitle(string $name = 'seo.meta_title'): TextInput
    {
        return TextInput::make($name)
            ->label('Meta Title')
            ->maxLength(self::META_TITLE_MAX)
            ->live(onBlur: false)
            ->hint(fn (?string $state): string => strlen($state ?? '').'/'.self::META_TITLE_MAX.' characters')
            ->hintColor(fn (?string $state): string => strlen($state ?? '') > self::META_TITLE_MAX ? 'danger' : 'gray');
    }

    public static function metaDescription(string $name = 'seo.meta_description'): Textarea
    {
        return Textarea::make($name)
            ->label('Meta Description')
            ->rows(2)
            ->maxLength(self::META_DESCRIPTION_MAX)
            ->live(onBlur: false)
            ->hint(fn (?string $state): string => strlen($state ?? '').'/'.self::META_DESCRIPTION_MAX.' characters')
            ->hintColor(fn (?string $state): string => strlen($state ?? '') > self::META_DESCRIPTION_MAX ? 'danger' : 'gray');
    }

    /**
     * Modern search engines no longer use this for ranking, but it's kept
     * for the crawlers/tools/legacy integrations that still read it —
     * cheap to offer, never required.
     */
    public static function metaKeywords(string $name = 'seo.keywords'): TextInput
    {
        return TextInput::make($name)
            ->label('Meta Keywords')
            ->maxLength(255)
            ->helperText('Optional, comma-separated. Ignored by Google/Bing ranking today — kept for other tools that still read it.');
    }

    /**
     * Never hardcodes the domain — always built from config('app.url'), the
     * application's own configured base URL.
     */
    public static function autoCanonicalUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public static function isCanonicalUrlAuto(?string $storedCanonicalUrl, string $autoPath): bool
    {
        return blank($storedCanonicalUrl) || $storedCanonicalUrl === self::autoCanonicalUrl($autoPath);
    }

    /**
     * Called from the record's own slug/title field(s) — never overwrites a
     * manually-overridden canonical URL, tracked via the sibling
     * "*_is_auto" state produced by canonicalUrlFields() below.
     */
    public static function syncCanonicalUrlIfAuto(Set $set, Get $get, string $canonicalName, string $isAutoName, string $path): void
    {
        if ($get($isAutoName) === false) {
            return;
        }

        $set($canonicalName, self::autoCanonicalUrl($path));
    }

    /**
     * Returns the Hidden "is auto" tracker, the canonical URL TextInput, and
     * a separate keyed Actions row for the reset button — matching the
     * codebase's established convention (docs/ARCHITECTURE.md §13: give a
     * record-scoped action its own ->key() rather than a bare suffixAction,
     * since that is what callFormComponentAction()/assertFormComponentAction*
     * can address in tests).
     *
     * @return array<int, Component>
     */
    public static function canonicalUrlFields(string $name, string $isAutoName, Closure $autoPathUsing): array
    {
        // Action names are matched by Filament's testing/resolution helpers
        // on a dot-separated path (action.nestedModalAction) — a literal
        // dot in the action's own name (e.g. from "seo.canonical_url")
        // breaks that lookup, so it's stripped here rather than in $name.
        $actionKey = str_replace('.', '_', $name);

        return [
            Hidden::make($isAutoName)->default(true)->dehydrated(false),
            TextInput::make($name)
                ->label('Canonical URL')
                ->url()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set) => $set($isAutoName, false))
                ->helperText(fn (Get $get): string => $get($isAutoName) === false
                    ? 'Manually overridden — use the action below to return to the automatic URL.'
                    : 'Automatically generated from the page URL.'),
            Actions::make([
                Action::make("{$actionKey}_reset")
                    ->label('Use automatic URL')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (Get $get): bool => $get($isAutoName) === false)
                    ->action(function (Set $set, Get $get) use ($name, $isAutoName, $autoPathUsing): void {
                        $set($name, self::autoCanonicalUrl($autoPathUsing($get)));
                        $set($isAutoName, true);
                    }),
            ])->key("{$actionKey}_reset_actions"),
        ];
    }
}
