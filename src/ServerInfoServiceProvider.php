<?php

namespace Aporat\ServerInfo;

use Aporat\ServerInfo\Console\ServerInfoCommand;
use Aporat\ServerInfo\Contracts\ModuleInterface;
use Illuminate\Support\ServiceProvider;

class ServerInfoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/server-info.php', 'server-info');

        // Delay registry instantiation until modules are available in boot()
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/server-info.php' => config_path('server-info.php'),
            ], 'config');

            $this->commands([
                ServerInfoCommand::class,
            ]);
        }

        $registry = $this->app->make(ModuleRegistry::class);

        foreach (config('server-info.modules', []) as $module) {
            $instance = is_callable($module) ? $module() : new $module;

            if ($instance instanceof ModuleInterface) {
                $registry->register($instance);
            }
        }
    }
}
