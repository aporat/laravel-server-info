<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Tests\Fixtures\CountingModule;
use Aporat\ServerInfo\Tests\Fixtures\NotAModule;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function it_registers_and_retrieves_a_scalar_module(): void
    {
        $module = new class implements ModuleInterface
        {
            public function name(): string
            {
                return 'scalar';
            }

            public function info(): string
            {
                return 'value';
            }
        };

        $registry = new ModuleRegistry;
        $registry->register($module);

        $result = $registry->all();

        $this->assertArrayHasKey('scalar', $result);
        $this->assertEquals('value', $result['scalar']);
    }

    #[Test]
    public function it_registers_and_retrieves_an_array_module(): void
    {
        $module = new class implements ModuleInterface
        {
            public function name(): string
            {
                return 'env';
            }

            public function info(): array
            {
                return ['php' => '8.4', 'laravel' => '12.x'];
            }
        };

        $registry = new ModuleRegistry;
        $registry->register($module);

        $result = $registry->all();

        $this->assertArrayHasKey('env.php', $result);
        $this->assertArrayHasKey('env.laravel', $result);
        $this->assertEquals('8.4', $result['env.php']);
        $this->assertEquals('12.x', $result['env.laravel']);
    }

    #[Test]
    public function extend_is_lazy_for_class_names_and_closures(): void
    {
        $calls = 0;
        $registry = (new ModuleRegistry)
            ->extend(CountingModule::class)
            ->extend(function () use (&$calls) {
                $calls++;

                return new StaticModule('built');
            });

        $this->assertSame(0, CountingModule::$constructed);
        $this->assertSame(0, $calls);

        $result = $registry->all();

        $this->assertSame(1, CountingModule::$constructed);
        $this->assertSame(1, $calls);
        $this->assertSame(1, $result['counting.constructed']);
        $this->assertSame('value', $result['built']);
    }

    #[Test]
    public function it_rejects_duplicate_module_names(): void
    {
        $registry = new ModuleRegistry;
        $registry->register(new StaticModule('php', 'a'));
        $registry->register(new StaticModule('php', 'b'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Server info module name "php" is used by both');

        $registry->all();
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
        $registry = new ModuleRegistry;
        $registry->register(new StaticModule($name, 'x'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names must be non-empty and must not contain a dot');

        $registry->all();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidEntries(): array
    {
        return [
            'missing class' => ['App\\ServerInfo\\Typo', 'Server info module class [App\\ServerInfo\\Typo] listed in extend() does not exist.'],
            'not a module' => [NotAModule::class, 'does not implement'],
            'global function name' => ['Aporat\\ServerInfo\\Tests\\Fixtures\\sideEffect', 'does not exist'],
            'static method string' => [StaticModule::class.'::make', 'does not exist'],
            'closure returning junk' => [fn () => 'nope', 'A closure in extend() returned string instead of an implementation of'],
        ];
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_rejects_invalid_entries_without_calling_them(mixed $entry, string $message): void
    {
        $registry = (new ModuleRegistry)->extend($entry);

        try {
            $registry->all();
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

        $this->assertSame(['static' => 'value'], $registry->all());
    }
}
