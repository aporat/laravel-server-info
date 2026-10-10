<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

use Aporat\ServerInfo\Contracts\ModuleInterface;

/**
 * A module whose name and info are chosen by the test.
 */
class StaticModule implements ModuleInterface
{
    /**
     * @param  array<string, mixed>  $info
     */
    public function __construct(private string $name = 'static', private array $info = ['value' => 'x']) {}

    public function name(): string
    {
        return $this->name;
    }

    public function info(): array
    {
        return $this->info;
    }

    /**
     * Static-method-style entry that must never be called.
     */
    public static function make(): self
    {
        $GLOBALS['server_info_side_effect'] = true;

        return new self;
    }
}
