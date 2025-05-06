<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Modules\LaravelModule;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LaravelModuleTest extends TestCase
{
    #[Test]
    public function it_returns_expected_info(): void
    {
        $module = new LaravelModule;

        $info = $module->info();

        $this->assertIsArray($info);
        $this->assertEquals(app()->version(), $info['version']);
        $this->assertEquals(app()->environment(), $info['env']);
        $this->assertEquals(config('app.debug') ? 'true' : 'false', $info['debug']);
        $this->assertEquals(config('app.name'), $info['name']);
        $this->assertEquals(config('app.timezone'), $info['timezone']);
    }

    #[Test]
    public function it_has_laravel_as_name(): void
    {
        $module = new LaravelModule;
        $this->assertEquals('laravel', $module->name());
    }
}
