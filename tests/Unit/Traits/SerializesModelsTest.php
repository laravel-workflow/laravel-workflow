<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Illuminate\Contracts\Database\ModelIdentifier;
use Tests\Fixtures\TestModelActivity;
use Tests\Fixtures\TestModelWorkflow;
use Tests\TestCase;
use Workbench\App\Models\User;
use Workflow\Models\StoredWorkflow;

class SerializesModelsTest extends TestCase
{
    public function testActivityJobSerializationWithModel(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $storedWorkflow = StoredWorkflow::create([
            'class' => TestModelWorkflow::class,
        ]);
        $activity = new TestModelActivity(0, now()->toDateTimeString(), $storedWorkflow, $user);

        $serialized = serialize($activity);

        $unserialized = unserialize($serialized);

        $userArgument = $unserialized->arguments[0];

        $this->assertStringContainsString(ModelIdentifier::class, $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
        $this->assertInstanceOf(User::class, $userArgument);
        $this->assertEquals($user->id, $userArgument->id);
    }

    public function testWorkflowJobSerializationWithModel(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $storedWorkflow = StoredWorkflow::create([
            'class' => TestModelWorkflow::class,
        ]);
        $workflow = new TestModelWorkflow($storedWorkflow, $user);

        $serialized = serialize($workflow);

        $unserialized = unserialize($serialized);

        $userArgument = $unserialized->arguments[0];

        $this->assertStringContainsString(ModelIdentifier::class, $serialized);
        $this->assertStringNotContainsString($user->email, $serialized);
        $this->assertInstanceOf(User::class, $userArgument);
        $this->assertEquals($user->id, $userArgument->id);
    }
}
