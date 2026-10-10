<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

class LaravelModule implements ModuleInterface
{
    public function __construct(
        protected Application $app,
        protected Repository $config,
    ) {}

    public function name(): string
    {
        return 'laravel';
    }

    public function info(): array
    {
        return [
            'version' => $this->app->version(),
            'env' => $this->app->environment(),
            'debug' => (bool) $this->config->get('app.debug'),
            'name' => $this->string('app.name'),
            'timezone' => $this->string('app.timezone'),
            'locale' => $this->string('app.locale'),
            'maintenance_mode' => $this->app->isDownForMaintenance(),
            'config_cached' => $this->flag('configurationIsCached'),
            'routes_cached' => $this->flag('routesAreCached'),
            'events_cached' => $this->flag('eventsAreCached'),
        ];
    }

    protected function string(string $key): ?string
    {
        $value = $this->config->get($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Calls one of the concrete Application's *Cached() methods, which are not
     * part of the Application contract.
     */
    protected function flag(string $method): ?bool
    {
        return method_exists($this->app, $method) ? (bool) $this->app->{$method}() : null;
    }
}
