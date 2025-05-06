<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;

class PhpModule implements ModuleInterface
{
    public function name(): string
    {
        return 'php';
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'extensions' => get_loaded_extensions(),
        ];
    }
}
