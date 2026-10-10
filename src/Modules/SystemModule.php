<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\Support\Bytes;

/**
 * Operating system, CPU, load, uptime and container facts. Everything is read
 * from PHP functions and /proc or /sys files; no shell commands are run.
 * Values that cannot be read on this platform are null.
 */
class SystemModule implements ModuleInterface
{
    /**
     * cgroup v1 reports "no limit" as a huge page-aligned number.
     */
    protected const int CGROUP_UNLIMITED = 1 << 60;

    /**
     * @param  string  $root  Filesystem root for /proc, /sys and container
     *                        marker files (overridable for tests).
     */
    public function __construct(protected string $root = '/') {}

    public function name(): string
    {
        return 'system';
    }

    public function info(): array
    {
        $uptime = $this->uptimeSeconds();
        $memoryLimit = $this->cgroupMemoryLimit();

        return [
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'kernel' => php_uname('r'),
            'machine' => php_uname('m'),
            'hostname' => gethostname() ?: null,
            'cpus' => $this->cpuCount(),
            'load_average' => $this->loadAverage(),
            'uptime' => $uptime === null ? null : $this->duration($uptime),
            'uptime_seconds' => $uptime,
            'container' => $this->container(),
            'memory_limit' => match (true) {
                $memoryLimit === null => null,
                $memoryLimit === PHP_INT_MAX => 'unlimited',
                default => Bytes::format($memoryLimit),
            },
            'memory_limit_bytes' => $memoryLimit === PHP_INT_MAX ? null : $memoryLimit,
        ];
    }

    protected function cpuCount(): ?int
    {
        $cpuinfo = $this->read('proc/cpuinfo');

        if ($cpuinfo === null) {
            return null;
        }

        $count = preg_match_all('/^processor\s*:/mi', $cpuinfo);

        return $count > 0 ? $count : null;
    }

    /**
     * @return list<float>|null 1, 5 and 15 minute load averages
     */
    protected function loadAverage(): ?array
    {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;

        return is_array($load) ? array_map(fn ($value) => round((float) $value, 2), $load) : null;
    }

    protected function uptimeSeconds(): ?int
    {
        $uptime = $this->read('proc/uptime');

        if ($uptime === null || ! preg_match('/^\s*(\d+)(?:\.\d+)?\s/', $uptime.' ', $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * Best-effort container runtime detection: "docker", "podman",
     * "kubernetes", a runtime named in the cgroup file, or "none".
     */
    protected function container(): string
    {
        if (@is_file($this->path('.dockerenv'))) {
            return 'docker';
        }

        if (@is_file($this->path('run/.containerenv'))) {
            return 'podman';
        }

        // Presence only; the variable's value is never read into the output.
        if ($this->hasEnv('KUBERNETES_SERVICE_HOST')) {
            return 'kubernetes';
        }

        $cgroup = $this->read('proc/1/cgroup') ?? '';

        foreach (['kubepods' => 'kubernetes', 'docker' => 'docker', 'libpod' => 'podman', 'containerd' => 'containerd', 'lxc' => 'lxc'] as $needle => $runtime) {
            if (str_contains($cgroup, $needle)) {
                return $runtime;
            }
        }

        return 'none';
    }

    /**
     * The cgroup memory limit in bytes (v2 memory.max, then v1
     * memory.limit_in_bytes), PHP_INT_MAX for "no limit", or null when no
     * cgroup memory controller is visible.
     */
    protected function cgroupMemoryLimit(): ?int
    {
        $v2 = $this->read('sys/fs/cgroup/memory.max');

        if ($v2 !== null) {
            $v2 = trim($v2);

            if ($v2 === 'max') {
                return PHP_INT_MAX;
            }

            return ctype_digit($v2) ? (int) $v2 : null;
        }

        $v1 = $this->read('sys/fs/cgroup/memory/memory.limit_in_bytes');

        if ($v1 !== null && ctype_digit($v1 = trim($v1))) {
            $bytes = (int) $v1;

            return $bytes >= self::CGROUP_UNLIMITED ? PHP_INT_MAX : $bytes;
        }

        return null;
    }

    protected function duration(int $seconds): string
    {
        $parts = [
            'd' => intdiv($seconds, 86400),
            'h' => intdiv($seconds % 86400, 3600),
            'm' => intdiv($seconds % 3600, 60),
        ];

        $parts = array_filter($parts);

        if ($parts === []) {
            return $seconds.'s';
        }

        return implode(' ', array_map(fn ($value, $unit) => $value.$unit, $parts, array_keys($parts)));
    }

    protected function hasEnv(string $name): bool
    {
        return getenv($name) !== false;
    }

    protected function read(string $relative): ?string
    {
        $path = $this->path($relative);

        if (! @is_file($path) || ! @is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    protected function path(string $relative): string
    {
        return rtrim($this->root, '/').'/'.$relative;
    }
}
