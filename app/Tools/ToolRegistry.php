<?php

namespace App\Tools;

use Illuminate\Support\Collection;
use ReflectionClass;

/**
 * The tool functionalities available in this deployment, discovered from
 * app/Tools/Functionalities — every concrete ToolFunctionality there is
 * registered automatically, so a newly deployed tool shows up in the admin
 * without editing any list. Bound as a singleton.
 */
class ToolRegistry
{
    /** @var array<string, ToolFunctionality>|null */
    private ?array $functionalities = null;

    /** @var array<string, ToolFunctionality> */
    private array $extra = [];

    /**
     * @return Collection<string, ToolFunctionality>
     */
    public function all(): Collection
    {
        $this->functionalities ??= $this->discover();

        return collect([...$this->functionalities, ...$this->extra])->sortBy(fn (ToolFunctionality $f): string => $f->name());
    }

    public function find(?string $key): ?ToolFunctionality
    {
        return $key === null ? null : $this->all()->get($key);
    }

    public function has(?string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->all()->keys()->all();
    }

    /**
     * key => "Name (v1.0.0)" for the admin selector.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->all()->map(fn (ToolFunctionality $f): string => "{$f->name()} (v{$f->version()})")->all();
    }

    /** Registers a functionality outside the discovered folder (tests). */
    public function register(ToolFunctionality $functionality): void
    {
        $this->extra[$functionality->key()] = $functionality;
    }

    /**
     * @return array<string, ToolFunctionality>
     */
    private function discover(): array
    {
        $found = [];

        foreach (glob(app_path('Tools/Functionalities/*.php')) ?: [] as $file) {
            $class = 'App\\Tools\\Functionalities\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, ToolFunctionality::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $functionality = app($class);

            if ($functionality->key() !== '') {
                $found[$functionality->key()] = $functionality;
            }
        }

        return $found;
    }
}
