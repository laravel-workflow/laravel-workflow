<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Inbox;
use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

final class TestChatBotWorkflow extends Workflow
{
    public Inbox $messages;

    public function __construct(...$args)
    {
        $this->messages = new Inbox();
        parent::__construct(...$args);
    }

    #[SignalMethod]
    public function receive(string $message): void
    {
        $this->messages->receive($message);
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
                yield await(fn () => $this->messages->hasUnread());
                $message = $this->messages->nextUnread();
            } elseif ($step === 'answer') {
                $step = 'ask';
                $done = yield activity(TestChatBotAnswerActivity::class, $message);
            }
        }

        return 'completed';
    }
}
