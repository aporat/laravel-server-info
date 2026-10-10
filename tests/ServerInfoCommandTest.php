<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Aporat\ServerInfo\Tests\Fixtures\ThrowingModule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class ServerInfoCommandTest extends TestCase
{
    private string $output = '';

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('server-info.modules', []);
    }

    /**
     * Registers modules, runs the command and returns [exit code, trimmed non-empty output lines].
     *
     * @param  list<StaticModule|ThrowingModule>  $modules
     * @param  array<string, mixed>  $parameters
     * @return array{int, list<string>}
     */
    private function runCommand(array $modules, array $parameters = []): array
    {
        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($modules as $module) {
            $registry->register($module);
        }

        $code = Artisan::call('server:info', $parameters);
        $this->output = Artisan::output();
        $lines = array_map(rtrim(...), explode("\n", $this->output));

        return [$code, array_values(array_filter($lines, fn ($line) => trim($line) !== ''))];
    }

    private function sample(): StaticModule
    {
        return new StaticModule('sample', [
            'version' => '1.2.3',
            'enabled' => true,
            'disabled' => false,
            'missing' => null,
            'count' => 42,
            'ratio' => 1.5,
            'list' => ['a', 'b'],
            'none' => [],
            'group' => ['inner' => 'x', 'deeper' => ['leaf' => 1]],
        ]);
    }

    #[Test]
    public function the_default_output_has_a_section_per_module(): void
    {
        [$code, $lines] = $this->runCommand([$this->sample(), new StaticModule('second', ['key' => 'value'])]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertMatchesRegularExpression('/^\s+sample \.+$/', $lines[0]);
        $this->assertMatchesRegularExpression('/^\s+version \.+ 1\.2\.3$/', $lines[1]);
        $this->assertMatchesRegularExpression('/^\s+enabled \.+ true$/', $lines[2]);
        $this->assertMatchesRegularExpression('/^\s+disabled \.+ false$/', $lines[3]);
        $this->assertMatchesRegularExpression('/^\s+missing \.+ null$/', $lines[4]);
        $this->assertMatchesRegularExpression('/^\s+count \.+ 42$/', $lines[5]);
        $this->assertMatchesRegularExpression('/^\s+ratio \.+ 1\.5$/', $lines[6]);
        $this->assertMatchesRegularExpression('/^\s+list \.+ a, b$/', $lines[7]);
        $this->assertMatchesRegularExpression('/^\s+none \.+ none$/', $lines[8]);
        $this->assertMatchesRegularExpression('/^\s+group\.inner \.+ x$/', $lines[9]);
        $this->assertMatchesRegularExpression('/^\s+group\.deeper\.leaf \.+ 1$/', $lines[10]);
        $this->assertMatchesRegularExpression('/^\s+second \.+$/', $lines[11]);
        $this->assertMatchesRegularExpression('/^\s+key \.+ value$/', $lines[12]);
        $this->assertCount(13, $lines);
    }

    #[Test]
    public function flat_prints_the_v1_style_lines(): void
    {
        [$code, $lines] = $this->runCommand([$this->sample()], ['--flat' => true]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([
            'sample.version: 1.2.3',
            'sample.enabled: true',
            'sample.disabled: false',
            'sample.missing: null',
            'sample.count: 42',
            'sample.ratio: 1.5',
            'sample.list: ["a","b"]',
            'sample.none: []',
            'sample.group.inner: x',
            'sample.group.deeper.leaf: 1',
        ], $lines);
    }

    #[Test]
    public function json_prints_the_nested_report(): void
    {
        $this->runCommand([$this->sample()], ['--json' => true]);

        $this->assertSame(['sample' => [
            'version' => '1.2.3',
            'enabled' => true,
            'disabled' => false,
            'missing' => null,
            'count' => 42,
            'ratio' => 1.5,
            'list' => ['a', 'b'],
            'none' => [],
            'group' => ['inner' => 'x', 'deeper' => ['leaf' => 1]],
        ]], json_decode($this->output, true, flags: JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function json_with_flat_prints_dotted_keys(): void
    {
        $this->runCommand([$this->sample()], ['--json' => true, '--flat' => true]);

        $decoded = json_decode($this->output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.2.3', $decoded['sample.version']);
        $this->assertSame(1, $decoded['sample.group.deeper.leaf']);
        $this->assertSame(['a', 'b'], $decoded['sample.list']);
    }

    #[Test]
    public function json_output_is_raw_and_keeps_markup_and_slashes(): void
    {
        $this->runCommand([new StaticModule('m', ['tag' => '<info>x</info>', 'path' => '/var/www', 'unicode' => 'é'])], ['--json' => true]);

        $output = $this->output;

        $this->assertStringContainsString('"tag": "<info>x</info>"', $output);
        $this->assertStringContainsString('"path": "/var/www"', $output);
        $this->assertStringContainsString('"unicode": "é"', $output);
    }

    #[Test]
    public function the_module_argument_shows_only_that_module(): void
    {
        [$code, $lines] = $this->runCommand([new ThrowingModule, new StaticModule('only', ['k' => 'v'])], ['module' => 'only', '--flat' => true]);

        $this->assertSame(Command::SUCCESS, $code, 'Other modules must not even be collected');
        $this->assertSame(['only.k: v'], $lines);
    }

    #[Test]
    public function the_module_argument_must_be_an_exact_module_name(): void
    {
        [$code, $lines] = $this->runCommand([new StaticModule('php', ['version' => '8'])], ['module' => 'php.version']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('No module named [php.version]. Available modules: php.', $lines[0]);
    }

    #[Test]
    public function an_unknown_module_fails_and_escapes_the_name(): void
    {
        [$code, $lines] = $this->runCommand([], ['module' => '<error>x</error>']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('No module named [<error>x</error>]. Available modules: none.', $lines[0]);
    }

    #[Test]
    public function a_failing_module_is_reported_and_the_others_still_run(): void
    {
        $modules = [new StaticModule('before', ['k' => 'v']), new ThrowingModule, new StaticModule('after', ['k' => 'v'])];

        [$code, $lines] = $this->runCommand($modules);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertMatchesRegularExpression('/^\s+broken \.+$/', $lines[2]);
        $this->assertMatchesRegularExpression('/^\s+Error \.+ RuntimeException: module exploded$/', $lines[3]);
        $this->assertMatchesRegularExpression('/^\s+after \.+$/', $lines[4]);
    }

    #[Test]
    public function a_failing_module_is_reported_in_flat_and_json_output(): void
    {
        $this->app->make(ModuleRegistry::class)->register(new ThrowingModule)->register(new StaticModule);

        $this->assertSame(Command::FAILURE, Artisan::call('server:info', ['--flat' => true]));
        $this->assertSame(
            "broken.error: RuntimeException: module exploded\nstatic.value: x\n",
            Artisan::output()
        );

        $this->assertSame(Command::FAILURE, Artisan::call('server:info', ['--json' => true]));
        $this->assertSame(
            ['broken' => ['error' => 'RuntimeException: module exploded'], 'static' => ['value' => 'x']],
            json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    #[Test]
    public function an_empty_module_says_so(): void
    {
        [$code, $lines] = $this->runCommand([new StaticModule('empty', [])]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertMatchesRegularExpression('/^\s+No data \.+$/', $lines[1]);
    }

    #[Test]
    public function console_markup_in_keys_and_values_is_printed_literally(): void
    {
        $module = new StaticModule('markup', [
            'link' => '<href=https://evil.example>click</>',
            '<error>key</error>' => '<error>boom</error>',
        ]);

        [, $flat] = $this->runCommand([$module], ['--flat' => true]);

        $this->assertSame([
            'markup.link: <href=https://evil.example>click</>',
            'markup.<error>key</error>: <error>boom</error>',
        ], $flat);

        Artisan::call('server:info');
        $nested = Artisan::output();

        $this->assertStringContainsString('<href=https://evil.example>click</>', $nested);
        $this->assertStringContainsString('<error>boom</error>', $nested);
    }
}
