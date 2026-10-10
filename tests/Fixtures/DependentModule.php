<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Illuminate\Contracts\Config\Repository;

class DependentModule implements ModuleInterface
{
    public function __construct(private Repository $config) {}

    public function name(): string
    {
        return 'dependent';
    }

    public function info(): array
    {
        return ['app_name' => $this->config->get('app.name')];
    }
}
