<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\ServerInfoServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServerInfoCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ServerInfoServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('server-info.modules', [
            PhpModule::class,
        ]);
    }

    #[Test]
    public function it_outputs_php_module_info()
    {
        $this->artisan('server:info php')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_outputs_all_modules()
    {
        $this->artisan('server:info')
            ->expectsOutputToContain('php.version:')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_unknown_module()
    {
        $this->artisan('server:info unknown')
            ->expectsOutputToContain('No data found for module [unknown].')
            ->assertExitCode(0);
    }
}
