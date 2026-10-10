<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\Support\Bytes;

class PhpModule implements ModuleInterface
{
    public function name(): string
    {
        return 'php';
    }

    public function info(): array
    {
        $extensions = get_loaded_extensions();
        natcasesort($extensions);

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => $this->ini('memory_limit'),
            'max_execution_time' => (int) $this->ini('max_execution_time'),
            'upload_max_filesize' => $this->ini('upload_max_filesize'),
            'post_max_size' => $this->ini('post_max_size'),
            'timezone' => date_default_timezone_get(),
            'opcache' => $this->opcache(),
            'extensions' => array_values($extensions),
        ];
    }

    protected function ini(string $option): ?string
    {
        $value = ini_get($option);

        return $value === false ? null : $value;
    }

    /**
     * @return array<string, bool|string>
     */
    protected function opcache(): array
    {
        if (! extension_loaded('Zend OPcache')) {
            return ['enabled' => false];
        }

        // opcache.restrict_api can make this warn and return false.
        $status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        if (! is_array($status)) {
            $setting = PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable';

            return ['enabled' => filter_var(ini_get($setting), FILTER_VALIDATE_BOOL)];
        }

        $memory = is_array($status['memory_usage'] ?? null) ? $status['memory_usage'] : [];
        $info = ['enabled' => (bool) ($status['opcache_enabled'] ?? false)];

        if (isset($memory['used_memory']) && is_numeric($memory['used_memory'])) {
            $info['memory_used'] = Bytes::format((float) $memory['used_memory']);
        }

        if (isset($memory['free_memory']) && is_numeric($memory['free_memory'])) {
            $info['memory_free'] = Bytes::format((float) $memory['free_memory']);
        }

        $jit = is_array($status['jit'] ?? null) ? $status['jit'] : [];
        $info['jit'] = (bool) ($jit['on'] ?? false);

        return $info;
    }
}
