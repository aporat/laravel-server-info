<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Info Modules
    |--------------------------------------------------------------------------
    |
    | Define the list of modules to load. Each one must implement
    | Aporat\ServerInfo\Contracts\ModuleInterface.
    |
    | You can pass closures if you need to inject custom config or dependencies.
    |
    */

    'modules' => [
        Aporat\ServerInfo\Modules\PhpModule::class,
    ],

];
