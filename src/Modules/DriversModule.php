<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Illuminate\Contracts\Config\Repository;

/**
 * The default driver/store names of Laravel's services. Only names are
 * reported, never connection settings.
 */
class DriversModule implements ModuleInterface
{
    public function __construct(protected Repository $config) {}

    public function name(): string
    {
        return 'drivers';
    }

    public function info(): array
    {
        return [
            'cache' => $this->string('cache.default'),
            'queue' => $this->string('queue.default'),
            'session' => $this->string('session.driver'),
            'mail' => $this->string('mail.default'),
            'filesystem' => $this->string('filesystems.default'),
            'broadcast' => $this->string('broadcasting.default'),
            'log' => $this->string('logging.default'),
            'database' => $this->string('database.default'),
        ];
    }

    protected function string(string $key): ?string
    {
        $value = $this->config->get($key);

        return is_scalar($value) ? (string) $value : null;
    }
}
