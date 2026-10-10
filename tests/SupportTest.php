<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\Support\Bytes;
use Aporat\ServerInfo\Support\Errors;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SupportTest extends TestCase
{
    /**
     * @return array<string, array{int|float, string}>
     */
    public static function sizes(): array
    {
        return [
            'zero' => [0, '0 B'],
            'negative' => [-5, '0 B'],
            'bytes' => [1023, '1023 B'],
            'kib' => [1024, '1.0 KiB'],
            'mib' => [1.5 * 1024 ** 2, '1.5 MiB'],
            'gib' => [512 * 1024 ** 3, '512.0 GiB'],
            'tib' => [2 * 1024 ** 4, '2.0 TiB'],
        ];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function bytes_are_formatted_with_iec_units(int|float $bytes, string $expected): void
    {
        $this->assertSame($expected, Bytes::format($bytes));
    }

    #[Test]
    public function errors_are_redacted_to_class_and_code(): void
    {
        $this->assertSame('RuntimeException', Errors::redacted(new RuntimeException('password=hunter2')));
        $this->assertSame('RuntimeException [7]', Errors::redacted(new RuntimeException('host 10.0.0.1', 7)));

        $pdo = new PDOException('SQLSTATE[HY000] [1045] Access denied for user admin@10.0.0.1');
        $pdo->errorInfo = ['HY000', 1045, 'Access denied'];
        $this->assertSame('PDOException [HY000]', Errors::redacted($pdo));
    }
}
