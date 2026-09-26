<?php

namespace App\Tools;

/**
 * One tool's working functionality — trusted application code, shipped
 * through Git like everything else, never uploaded through the admin.
 *
 * Adding a tool:
 *   1. app/Tools/Functionalities/MyTool.php extending this class (the
 *      registry discovers it automatically — no list to edit);
 *   2. its markup in resources/views/tools/functionalities/{key}.blade.php;
 *   3. optionally its script in resources/js/tools/{key}.js (Vite builds
 *      every file in that folder as its own entry, loaded only on this
 *      tool's page).
 * The tool then appears in Admin → Tools → Functionality, where its landing
 * page content, SEO and publication are managed separately.
 *
 * Bump VERSION when the behaviour changes; the admin list shows it.
 */
abstract class ToolFunctionality
{
    /** Stable identifier stored on tools.functionality — never change it once used. */
    public const KEY = '';

    public const NAME = '';

    public const VERSION = '1.0.0';

    /** One line for the admin, describing what the functionality does. */
    public const DESCRIPTION = '';

    /**
     * schema.org applicationCategory for the page's WebApplication data,
     * e.g. BusinessApplication, DeveloperApplication, MultimediaApplication.
     */
    public const APPLICATION_CATEGORY = 'UtilitiesApplication';

    public function key(): string
    {
        return static::KEY;
    }

    public function name(): string
    {
        return static::NAME;
    }

    public function version(): string
    {
        return static::VERSION;
    }

    public function description(): string
    {
        return static::DESCRIPTION;
    }

    public function applicationCategory(): string
    {
        return static::APPLICATION_CATEGORY;
    }

    /** The Blade view rendering the tool's interface. */
    public function view(): string
    {
        return 'tools.functionalities.'.$this->key();
    }

    /**
     * Vite entry points loaded on this tool's page only.
     *
     * @return list<string>
     */
    public function assets(): array
    {
        $script = 'resources/js/tools/'.$this->key().'.js';

        return file_exists(base_path($script)) ? [$script] : [];
    }

    /**
     * Extra data passed to the view.
     *
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        return [];
    }
}
