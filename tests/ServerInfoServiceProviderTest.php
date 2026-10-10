<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\ServerInfoServiceProvider;
use Aporat\ServerInfo\Tests\Fixtures\CountingModule;
use Aporat\ServerInfo\Tests\Fixtures\DependentModule;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServerInfoServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        CountingModule::$constructed = 0;
        parent::setUp();
    }

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
            CountingModule::class,
        ]);
    }

    #[Test]
    public function the_registry_is_a_singleton(): void
    {
        $this->assertSame($this->app->make(ModuleRegistry::class), $this->app->make(ModuleRegistry::class));
    }

    #[Test]
    public function the_php_module_is_loaded_through_the_registry(): void
    {
        $data = $this->app->make(ModuleRegistry::class)->all();

        $this->assertArrayHasKey('php.version', $data);
        $this->assertSame(PHP_VERSION, $data['php.version']);
    }

    #[Test]
    public function no_module_is_constructed_while_the_application_boots(): void
    {
        $this->assertTrue($this->app->isBooted());
        $this->assertSame(0, CountingModule::$constructed);

        $this->app->make(ModuleRegistry::class);
        $this->assertSame(0, CountingModule::$constructed, 'Resolving the registry must not build modules');

        $this->app->make(ModuleRegistry::class)->all();
        $this->assertSame(1, CountingModule::$constructed);
    }

    #[Test]
    public function a_missing_class_fails_only_when_queried_and_names_the_entry(): void
    {
        config()->set('server-info.modules', ['App\\ServerInfo\\Typo']);
        $registry = $this->app->make(ModuleRegistry::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Server info module class [App\\ServerInfo\\Typo] listed in server-info.modules does not exist.');

        $registry->all();
    }

    #[Test]
    public function modules_are_resolved_through_the_container(): void
    {
        config()->set('server-info.modules', [DependentModule::class]);
        config()->set('app.name', 'Probe App');

        $this->assertSame(['dependent.app_name' => 'Probe App'], $this->app->make(ModuleRegistry::class)->all());
    }

    #[Test]
    public function closures_in_config_still_work_and_can_use_injection(): void
    {
        config()->set('app.name', 'Injected');
        config()->set('server-info.modules', [
            fn (Repository $config) => new StaticModule('closure', $config->get('app.name')),
        ]);

        $this->assertSame(['closure' => 'Injected'], $this->app->make(ModuleRegistry::class)->all());
    }

    #[Test]
    public function a_null_modules_config_means_no_config_modules(): void
    {
        config()->set('server-info.modules', null);
        $registry = $this->app->make(ModuleRegistry::class)->extend(StaticModule::class);

        $this->assertSame(['static' => 'value'], $registry->all());
    }

    #[Test]
    public function a_non_array_modules_config_is_rejected(): void
    {
        config()->set('server-info.modules', PhpModule::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The server-info.modules config must be an array of module class names, string given.');

        $this->app->make(ModuleRegistry::class)->all();
    }

    #[Test]
    public function modules_can_be_added_from_a_service_provider_without_config(): void
    {
        config()->set('server-info.modules', [PhpModule::class]);
        $this->app->register(new class($this->app) extends ServiceProvider
        {
            public function boot(): void
            {
                $this->callAfterResolving(ModuleRegistry::class, fn (ModuleRegistry $registry) => $registry->extend(DependentModule::class));
            }
        });

        $data = $this->app->make(ModuleRegistry::class)->all();

        $this->assertArrayHasKey('php.version', $data);
        $this->assertArrayHasKey('dependent.app_name', $data);
    }

    #[Test]
    public function the_shipped_config_is_cacheable(): void
    {
        // `php artisan config:cache` writes the config with var_export() and
        // requires it back; anything that does not survive that round trip
        // (closures, objects) makes the command fail.
        $shipped = require __DIR__.'/../config/server-info.php';
        $this->assertEquals($shipped, eval('return '.var_export($shipped, true).';'));

        config()->set('server-info.modules', $shipped['modules']);
        $merged = config('server-info');
        $this->assertEquals($merged, eval('return '.var_export($merged, true).';'));

        $this->assertArrayHasKey('laravel.version', $this->app->make(ModuleRegistry::class)->all());
    }
}
