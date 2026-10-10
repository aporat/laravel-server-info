<?php

use Aporat\ServerInfo\Modules\LaravelModule;
use Aporat\ServerInfo\Modules\PhpModule;

return [

    /*
    |--------------------------------------------------------------------------
    | Server Info Modules
    |--------------------------------------------------------------------------
    |
    | Class names of the modules to load. Each one must implement
    | Aporat\ServerInfo\Contracts\ModuleInterface and is resolved through the
    | service container (so constructor dependencies are injected) only when
    | server info is requested.
    |
    | Use class names here. Closures also work, but they make the config
    | impossible to cache (`php artisan config:cache` fails). To build a module
    | in code, call ModuleRegistry::extend() from a service provider instead.
    |
    */

    'modules' => [
        PhpModule::class,
        LaravelModule::class,
    ],

];
