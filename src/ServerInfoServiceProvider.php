<?php

namespace Aporat\ServerInfo;

use Aporat\ServerInfo\Console\ServerInfoCommand;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class ServerInfoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/server-info.php', 'server-info');

        // Modules are read from config and constructed only when the registry
        // is queried, never during application boot.
        $this->app->singleton(ModuleRegistry::class, fn (Container $app) => new ModuleRegistry($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/server-info.php' => $this->app->configPath('server-info.php'),
            ], 'config');

            $this->commands([
                ServerInfoCommand::class,
            ]);
        }
    }
}
