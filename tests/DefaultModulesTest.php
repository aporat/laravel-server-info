<?php

namespace Aporat\ServerInfo\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

/**
 * Runs the shipped module list end to end, as `php artisan server:info` would.
 */
class DefaultModulesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'memory');
        $app['config']->set('database.connections.memory', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('database.redis.default.password', 'hunter2-redis');
        $app['config']->set('database.connections.mysql.password', 'hunter2-mysql');
        $app['config']->set('mail.mailers.smtp.password', 'hunter2-mail');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    #[Test]
    public function every_default_module_succeeds(): void
    {
        $this->assertSame(0, Artisan::call('server:info', ['--json' => true]));

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(
            ['php', 'laravel', 'system', 'disk', 'drivers', 'database', 'redis', 'packages'],
            array_keys($report)
        );

        foreach ($report as $name => $info) {
            $this->assertArrayNotHasKey('error', $info, "The $name module failed");
        }

        $this->assertSame('ok', $report['database']['memory']['status']);
    }

    #[Test]
    public function no_secret_is_printed_in_any_format(): void
    {
        foreach ([[], ['--flat' => true], ['--json' => true], ['--json' => true, '--flat' => true]] as $options) {
            Artisan::call('server:info', $options);
            $output = Artisan::output();

            foreach (['hunter2', base64_encode(str_repeat('k', 32))] as $secret) {
                $this->assertStringNotContainsString($secret, $output);
            }
        }
    }

    #[Test]
    public function the_nested_and_flat_views_render(): void
    {
        $this->assertSame(0, Artisan::call('server:info'));
        $this->assertMatchesRegularExpression('/^\s+packages \.+\s*$/m', Artisan::output());

        $this->assertSame(0, Artisan::call('server:info', ['--flat' => true]));
        $this->assertStringContainsString('php.version: '.PHP_VERSION, Artisan::output());
    }
}
