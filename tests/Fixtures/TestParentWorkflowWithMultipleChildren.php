<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\child;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

final class TestParentWorkflowWithMultipleChildren extends Workflow
{
    public function execute()
    {
        $child1Promise = child(TestSimpleChildWorkflowWithSignal::class, 'child1');
        $child1Handle = $this->child();
        $child1Handle->approve('first');

        $child2Promise = child(TestSimpleChildWorkflowWithSignal::class, 'child2');
        $child2Handle = $this->child();
        $child2Handle->approve('second');

        $child3Promise = child(TestSimpleChildWorkflowWithSignal::class, 'child3');
        $child3Handle = $this->child();
        $child3Handle->approve('third');

        $allChildHandles = $this->children();

        if (count($allChildHandles) !== 3) {
            return 'wrong_child_count:' . count($allChildHandles);
        }

        $child3Id = $allChildHandles[0]->id();
        $child2Id = $allChildHandles[1]->id();
        $child1Id = $allChildHandles[2]->id();

        if (! ($child3Id > $child2Id && $child2Id > $child1Id)) {
            return "wrong_order:{$child1Id},{$child2Id},{$child3Id}";
        }

        $results = yield ChildWorkflowStub::all([$child1Promise, $child2Promise, $child3Promise]);

        return implode('|', $results);
    }
}
