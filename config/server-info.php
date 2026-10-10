<?php

use Aporat\ServerInfo\Modules\DatabaseModule;
use Aporat\ServerInfo\Modules\DiskModule;
use Aporat\ServerInfo\Modules\DriversModule;
use Aporat\ServerInfo\Modules\LaravelModule;
use Aporat\ServerInfo\Modules\PackagesModule;
use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\Modules\RedisModule;
use Aporat\ServerInfo\Modules\SystemModule;

return [

    /*
    |--------------------------------------------------------------------------
    | Server Info Modules
    |--------------------------------------------------------------------------
    |
    | Class names of the modules to load, in display order. Each one must
    | implement Aporat\ServerInfo\Contracts\ModuleInterface and is resolved
    | through the service container (so constructor dependencies are
    | injected) only when server info is requested.
    |
    | Only class names are allowed, so this file can always be cached with
    | `php artisan config:cache`. To add modules from a package or a service
    | provider, call ServerInfo::extend(MyModule::class) or tag the class with
    | 'server-info.modules'.
    |
    */

    'modules' => [
        PhpModule::class,
        LaravelModule::class,
        SystemModule::class,
        DiskModule::class,
        DriversModule::class,
        DatabaseModule::class,
        RedisModule::class,
        PackagesModule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Disk Module
    |--------------------------------------------------------------------------
    |
    | Paths whose filesystem usage is reported, as label => path. When null,
    | the storage path and the base path are reported.
    |
    */

    'disk' => [
        'paths' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Module
    |--------------------------------------------------------------------------
    |
    | Connection names (from config/database.php) to probe. When null, only
    | the default connection is probed. Each probe opens its own connection
    | with the given timeout in seconds. Credentials are never reported.
    |
    */

    'database' => [
        'connections' => null,
        'timeout' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Module
    |--------------------------------------------------------------------------
    |
    | Redis connection names (from config/database.php) to probe. When null,
    | the "default" connection is probed only if the default cache store,
    | queue connection, session driver or broadcaster uses Redis.
    |
    */

    'redis' => [
        'connections' => null,
        'timeout' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Packages Module
    |--------------------------------------------------------------------------
    |
    | Composer packages whose installed version is reported, in addition to
    | the root package.
    |
    */

    'packages' => [
        'laravel/framework',
    ],

];
