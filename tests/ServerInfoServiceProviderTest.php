<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Facades\ServerInfo;
use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\Tests\Fixtures\CountingModule;
use Aporat\ServerInfo\Tests\Fixtures\DependentModule;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Aporat\ServerInfo\Tests\Fixtures\TaggedModule;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class ServerInfoServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        CountingModule::$constructed = 0;
        parent::setUp();
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

        $this->assertSame(PHP_VERSION, $data['php']['version']);
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
        $this->expectExceptionMessage('Server info module class [App\\ServerInfo\\Typo] listed in the server-info.modules config does not exist.');

        $registry->modules();
    }

    #[Test]
    public function modules_are_resolved_through_the_container(): void
    {
        config()->set('server-info.modules', [DependentModule::class]);
        config()->set('app.name', 'Probe App');

        $this->assertSame(['dependent' => ['app_name' => 'Probe App']], $this->app->make(ModuleRegistry::class)->all());
    }

    #[Test]
    public function a_closure_in_the_config_is_rejected_with_a_migration_hint(): void
    {
        config()->set('server-info.modules', [fn () => new StaticModule]);

        try {
            $this->app->make(ModuleRegistry::class)->modules();
            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Closures are no longer supported in the server-info.modules config', $e->getMessage());
            $this->assertStringContainsString('ServerInfo::extend(MyModule::class)', $e->getMessage());
            $this->assertStringContainsString("tag([MyModule::class], 'server-info.modules')", $e->getMessage());
            $this->assertStringContainsString('upgrade guide', $e->getMessage());
        }
    }

    #[Test]
    public function a_non_string_config_entry_is_rejected(): void
    {
        config()->set('server-info.modules', [new StaticModule]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entry in the server-info.modules config: expected a module class name, '.StaticModule::class.' given.');

        $this->app->make(ModuleRegistry::class)->modules();
    }

    #[Test]
    public function a_null_modules_config_means_no_config_modules(): void
    {
        config()->set('server-info.modules', null);
        $registry = $this->app->make(ModuleRegistry::class)->extend(StaticModule::class);

        $this->assertSame(['static' => ['value' => 'x']], $registry->all());
    }

    #[Test]
    public function a_non_array_modules_config_is_rejected(): void
    {
        config()->set('server-info.modules', PhpModule::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The server-info.modules config must be an array of module class names, string given.');

        $this->app->make(ModuleRegistry::class)->modules();
    }

    #[Test]
    public function modules_can_be_added_with_the_facade_from_a_service_provider(): void
    {
        $this->app->register(new class($this->app) extends ServiceProvider
        {
            public function boot(): void
            {
                ServerInfo::extend(DependentModule::class);
            }
        });

        $this->assertSame(0, CountingModule::$constructed, 'extend() must not build modules');
        $this->assertSame(['php', 'counting', 'dependent'], array_keys(ServerInfo::modules()));
    }

    #[Test]
    public function modules_can_be_added_with_a_container_tag(): void
    {
        $this->app->tag([TaggedModule::class], 'server-info.modules');
        ServerInfo::extend(StaticModule::class);

        $this->assertSame(['php', 'counting', 'tagged', 'static'], array_keys(ServerInfo::modules()));
        $this->assertSame(['source' => 'tag'], ServerInfo::collect('tagged')->data()['tagged']);
    }

    #[Test]
    public function the_facade_resolves_the_registry_singleton(): void
    {
        $this->assertSame($this->app->make(ModuleRegistry::class), ServerInfo::getFacadeRoot());
    }

    #[Test]
    public function the_shipped_config_is_cacheable_and_loads_every_module(): void
    {
        // `php artisan config:cache` writes the config with var_export() and
        // requires it back; anything that does not survive that round trip
        // (closures, objects) makes the command fail.
        $shipped = require __DIR__.'/../config/server-info.php';
        $this->assertEquals($shipped, eval('return '.var_export($shipped, true).';'));

        config()->set('server-info', $shipped);

        $this->assertSame(
            ['php', 'laravel', 'system', 'disk', 'drivers', 'database', 'redis', 'packages'],
            array_keys($this->app->make(ModuleRegistry::class)->modules())
        );
    }

    #[Test]
    public function the_package_config_defaults_are_merged(): void
    {
        $this->assertSame(2, config('server-info.database.timeout'));
        $this->assertNull(config('server-info.database.connections'));
        $this->assertSame(['laravel/framework'], config('server-info.packages'));
    }
}
