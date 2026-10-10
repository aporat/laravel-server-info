<?php

namespace Aporat\ServerInfo\Facades;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Report;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ModuleRegistry extend(class-string<ModuleInterface> $class)
 * @method static ModuleRegistry register(ModuleInterface $module)
 * @method static array<string, ModuleInterface> modules()
 * @method static Report collect(?string $only = null)
 * @method static array<string, array<string, mixed>> all()
 * @method static array<string, mixed> flat()
 *
 * @see ModuleRegistry
 */
class ServerInfo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleRegistry::class;
    }
}
