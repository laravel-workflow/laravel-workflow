<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Workflow\WorkflowMetadata;
use Workflow\WorkflowOptions;

final class WorkflowMetadataTest extends TestCase
{
    public function testFromSerializedArgumentsReturnsGivenMetadataInstance(): void
    {
        $metadata = new WorkflowMetadata([1, 2], new WorkflowOptions('sync', 'default'));

        $result = WorkflowMetadata::fromSerializedArguments($metadata);

        $this->assertSame($metadata, $result);
    }

    public function testFromSerializedArgumentsReturnsEmptyMetadataForInvalidPayload(): void
    {
        $result = WorkflowMetadata::fromSerializedArguments('not-an-array');

        $this->assertSame([], $result->arguments);
        $this->assertNull($result->options->connection);
        $this->assertNull($result->options->queue);
    }
}
