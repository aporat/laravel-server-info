<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\Support\Errors;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use PDO;
use Throwable;

/**
 * Driver, reachability and server version of the configured database
 * connections (`server-info.database.connections`, by default only the
 * default connection).
 *
 * Each connection is probed on a separate, short-lived connection with a
 * short timeout, so the application's own connections are untouched. Only
 * the driver name, the server version and a redacted error (exception class
 * and SQLSTATE/driver code) are reported: never hosts, usernames, passwords,
 * DSNs or exception messages.
 */
class DatabaseModule implements ModuleInterface
{
    public function __construct(
        protected Container $container,
        protected Repository $config,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function info(): array
    {
        if (! class_exists(DatabaseManager::class) || ! $this->container->bound('db')) {
            return ['status' => 'unavailable'];
        }

        $db = $this->container->make('db');

        if (! $db instanceof DatabaseManager) {
            return ['status' => 'unavailable'];
        }

        $info = [];

        foreach ($this->connections() as $name) {
            $info[$name] = $this->probe($db, $name);
        }

        return $info;
    }

    /**
     * @return list<string>
     */
    protected function connections(): array
    {
        $connections = $this->config->get('server-info.database.connections');

        if (! is_array($connections)) {
            $default = $this->config->get('database.default');

            return is_string($default) && $default !== '' ? [$default] : [];
        }

        return array_values(array_filter($connections, fn ($name) => is_string($name) && $name !== ''));
    }

    /**
     * @return array<string, string|null>
     */
    protected function probe(DatabaseManager $db, string $name): array
    {
        $config = $this->config->get('database.connections.'.$name);

        if (! is_array($config)) {
            return ['status' => 'not configured'];
        }

        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : null;
        $probe = 'server-info-probe-'.$name;

        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $options[PDO::ATTR_TIMEOUT] = $this->timeout();
        $config['options'] = $options;

        try {
            $connection = $db->connectUsing($probe, $config, force: true);

            if (! $connection instanceof Connection) {
                return ['driver' => $driver, 'status' => 'unknown'];
            }

            $version = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

            return [
                'driver' => $driver,
                'status' => 'ok',
                'version' => is_scalar($version) ? (string) $version : null,
            ];
        } catch (Throwable $e) {
            return [
                'driver' => $driver,
                'status' => 'unreachable',
                'error' => Errors::redacted($e),
            ];
        } finally {
            try {
                $db->purge($probe);
            } catch (Throwable) {
                // Nothing to clean up.
            }
        }
    }

    protected function timeout(): int
    {
        $timeout = $this->config->get('server-info.database.timeout', 2);

        return is_numeric($timeout) ? max(1, (int) $timeout) : 2;
    }
}
