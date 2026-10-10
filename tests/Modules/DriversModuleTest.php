<?php

namespace Aporat\ServerInfo\Tests\Modules;

use Aporat\ServerInfo\Modules\DriversModule;
use Aporat\ServerInfo\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DriversModuleTest extends TestCase
{
    #[Test]
    public function it_reports_the_default_driver_names(): void
    {
        config()->set([
            'cache.default' => 'redis',
            'queue.default' => 'sqs',
            'session.driver' => 'file',
            'mail.default' => 'smtp',
            'filesystems.default' => 's3',
            'broadcasting.default' => 'pusher',
            'logging.default' => 'stack',
            'database.default' => 'mysql',
        ]);

        $module = $this->app->make(DriversModule::class);

        $this->assertSame('drivers', $module->name());
        $this->assertSame([
            'cache' => 'redis',
            'queue' => 'sqs',
            'session' => 'file',
            'mail' => 'smtp',
            'filesystem' => 's3',
            'broadcast' => 'pusher',
            'log' => 'stack',
            'database' => 'mysql',
        ], $module->info());
    }

    #[Test]
    public function missing_values_are_null(): void
    {
        config()->set('broadcasting.default', null);
        config()->set('mail', []);

        $info = $this->app->make(DriversModule::class)->info();

        $this->assertNull($info['broadcast']);
        $this->assertNull($info['mail']);
    }
}
