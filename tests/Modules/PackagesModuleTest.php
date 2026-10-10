<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\PackagesModule;
use Aporat\ServerInfo\Tests\TestCase;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\Test;

class PackagesModuleTest extends TestCase
{
    #[Test]
    public function it_reports_the_root_package_and_laravel_by_default(): void
    {
        $module = $this->app->make(PackagesModule::class);
        $info = $module->info();
        $root = InstalledVersions::getRootPackage();

        $this->assertSame('packages', $module->name());
        $this->assertSame($root['name'], $info['root']['name']);
        $this->assertSame($root['pretty_version'], $info['root']['version']);
        $this->assertSame(substr((string) $root['reference'], 0, 12), $info['root']['reference'] ?? '');
        $this->assertSame(InstalledVersions::getPrettyVersion('laravel/framework'), $info['laravel/framework']);
    }

    #[Test]
    public function configured_packages_are_reported_and_missing_ones_say_so(): void
    {
        config()->set('server-info.packages', ['phpunit/phpunit', 'acme/not-a-real-package', '', 42, 'root']);

        $info = $this->app->make(PackagesModule::class)->info();

        $this->assertSame(['root', 'phpunit/phpunit', 'acme/not-a-real-package'], array_keys($info));
        $this->assertSame(InstalledVersions::getPrettyVersion('phpunit/phpunit'), $info['phpunit/phpunit']);
        $this->assertSame('not installed', $info['acme/not-a-real-package']);
    }

    #[Test]
    public function a_non_array_packages_config_reports_only_the_root(): void
    {
        config()->set('server-info.packages', 'laravel/framework');

        $this->assertSame(['root'], array_keys($this->app->make(PackagesModule::class)->info()));
    }
}
