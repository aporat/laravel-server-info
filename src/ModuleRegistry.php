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
     * Container tag for module classes registered by other packages:
     *
     *     $this->app->tag([MyModule::class], ModuleRegistry::TAG);
     */
    public const string TAG = 'server-info.modules';

    /**
     * Module instances registered directly.
     *
     * @var list<ModuleInterface>
     */
    protected array $modules = [];

    /**
     * Module class names registered with extend(), resolved only when the
     * registry is queried.
     *
     * @var list<class-string<ModuleInterface>>
     */
    protected array $extensions = [];

    /**
     * @param  Container|null  $container  Resolves module classes, reads the
     *                                     `server-info.modules` config and the
     *                                     tagged modules. Without one, classes are
     *                                     instantiated with `new`.
     */
    public function __construct(protected ?Container $container = null) {}

    /**
     * Registers an already-built module.
     */
    public function register(ModuleInterface $module): static
    {
        $this->modules[] = $module;

        return $this;
    }

    /**
     * Registers a module class lazily: it is resolved through the container
     * only when the registry is queried. Safe to call from a service
     * provider's boot() (e.g. via the ServerInfo facade).
     *
     * @param  class-string<ModuleInterface>  $class
     */
    public function extend(string $class): static
    {
        $this->extensions[] = $class;

        return $this;
    }

    /**
     * Resolves every module, in this order: the `server-info.modules` config,
     * modules tagged with {@see self::TAG}, extend() classes, register()
     * instances.
     *
     * @return array<string, ModuleInterface> keyed by module name
     *
     * @throws InvalidArgumentException for an entry that is not a module class
     *                                  (including a closure in the config), or
     *                                  a module name that is empty, contains a
     *                                  dot, or is already taken
     */
    public function modules(): array
    {
        $modules = [];

        $candidates = [
            ...array_map(fn ($entry) => $this->resolve($entry, 'the server-info.modules config'), $this->configEntries()),
            ...$this->tagged(),
            ...array_map(fn ($entry) => $this->resolve($entry, 'ModuleRegistry::extend()'), $this->extensions),
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
     * Collects info from every module, or only the named one. A module whose
     * info() throws is recorded as an error in the report; the other modules
     * still run.
     *
     * @throws InvalidArgumentException when a module cannot be resolved (see
     *                                  modules()) or $only names no module
     */
    public function collect(?string $only = null): Report
    {
        $modules = $this->modules();

        if ($only !== null) {
            if (! isset($modules[$only])) {
                throw new InvalidArgumentException(sprintf('No server info module is named "%s".', $only));
            }

            $modules = [$modules[$only]];
        }

        return Report::collect($modules);
    }

    /**
     * Info from every module, nested by module name:
     * ['php' => ['version' => '8.4.0', ...], ...].
     *
     * Unlike collect(), the first exception thrown by a module is rethrown.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $report = $this->collect();

        foreach ($report->errors() as $error) {
            throw $error;
        }

        return $report->data();
    }

    /**
     * Info from every module as flat "module.key" => value pairs (the v1
     * output format). Rethrows module exceptions like all().
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        $report = $this->collect();

        foreach ($report->errors() as $error) {
            throw $error;
        }

        return $report->flat();
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

    /**
     * @return list<ModuleInterface>
     */
    protected function tagged(): array
    {
        if ($this->container === null) {
            return [];
        }

        $modules = [];

        foreach ($this->container->tagged(self::TAG) as $module) {
            if (! $module instanceof ModuleInterface) {
                throw new InvalidArgumentException(sprintf(
                    'A service tagged [%s] resolved to %s, which does not implement %s.',
                    self::TAG,
                    get_debug_type($module),
                    ModuleInterface::class
                ));
            }

            $modules[] = $module;
        }

        return $modules;
    }

    protected function resolve(mixed $entry, string $source): ModuleInterface
    {
        if ($entry instanceof Closure) {
            throw new InvalidArgumentException(sprintf(
                'Closures are no longer supported in %s because they prevent `php artisan config:cache`. '
                .'List the module class name instead, or register the module from a service provider with '
                .'ServerInfo::extend(MyModule::class) or $this->app->tag([MyModule::class], \'%s\'). '
                .'See the upgrade guide in the aporat/laravel-server-info README.',
                $source,
                self::TAG
            ));
        }

        if (! is_string($entry)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid entry in %s: expected a module class name, %s given.',
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
