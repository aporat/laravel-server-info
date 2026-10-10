<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;

/**
 * A module whose name and info are chosen by the test.
 */
class StaticModule implements ModuleInterface
{
    public function __construct(private string $name = 'static', private mixed $info = 'value') {}

    public function name(): string
    {
        return $this->name;
    }

    public function info(): mixed
    {
        return $this->info;
    }

    /**
     * Global-function-style and static-method-style entries that must never be called.
     */
    public static function make(): self
    {
        $GLOBALS['server_info_side_effect'] = true;

        return new self;
    }
}
