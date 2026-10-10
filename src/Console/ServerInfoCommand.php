<?php

namespace Aporat\ServerInfo\Console;

use Aporat\ServerInfo\ModuleRegistry;
use Illuminate\Console\Command;
use JsonException;
use JsonSerializable;
use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ServerInfoCommand extends Command
{
    protected $signature = 'server:info {module? : Optional module name to query}';

    protected $description = 'Display server or environment info from registered modules';

    public function handle(ModuleRegistry $registry): int
    {
        $modules = $registry->all();
        $filter = is_string($this->argument('module')) ? $this->argument('module') : null;

        if ($filter) {
            $filtered = array_filter($modules, fn ($_, $key) => str_starts_with($key, $filter.'.'), ARRAY_FILTER_USE_BOTH);

            if (array_key_exists($filter, $modules)) {
                $filtered[$filter] = $modules[$filter]; // Also include base match
            }

            if (empty($filtered)) {
                $this->warn('No data found for module ['.OutputFormatter::escape($filter).'].');

                return self::FAILURE;
            }

            $this->outputInfo($filtered);
        } else {
            $this->outputInfo($modules);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function outputInfo(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->line(sprintf(
                '<info>%s:</info> %s',
                OutputFormatter::escape((string) $key),
                OutputFormatter::escape($this->formatValue($value))
            ));
        }
    }

    /**
     * Renders a module value as one line of plain text. Never throws:
     * values that cannot be rendered become a bracketed placeholder.
     */
    protected function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => mb_scrub($value, 'UTF-8'),
            is_int($value), is_float($value) => (string) $value,
            is_array($value), $value instanceof JsonSerializable => $this->encode($value),
            $value instanceof Stringable => mb_scrub((string) $value, 'UTF-8'),
            is_object($value) => '[object '.$value::class.']',
            default => '['.get_debug_type($value).']',
        };
    }

    protected function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return '[unencodable value: '.$e->getMessage().']';
        }
    }
}
