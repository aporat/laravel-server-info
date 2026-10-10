<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;

class TaggedModule implements ModuleInterface
{
    public function name(): string
    {
        return 'tagged';
    }

    public function info(): array
    {
        return ['source' => 'tag'];
    }
}
