<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;

class CountingModule implements ModuleInterface
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function name(): string
    {
        return 'counting';
    }

    public function info(): array
    {
        return ['constructed' => self::$constructed];
    }
}
