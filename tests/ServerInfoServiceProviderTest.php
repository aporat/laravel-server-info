<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\ServerInfoServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServerInfoServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ServerInfoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('server-info.modules', [
            PhpModule::class,
        ]);
    }

    #[Test]
    public function test_service_provider_boots(): void
    {
        $this->assertTrue(class_exists(ServerInfoServiceProvider::class));
    }

    #[Test]
    public function test_php_module_is_loaded_through_registry(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);
        $data = $registry->all();

        $this->assertArrayHasKey('php.version', $data);
        $this->assertSame(PHP_VERSION, $data['php.version']);
    }
}
