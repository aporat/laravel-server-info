<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;

class LaravelModule implements ModuleInterface
{
    public function name(): string
    {
        return 'laravel';
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return [
            'version' => app()->version(),
            'env' => app()->environment(),
            'debug' => config('app.debug') ? 'true' : 'false',
            'name' => config('app.name'),
            'timezone' => config('app.timezone'),
        ];
    }
}
