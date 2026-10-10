<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\Support\Bytes;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

/**
 * Free/used space of the filesystems holding the configured paths
 * (`server-info.disk.paths`, by default the storage and base paths).
 */
class DiskModule implements ModuleInterface
{
    public function __construct(
        protected Application $app,
        protected Repository $config,
    ) {}

    public function name(): string
    {
        return 'disk';
    }

    public function info(): array
    {
        $info = [];

        foreach ($this->paths() as $label => $path) {
            $info[$label] = $this->usage($path);
        }

        return $info;
    }

    /**
     * @return array<string, string> label => path
     */
    protected function paths(): array
    {
        $paths = $this->config->get('server-info.disk.paths');

        if (! is_array($paths)) {
            return [
                'storage' => $this->app->storagePath(),
                'base' => $this->app->basePath(),
            ];
        }

        $valid = [];

        foreach ($paths as $label => $path) {
            if (is_string($path) && $path !== '') {
                $valid[is_string($label) ? $label : $path] = $path;
            }
        }

        return $valid;
    }

    /**
     * @return array<string, int|float|string>
     */
    protected function usage(string $path): array
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return ['path' => $path, 'status' => 'unavailable'];
        }

        $used = max(0, $total - $free);

        return [
            'path' => $path,
            'total' => Bytes::format($total),
            'free' => Bytes::format($free),
            'used' => Bytes::format($used),
            'used_percent' => round($used / $total * 100, 1),
            'total_bytes' => (int) $total,
            'free_bytes' => (int) $free,
            'used_bytes' => (int) $used,
        ];
    }
}
