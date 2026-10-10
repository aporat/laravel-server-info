<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use RuntimeException;

class ThrowingModule implements ModuleInterface
{
    public function name(): string
    {
        return 'broken';
    }

    public function info(): array
    {
        throw new RuntimeException('module exploded');
    }
}
