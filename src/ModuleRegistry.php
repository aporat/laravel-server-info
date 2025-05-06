<?php

namespace Aporat\ServerInfo;

use Aporat\ServerInfo\Contracts\ModuleInterface;

class ModuleRegistry
{
    /**
     * @var ModuleInterface[]
     */
    protected array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[] = $module;
    }

    /**
     * Retrieves and aggregates information from all modules.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->modules as $module) {
            $info = $module->info();

            if (is_array($info)) {
                foreach ($info as $key => $value) {
                    $result[$module->name().'.'.$key] = $value;
                }
            } else {
                $result[$module->name()] = $info;
            }
        }

        return $result;
    }
}
