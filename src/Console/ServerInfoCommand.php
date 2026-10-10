<?php

namespace Aporat\ServerInfo\Console;

use Aporat\ServerInfo\ModuleRegistry;
use Aporat\ServerInfo\Report;
use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ServerInfoCommand extends Command
{
    protected $signature = 'server:info
        {module? : Only show this module}
        {--flat : Print one "module.key value" line per value}
        {--json : Print the report as JSON (nested, or flat with --flat)}';

    protected $description = 'Display server and environment info from the registered modules';

    public function handle(ModuleRegistry $registry): int
    {
        $modules = $registry->modules();
        $filter = $this->argument('module');

        if (is_string($filter) && $filter !== '') {
            if (! isset($modules[$filter])) {
                $this->components->warn(sprintf(
                    'No module named [%s]. Available modules: %s.',
                    OutputFormatter::escape($filter),
                    OutputFormatter::escape(implode(', ', array_keys($modules)) ?: 'none')
                ));

                return self::FAILURE;
            }

            $modules = [$modules[$filter]];
        }

        $report = Report::collect($modules);

        match (true) {
            (bool) $this->option('json') => $this->renderJson($report, (bool) $this->option('flat')),
            (bool) $this->option('flat') => $this->renderFlat($report),
            default => $this->renderNested($report),
        };

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    protected function renderNested(Report $report): void
    {
        foreach ($report->entries() as $name => $entry) {
            $this->newLine();
            $this->components->twoColumnDetail('  <fg=green;options=bold>'.OutputFormatter::escape($name).'</>');

            if ($entry instanceof Throwable) {
                $this->components->twoColumnDetail(
                    '<fg=red;options=bold>Error</>',
                    '<fg=red>'.OutputFormatter::escape(Report::describe($entry)).'</>'
                );

                continue;
            }

            if ($entry === []) {
                $this->components->twoColumnDetail('<fg=gray>No data</>');

                continue;
            }

            $this->renderRows($entry);
        }

        $this->newLine();
    }

    /**
     * Renders one row per value. Values inside groups (associative arrays)
     * are labelled with their path relative to the module, e.g.
     * "opcache.enabled", because the components trim leading indentation.
     *
     * @param  array<mixed>  $rows
     */
    protected function renderRows(array $rows, string $prefix = ''): void
    {
        foreach ($rows as $key => $value) {
            $label = $prefix.$key;

            if (Report::isGroup($value)) {
                $this->renderRows($value, $label.'.');
            } else {
                $this->components->twoColumnDetail(OutputFormatter::escape($label), $this->formatNested($value));
            }
        }
    }

    /**
     * Console markup for one value in the nested view.
     */
    protected function formatNested(mixed $value): string
    {
        return match (true) {
            $value === null => '<fg=gray>null</>',
            $value === true => '<fg=green;options=bold>true</>',
            $value === false => '<fg=yellow;options=bold>false</>',
            $value === [] => '<fg=gray>none</>',
            is_array($value) && array_is_list($value) && array_filter($value, fn ($item) => is_array($item)) === [] => OutputFormatter::escape(implode(', ', array_map(fn ($item) => $this->formatPlain($item), $value))),
            default => OutputFormatter::escape($this->formatPlain($value)),
        };
    }

    protected function renderFlat(Report $report): void
    {
        $errorKeys = array_map(fn (string $name) => $name.'.error', array_keys($report->errors()));

        foreach ($report->flat() as $key => $value) {
            $tag = in_array($key, $errorKeys, true) ? 'fg=red' : 'info';

            $this->line(sprintf(
                '<%s>%s:</> %s',
                $tag,
                OutputFormatter::escape($key),
                OutputFormatter::escape($this->formatPlain($value))
            ));
        }
    }

    protected function renderJson(Report $report, bool $flat): void
    {
        $json = $this->encode($flat ? $report->flat() : $report->toArray(), JSON_PRETTY_PRINT);

        $this->output->writeln($json, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Plain text for one value: true/false/null as words, arrays as JSON.
     */
    protected function formatPlain(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => $this->encode($value),
            is_scalar($value) => (string) $value,
            default => '['.get_debug_type($value).']',
        };
    }

    protected function encode(mixed $value, int $flags = 0): string
    {
        try {
            return json_encode(
                $value,
                $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            return '[unencodable value: '.$e->getMessage().']';
        }
    }
}
