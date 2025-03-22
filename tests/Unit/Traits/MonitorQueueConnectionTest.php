<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Tests\TestCase;
use Workflow\Traits\MonitorQueueConnection;

class MonitorQueueConnectionTest extends TestCase
{
    public function testReturnsDefaultConnection(): void
    {
        config([
            'queue.default' => 'sync',
        ]);

        $instance = $this->makeAnonymousTraitInstance();

        $this->assertSame(config('queue.default'), $instance->viaConnection());
    }

    public function testReturnsDefaultQueue(): void
    {
        config([
            'queue.default' => 'sync',
        ]);

        $instance = $this->makeAnonymousTraitInstance();

        $this->assertSame('default', $instance->viaQueue());
    }

    public function testReturnsCustomConnection(): void
    {
        config([
            'queue.default' => 'sync',
            'workflows.monitor_connection' => 'custom_connection',
        ]);

        $instance = $this->makeAnonymousTraitInstance();

        $this->assertSame('custom_connection', $instance->viaConnection());
    }

    public function testReturnsCustomQueue(): void
    {
        config([
            'queue.default' => 'sync',
            'workflows.monitor_queue' => 'custom_queue',
        ]);

        $instance = $this->makeAnonymousTraitInstance();

        $this->assertSame('custom_queue', $instance->viaQueue());
    }

    private function makeAnonymousTraitInstance(): object
    {
        return new class() {
            use MonitorQueueConnection;
        };
    }
}
