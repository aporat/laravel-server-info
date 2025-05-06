<?php

namespace Aporat\ServerInfo\Console;

use Aporat\ServerInfo\ModuleRegistry;
use Illuminate\Console\Command;

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

            if (isset($modules[$filter])) {
                $filtered[$filter] = $modules[$filter]; // Also include base match
            }

            if (empty($filtered)) {
                $this->warn("No data found for module [$filter].");

                return self::SUCCESS;
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
            $this->line(sprintf('<info>%s:</info> %s', $key, is_array($value) ? json_encode($value) : $value));
        }
    }
}
