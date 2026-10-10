<?php

namespace Aporat\ServerInfo;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ModuleRegistry
{
    /**
     * Module instances registered directly.
     *
     * @var ModuleInterface[]
     */
    protected array $modules = [];

    /**
     * Module class names or factory closures registered with extend(),
     * resolved only when the registry is queried.
     *
     * @var array<int, class-string<ModuleInterface>|Closure>
     */
    protected array $extensions = [];

    /**
     * @param  Container|null  $container  Resolves module classes and reads the
     *                                     `server-info.modules` config. Without one,
     *                                     classes are instantiated with `new` and
     *                                     the config is not read.
     */
    public function __construct(protected ?Container $container = null) {}

    /**
     * Registers an already-built module.
     */
    public function register(ModuleInterface $module): void
    {
        $this->modules[] = $module;
    }

    /**
     * Registers a module lazily, by class name or by a closure returning a
     * module. Nothing is constructed until the registry is queried.
     *
     * This is the config-cache-safe way to add modules from a service provider:
     *
     *     $this->callAfterResolving(ModuleRegistry::class,
     *         fn (ModuleRegistry $registry) => $registry->extend(MyModule::class));
     *
     * @param  class-string<ModuleInterface>|Closure  $module
     */
    public function extend(string|Closure $module): static
    {
        $this->extensions[] = $module;

        return $this;
    }

    /**
     * Resolves every module: the `server-info.modules` config entries, then
     * extend() entries, then register() instances.
     *
     * @return array<string, ModuleInterface> keyed by module name
     *
     * @throws InvalidArgumentException for an entry that is not a module, or a
     *                                  module name that is empty, contains a dot,
     *                                  or is already taken
     */
    public function modules(): array
    {
        $modules = [];

        $candidates = [
            ...array_map(fn ($entry) => $this->resolve($entry, 'server-info.modules'), $this->configEntries()),
            ...array_map(fn ($entry) => $this->resolve($entry, 'extend()'), $this->extensions),
            ...$this->modules,
        ];

        foreach ($candidates as $module) {
            $name = $module->name();

            if ($name === '' || str_contains($name, '.')) {
                throw new InvalidArgumentException(sprintf(
                    'Server info module [%s] has an invalid name "%s": names must be non-empty and must not contain a dot.',
                    $module::class,
                    $name
                ));
            }

            if (isset($modules[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'Server info module name "%s" is used by both [%s] and [%s].',
                    $name,
                    $modules[$name]::class,
                    $module::class
                ));
            }

            $modules[$name] = $module;
        }

        return $modules;
    }

    /**
     * Retrieves and aggregates information from all modules.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->modules() as $name => $module) {
            $info = $module->info();

            if (is_array($info)) {
                foreach ($info as $key => $value) {
                    $result[$name.'.'.$key] = $value;
                }
            } else {
                $result[$name] = $info;
            }
        }

        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function configEntries(): array
    {
        if ($this->container === null || ! $this->container->bound('config')) {
            return [];
        }

        $config = $this->container->make('config');

        if (! $config instanceof Repository) {
            return [];
        }

        $entries = $config->get('server-info.modules', []);

        if ($entries === null) {
            return [];
        }

        if (! is_array($entries)) {
            throw new InvalidArgumentException(sprintf(
                'The server-info.modules config must be an array of module class names, %s given.',
                get_debug_type($entries)
            ));
        }

        return $entries;
    }

    protected function resolve(mixed $entry, string $source): ModuleInterface
    {
        if ($entry instanceof Closure) {
            $module = $this->container !== null ? $this->container->call($entry) : $entry();

            if (! $module instanceof ModuleInterface) {
                throw new InvalidArgumentException(sprintf(
                    'A closure in %s returned %s instead of an implementation of %s.',
                    $source,
                    get_debug_type($module),
                    ModuleInterface::class
                ));
            }

            return $module;
        }

        if (! is_string($entry)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid entry in %s: expected a module class name or a Closure, %s given.',
                $source,
                get_debug_type($entry)
            ));
        }

        if (! class_exists($entry)) {
            throw new InvalidArgumentException(sprintf(
                'Server info module class [%s] listed in %s does not exist.',
                $entry,
                $source
            ));
        }

        if (! is_subclass_of($entry, ModuleInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Server info module class [%s] listed in %s does not implement %s.',
                $entry,
                $source,
                ModuleInterface::class
            ));
        }

        return $this->container !== null ? $this->container->make($entry) : new $entry;
    }
}
