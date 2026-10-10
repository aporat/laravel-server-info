<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\DatabaseModule;
use Aporat\ServerInfo\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class DatabaseModuleTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'memory');
        $app['config']->set('database.connections.memory', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.missing_file', [
            'driver' => 'sqlite',
            'database' => '/nonexistent/secret-dir/hunter2.sqlite',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.remote', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'app',
            'username' => 'admin-user',
            'password' => 'hunter2',
        ]);
    }

    #[Test]
    public function it_probes_the_default_connection(): void
    {
        $module = $this->app->make(DatabaseModule::class);

        $this->assertSame('database', $module->name());
        $this->assertSame([
            'memory' => [
                'driver' => 'sqlite',
                'status' => 'ok',
                'version' => DB::connection('memory')->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            ],
        ], $module->info());
    }

    #[Test]
    public function the_probe_does_not_touch_the_application_connections(): void
    {
        $this->app->make(DatabaseModule::class)->info();

        $this->assertSame([], array_filter(
            array_keys(DB::getConnections()),
            fn ($name) => str_starts_with($name, 'server-info-probe-')
        ));
    }

    #[Test]
    public function unreachable_connections_are_reported_without_secrets(): void
    {
        config()->set('server-info.database.connections', ['memory', 'missing_file', 'remote', 'undefined']);

        $info = $this->app->make(DatabaseModule::class)->info();

        $this->assertSame('ok', $info['memory']['status']);

        $this->assertSame('sqlite', $info['missing_file']['driver']);
        $this->assertSame('unreachable', $info['missing_file']['status']);
        $this->assertSame('SQLiteDatabaseDoesNotExistException', $info['missing_file']['error']);

        $this->assertSame('mysql', $info['remote']['driver']);
        $this->assertSame('unreachable', $info['remote']['status']);
        $this->assertMatchesRegularExpression('/^\w+( \[\w+\])?$/', $info['remote']['error']);

        $this->assertSame(['status' => 'not configured'], $info['undefined']);

        $encoded = json_encode($info, JSON_THROW_ON_ERROR);
        foreach (['hunter2', 'admin-user', '127.0.0.1', 'secret-dir'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    #[Test]
    public function a_missing_default_connection_gives_an_empty_report(): void
    {
        config()->set('database.default', null);

        $this->assertSame([], $this->app->make(DatabaseModule::class)->info());
    }

    #[Test]
    public function the_probe_timeout_is_configurable(): void
    {
        config()->set('server-info.database.timeout', 'nonsense');

        $this->assertSame('ok', $this->app->make(DatabaseModule::class)->info()['memory']['status']);
    }
}
