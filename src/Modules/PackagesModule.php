<?php

namespace Aporat\ServerInfo\Modules;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;

/**
 * The root Composer package and the installed versions of the packages listed
 * in `server-info.packages`, read from Composer's runtime metadata.
 */
class PackagesModule implements ModuleInterface
{
    public function __construct(protected Repository $config) {}

    public function name(): string
    {
        return 'packages';
    }

    public function info(): array
    {
        if (! class_exists(InstalledVersions::class)) {
            return ['status' => 'unavailable'];
        }

        $root = InstalledVersions::getRootPackage();
        $reference = $root['reference'] ?? null;

        $info = [
            'root' => [
                'name' => $root['name'],
                'version' => $root['pretty_version'],
                'reference' => is_string($reference) ? substr($reference, 0, 12) : null,
            ],
        ];

        foreach ($this->packages() as $package) {
            $info[$package] = InstalledVersions::isInstalled($package)
                ? (InstalledVersions::getPrettyVersion($package) ?? 'unknown')
                : 'not installed';
        }

        return $info;
    }

    /**
     * @return list<string>
     */
    protected function packages(): array
    {
        $packages = $this->config->get('server-info.packages', ['laravel/framework']);

        if (! is_array($packages)) {
            return [];
        }

        return array_values(array_filter(
            $packages,
            fn ($package) => is_string($package) && $package !== '' && $package !== 'root'
        ));
    }
}
