<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\LaravelModule;
use Aporat\ServerInfo\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LaravelModuleTest extends TestCase
{
    #[Test]
    public function it_reports_the_application(): void
    {
        config()->set('app.name', 'Probe');
        config()->set('app.timezone', 'Asia/Jerusalem');
        config()->set('app.locale', 'he');

        $module = $this->app->make(LaravelModule::class);
        $info = $module->info();

        $this->assertSame('laravel', $module->name());
        $this->assertSame($this->app->version(), $info['version']);
        $this->assertSame($this->app->environment(), $info['env']);
        $this->assertSame('Probe', $info['name']);
        $this->assertSame('Asia/Jerusalem', $info['timezone']);
        $this->assertSame('he', $info['locale']);
        $this->assertFalse($info['maintenance_mode']);
        $this->assertFalse($info['config_cached']);
        $this->assertFalse($info['routes_cached']);
        $this->assertFalse($info['events_cached']);
    }

    #[Test]
    public function debug_is_a_real_boolean(): void
    {
        config()->set('app.debug', true);
        $this->assertTrue($this->app->make(LaravelModule::class)->info()['debug']);

        config()->set('app.debug', false);
        $this->assertFalse($this->app->make(LaravelModule::class)->info()['debug']);

        config()->set('app.debug', '1');
        $this->assertTrue($this->app->make(LaravelModule::class)->info()['debug']);
    }

    #[Test]
    public function maintenance_mode_is_detected(): void
    {
        $this->app->maintenanceMode()->activate([]);

        try {
            $this->assertTrue($this->app->make(LaravelModule::class)->info()['maintenance_mode']);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    #[Test]
    public function non_scalar_config_values_become_null(): void
    {
        config()->set('app.name', ['not', 'a', 'string']);

        $this->assertNull($this->app->make(LaravelModule::class)->info()['name']);
    }
}
