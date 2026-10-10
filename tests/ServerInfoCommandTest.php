<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Modules\PhpModule;
use Aporat\ServerInfo\ServerInfoServiceProvider;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use JsonSerializable;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Stringable;

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

    /**
     * Runs the command against one extra module and returns [exit code, output lines].
     *
     * @return array{int, list<string>}
     */
    private function runWith(StaticModule $module, string $filter): array
    {
        $this->app->make(ModuleRegistry::class)->register($module);
        $code = Artisan::call('server:info', ['module' => $filter]);

        return [$code, array_values(array_filter(explode("\n", Artisan::output()), fn ($line) => $line !== ''))];
    }

    #[Test]
    public function it_outputs_php_module_info(): void
    {
        $this->artisan('server:info php')
            ->expectsOutputToContain('php.version: '.PHP_VERSION)
            ->expectsOutputToContain('php.sapi: '.PHP_SAPI)
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function it_outputs_all_modules(): void
    {
        $this->artisan('server:info')
            ->expectsOutputToContain('php.version:')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Test]
    public function an_unknown_module_fails(): void
    {
        $this->artisan('server:info unknown')
            ->expectsOutputToContain('No data found for module [unknown].')
            ->assertExitCode(Command::FAILURE);
    }

    #[Test]
    public function a_scalar_module_matches_its_exact_name(): void
    {
        [$code, $lines] = $this->runWith(new StaticModule('scalar', 'only'), 'scalar');

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(['scalar: only'], $lines);
    }

    #[Test]
    public function values_are_rendered_explicitly_and_never_fatal(): void
    {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'from __toString';
            }
        };
        $serializable = new class implements JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return ['k' => 'v'];
            }
        };

        [$code, $lines] = $this->runWith(new StaticModule('weird', [
            'true' => true,
            'false' => false,
            'null' => null,
            'int' => 42,
            'float' => 1.5,
            'list' => ['a', 'b'],
            'stringable' => $stringable,
            'serializable' => $serializable,
            'object' => new stdClass,
            'invalid_utf8' => "ok\xB1",
            'invalid_utf8_nested' => ['a' => "\xFF"],
            'inf' => [INF],
        ]), 'weird');

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([
            'weird.true: true',
            'weird.false: false',
            'weird.null: null',
            'weird.int: 42',
            'weird.float: 1.5',
            'weird.list: ["a","b"]',
            'weird.stringable: from __toString',
            'weird.serializable: {"k":"v"}',
            'weird.object: [object stdClass]',
            'weird.invalid_utf8: ok?',
            'weird.invalid_utf8_nested: {"a":"\ufffd"}',
            'weird.inf: [unencodable value: Inf and NaN cannot be JSON encoded]',
        ], $lines);
    }

    #[Test]
    public function console_markup_in_values_is_printed_literally(): void
    {
        [$code, $lines] = $this->runWith(new StaticModule('markup', [
            'link' => '<href=https://evil.example>click</>',
            'style' => '<error>boom</error>',
        ]), 'markup');

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([
            'markup.link: <href=https://evil.example>click</>',
            'markup.style: <error>boom</error>',
        ], $lines);
    }
}
