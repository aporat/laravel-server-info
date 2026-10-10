<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Report;
use Aporat\ServerInfo\Tests\Fixtures\StaticModule;
use Aporat\ServerInfo\Tests\Fixtures\ThrowingModule;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

class ReportTest extends TestCase
{
    #[Test]
    public function a_throwing_module_is_isolated(): void
    {
        $report = Report::collect([
            new StaticModule('first', ['a' => 1]),
            new ThrowingModule,
            new StaticModule('last', ['b' => 2]),
        ]);

        $this->assertTrue($report->hasErrors());
        $this->assertSame(['first', 'broken', 'last'], array_keys($report->entries()));
        $this->assertSame(['first' => ['a' => 1], 'last' => ['b' => 2]], $report->data());
        $this->assertInstanceOf(RuntimeException::class, $report->errors()['broken']);
        $this->assertSame([
            'first' => ['a' => 1],
            'broken' => ['error' => 'RuntimeException: module exploded'],
            'last' => ['b' => 2],
        ], $report->toArray());
    }

    #[Test]
    public function a_report_without_failures_has_no_errors(): void
    {
        $report = Report::collect([new StaticModule]);

        $this->assertFalse($report->hasErrors());
        $this->assertSame([], $report->errors());
    }

    #[Test]
    public function flat_recurses_into_groups_and_keeps_lists(): void
    {
        $report = Report::collect([
            new StaticModule('m', [
                'scalar' => 'v',
                'group' => ['inner' => ['deep' => true], 'n' => null],
                'list' => ['a', 'b'],
                'empty' => [],
            ]),
            new ThrowingModule,
        ]);

        $this->assertSame([
            'm.scalar' => 'v',
            'm.group.inner.deep' => true,
            'm.group.n' => null,
            'm.list' => ['a', 'b'],
            'm.empty' => [],
            'broken.error' => 'RuntimeException: module exploded',
        ], $report->flat());
    }

    #[Test]
    public function values_are_normalized_to_printable_data(): void
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
        $resource = fopen('php://memory', 'r');

        $report = Report::collect([new StaticModule('weird', [
            'stringable' => $stringable,
            'serializable' => $serializable,
            'object' => new stdClass,
            'invalid_utf8' => "ok\xB1",
            'nested' => ['bad' => "\xFF"],
            'inf' => INF,
            'resource' => $resource,
            'float' => 1.5,
        ])]);

        $this->assertSame([
            'stringable' => 'from __toString',
            'serializable' => ['k' => 'v'],
            'object' => '[object stdClass]',
            'invalid_utf8' => 'ok?',
            'nested' => ['bad' => '?'],
            'inf' => 'INF',
            'resource' => '[resource (stream)]',
            'float' => 1.5,
        ], $report->data()['weird']);
    }

    #[Test]
    public function is_group_only_matches_non_empty_associative_arrays(): void
    {
        $this->assertTrue(Report::isGroup(['a' => 1]));
        $this->assertFalse(Report::isGroup([]));
        $this->assertFalse(Report::isGroup([1, 2]));
        $this->assertFalse(Report::isGroup('a'));
    }
}
