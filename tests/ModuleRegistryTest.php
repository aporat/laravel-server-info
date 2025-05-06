<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_register_and_retrieve_scalar_module()
    {
        $module = new class implements ModuleInterface
        {
            public function name(): string
            {
                return 'scalar';
            }

            public function info(): string
            {
                return 'value';
            }
        };

        $registry = new ModuleRegistry;
        $registry->register($module);

        $result = $registry->all();

        $this->assertArrayHasKey('scalar', $result);
        $this->assertEquals('value', $result['scalar']);
    }

    public function test_register_and_retrieve_array_module()
    {
        $module = new class implements ModuleInterface
        {
            public function name(): string
            {
                return 'env';
            }

            public function info(): array
            {
                return ['php' => '8.4', 'laravel' => '12.x'];
            }
        };

        $registry = new ModuleRegistry;
        $registry->register($module);

        $result = $registry->all();

        $this->assertArrayHasKey('env.php', $result);
        $this->assertArrayHasKey('env.laravel', $result);
        $this->assertEquals('8.4', $result['env.php']);
        $this->assertEquals('12.x', $result['env.laravel']);
    }
}
