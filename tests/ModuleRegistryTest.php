<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Tests\Fixtures\CountingModule;
use Aporat\ServerInfo\Tests\Fixtures\NotAModule;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Aporat\ServerInfo\Tests\Fixtures\TaggedModule;
use Aporat\ServerInfo\Tests\Fixtures\ThrowingModule;
use Illuminate\Container\Container;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/Fixtures/functions.php';

class ModuleRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CountingModule::$constructed = 0;
        unset($GLOBALS['server_info_side_effect']);
    }

    #[Test]
    public function all_returns_info_nested_by_module_name(): void
    {
        $registry = (new ModuleRegistry)
            ->register(new StaticModule('env', ['php' => '8.4', 'laravel' => '12.x']))
            ->register(new StaticModule('other', ['group' => ['a' => 1]]));

        $this->assertSame([
            'env' => ['php' => '8.4', 'laravel' => '12.x'],
            'other' => ['group' => ['a' => 1]],
        ], $registry->all());
    }

    #[Test]
    public function flat_returns_dotted_keys(): void
    {
        $registry = (new ModuleRegistry)->register(new StaticModule('env', ['php' => '8.4', 'group' => ['a' => 1]]));

        $this->assertSame(['env.php' => '8.4', 'env.group.a' => 1], $registry->flat());
    }

    #[Test]
    public function extend_is_lazy(): void
    {
        $registry = (new ModuleRegistry)->extend(CountingModule::class);

        $this->assertSame(0, CountingModule::$constructed);

        $result = $registry->all();

        $this->assertSame(1, CountingModule::$constructed);
        $this->assertSame(['counting' => ['constructed' => 1]], $result);
    }

    #[Test]
    public function collect_isolates_module_failures(): void
    {
        $registry = (new ModuleRegistry)
            ->register(new ThrowingModule)
            ->register(new StaticModule);

        $report = $registry->collect();

        $this->assertTrue($report->hasErrors());
        $this->assertSame(['static' => ['value' => 'x']], $report->data());
    }

    #[Test]
    public function collect_can_be_limited_to_one_module(): void
    {
        $registry = (new ModuleRegistry)
            ->register(new ThrowingModule)
            ->register(new StaticModule);

        $report = $registry->collect('static');

        $this->assertFalse($report->hasErrors());
        $this->assertSame(['static' => ['value' => 'x']], $report->entries());
    }

    #[Test]
    public function collect_rejects_an_unknown_module(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No server info module is named "nope".');

        (new ModuleRegistry)->register(new StaticModule)->collect('nope');
    }

    #[Test]
    public function all_and_flat_rethrow_module_failures(): void
    {
        $registry = (new ModuleRegistry)->register(new ThrowingModule);

        foreach (['all', 'flat'] as $method) {
            try {
                $registry->{$method}();
                $this->fail("$method() should rethrow");
            } catch (RuntimeException $e) {
                $this->assertSame('module exploded', $e->getMessage());
            }
        }
    }

    #[Test]
    public function tagged_modules_are_resolved_from_the_container(): void
    {
        $container = new Container;
        $container->tag([TaggedModule::class], ModuleRegistry::TAG);

        $registry = (new ModuleRegistry($container))->register(new StaticModule);

        $this->assertSame(['tagged', 'static'], array_keys($registry->modules()));
    }

    #[Test]
    public function a_tagged_service_that_is_not_a_module_is_rejected(): void
    {
        $container = new Container;
        $container->tag([NotAModule::class], ModuleRegistry::TAG);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A service tagged [server-info.modules] resolved to '.NotAModule::class);

        (new ModuleRegistry($container))->modules();
    }

    #[Test]
    public function it_rejects_duplicate_module_names(): void
    {
        $registry = (new ModuleRegistry)
            ->register(new StaticModule('php'))
            ->register(new StaticModule('php'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Server info module name "php" is used by both');

        $registry->modules();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'dot' => ['php.version'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_invalid_module_names(string $name): void
    {
        $registry = (new ModuleRegistry)->register(new StaticModule($name));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names must be non-empty and must not contain a dot');

        $registry->modules();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidEntries(): array
    {
        return [
            'missing class' => ['App\\ServerInfo\\Typo', 'Server info module class [App\\ServerInfo\\Typo] listed in ModuleRegistry::extend() does not exist.'],
            'not a module' => [NotAModule::class, 'does not implement'],
            'global function name' => ['Aporat\\ServerInfo\\Tests\\Fixtures\\sideEffect', 'does not exist'],
            'static method string' => [StaticModule::class.'::make', 'does not exist'],
        ];
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_rejects_invalid_entries_without_calling_them(string $entry, string $message): void
    {
        $registry = (new ModuleRegistry)->extend($entry);

        try {
            $registry->modules();
            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertArrayNotHasKey('server_info_side_effect', $GLOBALS, 'A string entry was executed as a callable');
    }

    #[Test]
    public function without_a_container_classes_are_instantiated_directly(): void
    {
        $registry = (new ModuleRegistry)->extend(StaticModule::class);

        $this->assertSame(['static' => ['value' => 'x']], $registry->all());
    }
}
