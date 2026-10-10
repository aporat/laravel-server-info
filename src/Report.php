<?php

namespace Aporat\ServerInfo;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use JsonSerializable;
use Stringable;
use Throwable;

/**
 * The collected output of a set of modules. A module whose info() threw is
 * kept as an error instead of aborting the whole report.
 */
final readonly class Report
{
    /**
     * @param  array<string, array<string, mixed>|Throwable>  $entries  module name => info, or the exception its info() threw
     */
    public function __construct(private array $entries) {}

    /**
     * Calls info() on each module, isolating failures per module.
     *
     * @param  iterable<ModuleInterface>  $modules
     */
    public static function collect(iterable $modules): self
    {
        $entries = [];

        foreach ($modules as $module) {
            try {
                $entries[$module->name()] = self::normalize($module->info());
            } catch (Throwable $e) {
                $entries[$module->name()] = $e;
            }
        }

        return new self($entries);
    }

    /**
     * Every module in order: its info, or the Throwable its info() threw.
     *
     * @return array<string, array<string, mixed>|Throwable>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Info of the modules that succeeded, keyed by module name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function data(): array
    {
        return array_filter($this->entries, fn ($entry) => is_array($entry));
    }

    /**
     * Exceptions thrown by failing modules, keyed by module name.
     *
     * @return array<string, Throwable>
     */
    public function errors(): array
    {
        return array_filter($this->entries, fn ($entry) => $entry instanceof Throwable);
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * Nested array of every module; a failed module becomes ['error' => '...'].
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn ($entry) => $entry instanceof Throwable ? ['error' => self::describe($entry)] : $entry,
            $this->entries
        );
    }

    /**
     * Flattened "module.key" => value pairs. Associative arrays are flattened
     * recursively; lists stay as array values. A failed module becomes a
     * single "module.error" entry.
     *
     * @return array<string, mixed>
     */
    public function flat(): array
    {
        $flat = [];

        foreach ($this->toArray() as $name => $info) {
            self::flatten($info, $name.'.', $flat);
        }

        return $flat;
    }

    public static function describe(Throwable $e): string
    {
        return $e::class.': '.$e->getMessage();
    }

    /**
     * True for a non-empty array that is not a list.
     *
     * @phpstan-assert-if-true array<mixed> $value
     */
    public static function isGroup(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, mixed>  $flat
     */
    private static function flatten(array $data, string $prefix, array &$flat): void
    {
        foreach ($data as $key => $value) {
            if (self::isGroup($value)) {
                self::flatten($value, $prefix.$key.'.', $flat);
            } else {
                $flat[$prefix.$key] = $value;
            }
        }
    }

    /**
     * Reduces values to scalars, null and arrays so every renderer (console,
     * JSON) can print them. Strings are made valid UTF-8.
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            return '[too deep]';
        }

        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => is_finite($value) ? $value : (string) $value,
            is_string($value) => mb_scrub($value, 'UTF-8'),
            is_array($value) => array_map(fn ($item) => self::normalizeValue($item, $depth + 1), $value),
            $value instanceof JsonSerializable => self::normalizeValue($value->jsonSerialize(), $depth + 1),
            $value instanceof Stringable => mb_scrub((string) $value, 'UTF-8'),
            is_object($value) => '[object '.$value::class.']',
            default => '['.get_debug_type($value).']',
        };
    }
}
