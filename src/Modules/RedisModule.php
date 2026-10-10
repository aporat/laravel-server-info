<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\Support\Errors;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\RedisManager;
use Predis\Client as PredisClient;
use Throwable;

/**
 * Redis server version and mode, from INFO server.
 *
 * By default (`server-info.redis.connections` = null) the "default" Redis
 * connection is probed only when the cache, queue, session or broadcast
 * default uses Redis. Probes use their own short-lived client with a short
 * timeout. Only the client name, server version/mode and a redacted error
 * (exception class and code) are reported: never hosts, passwords or URLs.
 */
class RedisModule implements ModuleInterface
{
    public function __construct(
        protected Application $app,
        protected Repository $config,
    ) {}

    public function name(): string
    {
        return 'redis';
    }

    public function info(): array
    {
        $redis = $this->config->get('database.redis');

        if (! is_array($redis)) {
            return ['status' => 'not configured'];
        }

        $client = is_string($redis['client'] ?? null) ? $redis['client'] : 'phpredis';
        $info = ['client' => $client];

        $connections = $this->connections();

        if ($connections === []) {
            return $info + ['status' => 'not in use'];
        }

        $missing = $this->missingRequirement($client);

        if ($missing !== null) {
            return $info + ['status' => 'unavailable', 'reason' => $missing];
        }

        foreach ($connections as $name) {
            $info[$name] = $this->probe($redis, $client, $name);
        }

        return $info;
    }

    /**
     * @return list<string>
     */
    protected function connections(): array
    {
        $connections = $this->config->get('server-info.redis.connections');

        if (is_array($connections)) {
            return array_values(array_filter(
                $connections,
                fn ($name) => is_string($name) && ! in_array($name, ['', 'client', 'options', 'clusters'], true)
            ));
        }

        return $this->usedByDefaults() ? ['default'] : [];
    }

    /**
     * Whether the default cache store, queue connection, session driver or
     * broadcaster is backed by Redis.
     */
    protected function usedByDefaults(): bool
    {
        $drivers = [
            $this->config->get('cache.stores.'.$this->string('cache.default').'.driver'),
            $this->config->get('queue.connections.'.$this->string('queue.default').'.driver'),
            $this->config->get('session.driver'),
            $this->config->get('broadcasting.connections.'.$this->string('broadcasting.default').'.driver'),
        ];

        return in_array('redis', $drivers, true);
    }

    protected function missingRequirement(string $client): ?string
    {
        return match (true) {
            ! class_exists(RedisManager::class) => 'illuminate/redis is not installed',
            $client === 'phpredis' && ! extension_loaded('redis') => 'the phpredis extension is not loaded',
            $client === 'predis' && ! class_exists(PredisClient::class) => 'predis/predis is not installed',
            default => null,
        };
    }

    /**
     * @param  array<mixed>  $redis  the database.redis config
     * @return array<string, string|null>
     */
    protected function probe(array $redis, string $client, string $name): array
    {
        $connectionConfig = $redis[$name] ?? null;

        if (! is_array($connectionConfig)) {
            return ['status' => 'not configured'];
        }

        $timeout = $this->timeout();
        $connectionConfig = [
            ...$connectionConfig,
            'timeout' => $timeout,
            $client === 'predis' ? 'read_write_timeout' : 'read_timeout' => $timeout,
        ];

        $manager = new RedisManager($this->app, $client, [
            'options' => is_array($redis['options'] ?? null) ? $redis['options'] : [],
            $name => $connectionConfig,
        ]);

        $connection = null;

        try {
            $connection = $manager->connection($name);
            $server = $connection->command('info', ['server']);

            return [
                'status' => 'ok',
                'version' => $this->find($server, 'redis_version'),
                'mode' => $this->find($server, 'redis_mode'),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'unreachable',
                'error' => Errors::redacted($e),
            ];
        } finally {
            $this->close($manager, $connection, $name);
        }
    }

    /**
     * Finds a field in INFO output: a flat array (phpredis), an array grouped
     * by section (predis) or the raw "key:value" text.
     */
    protected function find(mixed $info, string $key): ?string
    {
        if (is_string($info)) {
            return preg_match('/^'.preg_quote($key, '/').':(.*)$/m', $info, $match) ? trim($match[1]) : null;
        }

        if (! is_array($info)) {
            return null;
        }

        if (isset($info[$key]) && is_scalar($info[$key])) {
            return (string) $info[$key];
        }

        foreach ($info as $value) {
            if (is_array($value) && ($found = $this->find($value, $key)) !== null) {
                return $found;
            }
        }

        return null;
    }

    protected function close(RedisManager $manager, ?Connection $connection, string $name): void
    {
        try {
            if ($connection instanceof PhpRedisConnection) {
                $connection->disconnect();
            } elseif ($connection !== null && is_object($client = $connection->client()) && method_exists($client, 'disconnect')) {
                $client->disconnect();
            }
        } catch (Throwable) {
            // Already closed.
        }

        $manager->purge($name);
    }

    protected function timeout(): float
    {
        $timeout = $this->config->get('server-info.redis.timeout', 2);

        return is_numeric($timeout) && $timeout > 0 ? (float) $timeout : 2.0;
    }

    protected function string(string $key): string
    {
        $value = $this->config->get($key);

        return is_string($value) ? $value : '';
    }
}
