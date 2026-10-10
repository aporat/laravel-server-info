<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\DiskModule;
use Aporat\ServerInfo\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DiskModuleTest extends TestCase
{
    #[Test]
    public function it_reports_storage_and_base_paths_by_default(): void
    {
        $module = $this->app->make(DiskModule::class);
        $info = $module->info();

        $this->assertSame('disk', $module->name());
        $this->assertSame(['storage', 'base'], array_keys($info));
        $this->assertSame($this->app->storagePath(), $info['storage']['path']);
        $this->assertSame($this->app->basePath(), $info['base']['path']);
    }

    #[Test]
    public function usage_is_reported_in_bytes_and_human_units(): void
    {
        $dir = sys_get_temp_dir();
        config()->set('server-info.disk.paths', ['tmp' => $dir]);

        $tmp = $this->app->make(DiskModule::class)->info()['tmp'];

        $this->assertSame($dir, $tmp['path']);
        $this->assertSame((int) disk_total_space($dir), $tmp['total_bytes']);
        $this->assertGreaterThan(0, $tmp['total_bytes']);
        $this->assertSame($tmp['total_bytes'] - $tmp['free_bytes'], $tmp['used_bytes']);
        $this->assertSame(round($tmp['used_bytes'] / $tmp['total_bytes'] * 100, 1), $tmp['used_percent']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? (B|KiB|MiB|GiB|TiB|PiB)$/', $tmp['total']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? (B|KiB|MiB|GiB|TiB|PiB)$/', $tmp['free']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? (B|KiB|MiB|GiB|TiB|PiB)$/', $tmp['used']);
    }

    #[Test]
    public function a_missing_path_is_unavailable_instead_of_failing(): void
    {
        config()->set('server-info.disk.paths', ['gone' => '/definitely/not/here/'.bin2hex(random_bytes(4))]);

        $info = $this->app->make(DiskModule::class)->info();

        $this->assertSame('unavailable', $info['gone']['status']);
    }

    #[Test]
    public function list_entries_use_the_path_as_label_and_invalid_entries_are_skipped(): void
    {
        $dir = sys_get_temp_dir();
        config()->set('server-info.disk.paths', [$dir, '', 42]);

        $this->assertSame([$dir], array_keys($this->app->make(DiskModule::class)->info()));
    }
}
