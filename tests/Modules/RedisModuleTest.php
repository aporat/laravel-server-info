<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\RedisModule;
use Aporat\ServerInfo\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;

class RedisModuleTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.redis', [
            'client' => 'phpredis',
            'options' => ['prefix' => 'app:'],
            'default' => [
                'host' => '127.0.0.1',
                'port' => 1,
                'password' => 'hunter2',
                'username' => 'redis-admin',
                'database' => 0,
            ],
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('broadcasting.default', 'log');
    }

    private function info(): array
    {
        return $this->app->make(RedisModule::class)->info();
    }

    #[Test]
    public function redis_that_is_not_used_by_any_default_is_not_probed(): void
    {
        $module = $this->app->make(RedisModule::class);

        $this->assertSame('redis', $module->name());
        $this->assertSame(['client' => 'phpredis', 'status' => 'not in use'], $module->info());
    }

    #[Test]
    public function missing_redis_config_is_reported(): void
    {
        config()->set('database.redis', null);

        $this->assertSame(['status' => 'not configured'], $this->info());
    }

    #[Test]
    public function a_missing_predis_client_is_unavailable(): void
    {
        if (class_exists(Client::class)) {
            $this->markTestSkipped('predis/predis is installed');
        }

        config()->set('database.redis.client', 'predis');
        config()->set('cache.default', 'redis');
        config()->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'cache']);

        $this->assertSame([
            'client' => 'predis',
            'status' => 'unavailable',
            'reason' => 'predis/predis is not installed',
        ], $this->info());
    }

    #[Test]
    public function a_missing_phpredis_extension_is_unavailable(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is loaded');
        }

        config()->set('server-info.redis.connections', ['default']);

        $this->assertSame([
            'client' => 'phpredis',
            'status' => 'unavailable',
            'reason' => 'the phpredis extension is not loaded',
        ], $this->info());
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function redisDefaults(): array
    {
        return [
            'cache' => ['cache.default', 'redis'],
            'queue' => ['queue.default', 'redis'],
            'session' => ['session.driver', 'redis'],
            'broadcasting' => ['broadcasting.default', 'redis'],
        ];
    }

    #[Test]
    #[DataProvider('redisDefaults')]
    public function the_default_connection_is_probed_when_a_default_uses_redis(string $key, string $value): void
    {
        config()->set('database.redis.client', 'unsupported-client');
        config()->set('cache.stores.redis', ['driver' => 'redis']);
        config()->set('queue.connections.redis', ['driver' => 'redis']);
        config()->set('broadcasting.connections.redis', ['driver' => 'redis']);
        config()->set($key, $value);

        $info = $this->info();

        $this->assertArrayHasKey('default', $info, 'The default connection should have been probed');
        $this->assertSame('unreachable', $info['default']['status']);
    }

    #[Test]
    public function an_unreachable_server_is_reported_without_secrets(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not loaded');
        }

        config()->set('server-info.redis.connections', ['default', 'undefined', 'options']);
        config()->set('server-info.redis.timeout', 0.5);

        $info = $this->info();

        $this->assertSame('phpredis', $info['client']);
        $this->assertSame('unreachable', $info['default']['status']);
        $this->assertSame('RedisException', $info['default']['error']);
        $this->assertSame(['status' => 'not configured'], $info['undefined']);
        $this->assertArrayNotHasKey('options', $info);

        $encoded = json_encode($info, JSON_THROW_ON_ERROR);
        foreach (['hunter2', 'redis-admin', '127.0.0.1'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    #[Test]
    public function the_application_redis_manager_is_not_used(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is not loaded');
        }

        config()->set('server-info.redis.connections', ['default']);

        $this->info();

        $this->assertFalse($this->app->resolved('redis'), 'The probe must use its own RedisManager');
    }
}
