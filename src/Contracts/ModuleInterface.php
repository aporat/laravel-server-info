<?php

namespace Aporat\ServerInfo\Contracts;

interface ModuleInterface
{
    /**
     * A unique, non-empty name for this module, used as its section title
     * and key prefix. Must not contain a dot.
     */
    public function name(): string;

    /**
     * Diagnostic info as key/value pairs. Values are scalars, null, or
     * arrays: an associative array renders as a nested group, a list as a
     * comma-separated value.
     *
     * Never return secrets (passwords, credentials, DSNs, env values).
     *
     * @return array<string, scalar|null|array<mixed>>
     */
    public function info(): array;
}
