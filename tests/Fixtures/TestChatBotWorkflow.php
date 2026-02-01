<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

final class TestChatBotWorkflow extends Workflow
{
    #[SignalMethod]
    public function receive(string $message): void
    {
        $this->inbox->receive($message);
    }

    public function execute()
    {
        $step = 'ask';
        $done = false;
        $message = null;
        while (! $done) {
            if ($step === 'ask') {
                $step = 'answer';
                yield activity(TestChatBotAskActivity::class);
                yield await(fn () => $this->inbox->hasUnread());
                $message = $this->inbox->nextUnread();
            } elseif ($step === 'answer') {
                $step = 'ask';
                $done = yield activity(TestChatBotAnswerActivity::class, $message);
            }
        }

        return 'completed';
    }
}
