<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\PhpModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhpModuleTest extends TestCase
{
    #[Test]
    public function it_reports_the_runtime(): void
    {
        $module = new PhpModule;
        $info = $module->info();

        $this->assertSame('php', $module->name());
        $this->assertSame(PHP_VERSION, $info['version']);
        $this->assertSame(PHP_SAPI, $info['sapi']);
        $this->assertSame(ini_get('memory_limit'), $info['memory_limit']);
        $this->assertSame((int) ini_get('max_execution_time'), $info['max_execution_time']);
        $this->assertSame(ini_get('upload_max_filesize'), $info['upload_max_filesize']);
        $this->assertSame(ini_get('post_max_size'), $info['post_max_size']);
        $this->assertSame(date_default_timezone_get(), $info['timezone']);
        $this->assertArrayNotHasKey('os', $info, 'The OS moved to the system module');
    }

    #[Test]
    public function extensions_are_a_sorted_list(): void
    {
        $extensions = (new PhpModule)->info()['extensions'];

        $this->assertIsArray($extensions);
        $this->assertTrue(array_is_list($extensions));
        $this->assertContains('Core', $extensions);

        $sorted = $extensions;
        natcasesort($sorted);
        $this->assertSame(array_values($sorted), $extensions);
    }

    #[Test]
    public function opcache_reports_typed_fields(): void
    {
        $opcache = (new PhpModule)->info()['opcache'];

        $this->assertIsArray($opcache);
        $this->assertIsBool($opcache['enabled']);

        if (! extension_loaded('Zend OPcache')) {
            $this->assertSame(['enabled' => false], $opcache);

            return;
        }

        if (is_array(@opcache_get_status(false))) {
            $this->assertIsBool($opcache['jit']);
            $this->assertMatchesRegularExpression('/^\d+(\.\d)? (B|KiB|MiB|GiB)$/', $opcache['memory_used']);
            $this->assertMatchesRegularExpression('/^\d+(\.\d)? (B|KiB|MiB|GiB)$/', $opcache['memory_free']);
        }
    }

    #[Test]
    public function every_value_matches_the_module_contract(): void
    {
        foreach ((new PhpModule)->info() as $key => $value) {
            $this->assertIsString($key);
            $this->assertTrue(is_scalar($value) || $value === null || is_array($value), "$key has an invalid type");
        }
    }
}
