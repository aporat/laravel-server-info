<?php

namespace Aporat\ServerInfo\Tests;

use Aporat\ServerInfo\ServerInfoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ServerInfoServiceProvider::class];
    }
}
