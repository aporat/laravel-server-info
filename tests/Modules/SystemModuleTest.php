<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\SystemModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SystemModuleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/server-info-system-'.bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        parent::tearDown();
    }

    /**
     * @param  list<string>  $env  environment variables that exist
     */
    private function module(array $env = []): SystemModule
    {
        return new class($this->root, $env) extends SystemModule
        {
            /**
             * @param  list<string>  $env
             */
            public function __construct(string $root, private array $env)
            {
                parent::__construct($root);
            }

            protected function hasEnv(string $name): bool
            {
                return in_array($name, $this->env, true);
            }
        };
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->root.'/'.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }

    #[Test]
    public function it_reports_uname_facts(): void
    {
        $module = $this->module();
        $info = $module->info();

        $this->assertSame('system', $module->name());
        $this->assertSame(PHP_OS, $info['os']);
        $this->assertSame(PHP_OS_FAMILY, $info['os_family']);
        $this->assertSame(php_uname('r'), $info['kernel']);
        $this->assertSame(php_uname('m'), $info['machine']);
        $this->assertSame(gethostname() ?: null, $info['hostname']);
    }

    #[Test]
    public function it_reads_cpus_uptime_and_cgroup_v2_limits_from_proc_and_sys(): void
    {
        $this->write('proc/cpuinfo', "processor\t: 0\nmodel name\t: x\n\nprocessor\t: 1\nmodel name\t: x\n\nprocessor\t: 2\n");
        $this->write('proc/uptime', "93784.52 180000.00\n");
        $this->write('sys/fs/cgroup/memory.max', "536870912\n");

        $info = $this->module()->info();

        $this->assertSame(3, $info['cpus']);
        $this->assertSame(93784, $info['uptime_seconds']);
        $this->assertSame('1d 2h 3m', $info['uptime']);
        $this->assertSame('512.0 MiB', $info['memory_limit']);
        $this->assertSame(536870912, $info['memory_limit_bytes']);
    }

    #[Test]
    public function an_unlimited_cgroup_v2_limit_is_reported_as_unlimited(): void
    {
        $this->write('sys/fs/cgroup/memory.max', "max\n");

        $info = $this->module()->info();

        $this->assertSame('unlimited', $info['memory_limit']);
        $this->assertNull($info['memory_limit_bytes']);
    }

    #[Test]
    public function cgroup_v1_limits_are_read_and_huge_values_mean_unlimited(): void
    {
        $this->write('sys/fs/cgroup/memory/memory.limit_in_bytes', "1073741824\n");
        $this->assertSame('1.0 GiB', $this->module()->info()['memory_limit']);

        $this->write('sys/fs/cgroup/memory/memory.limit_in_bytes', "9223372036854771712\n");
        $this->assertSame('unlimited', $this->module()->info()['memory_limit']);
    }

    #[Test]
    public function missing_files_give_null_values(): void
    {
        $info = $this->module()->info();

        $this->assertNull($info['cpus']);
        $this->assertNull($info['uptime']);
        $this->assertNull($info['uptime_seconds']);
        $this->assertNull($info['memory_limit']);
        $this->assertNull($info['memory_limit_bytes']);
        $this->assertSame('none', $info['container']);
    }

    #[Test]
    public function malformed_files_give_null_values(): void
    {
        $this->write('proc/cpuinfo', 'garbage');
        $this->write('proc/uptime', 'garbage');
        $this->write('sys/fs/cgroup/memory.max', 'garbage');

        $info = $this->module()->info();

        $this->assertNull($info['cpus']);
        $this->assertNull($info['uptime_seconds']);
        $this->assertNull($info['memory_limit']);
    }

    #[Test]
    public function short_uptimes_are_shown_in_seconds(): void
    {
        $this->write('proc/uptime', "42.0 1.0\n");

        $this->assertSame('42s', $this->module()->info()['uptime']);
    }

    #[Test]
    public function docker_is_detected_from_dockerenv(): void
    {
        $this->write('.dockerenv', '');

        $this->assertSame('docker', $this->module()->info()['container']);
    }

    #[Test]
    public function podman_is_detected_from_containerenv(): void
    {
        $this->write('run/.containerenv', '');

        $this->assertSame('podman', $this->module()->info()['container']);
    }

    #[Test]
    public function kubernetes_is_detected_from_the_service_host_variable(): void
    {
        $this->assertSame('kubernetes', $this->module(['KUBERNETES_SERVICE_HOST'])->info()['container']);
    }

    #[Test]
    public function runtimes_are_detected_from_the_init_cgroup(): void
    {
        $this->write('proc/1/cgroup', "0::/system.slice/containerd.service\n");
        $this->assertSame('containerd', $this->module()->info()['container']);

        $this->write('proc/1/cgroup', "12:memory:/kubepods/burstable/pod1/abc\n");
        $this->assertSame('kubernetes', $this->module()->info()['container']);

        $this->write('proc/1/cgroup', "0::/\n");
        $this->assertSame('none', $this->module()->info()['container']);
    }

    #[Test]
    public function load_average_is_a_list_of_three_numbers_when_available(): void
    {
        $load = $this->module()->info()['load_average'];

        if (! function_exists('sys_getloadavg') || sys_getloadavg() === false) {
            $this->assertNull($load);

            return;
        }

        $this->assertIsArray($load);
        $this->assertCount(3, $load);
        $this->assertContainsOnlyFloat($load);
    }

    #[Test]
    public function the_default_root_reads_the_real_system(): void
    {
        $info = (new SystemModule)->info();

        if (PHP_OS_FAMILY === 'Linux') {
            $this->assertIsInt($info['cpus']);
            $this->assertGreaterThan(0, $info['cpus']);
            $this->assertIsInt($info['uptime_seconds']);
        }

        $this->assertIsString($info['container']);
    }
}
