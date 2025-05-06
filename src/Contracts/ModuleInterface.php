<?php

namespace Aporat\ServerInfo\Contracts;

interface ModuleInterface
{
    /**
     * Returns a unique name for this module.
     */
    public function name(): string;

    /**
     * Returns diagnostic info for this module.
     * Can be a scalar or associative array.
     */
    public function info(): mixed;
}
