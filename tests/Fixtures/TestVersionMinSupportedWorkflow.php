<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, getVersion};

class TestVersionMinSupportedWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        // minSupported is 1, so DEFAULT_VERSION (-1) is no longer supported
        $version = yield getVersion(
            'step-1',
            1,  // minSupported: we dropped support for DEFAULT_VERSION
            2   // maxSupported: current version
        );

        $result = match ($version) {
            1 => yield activity(TestVersionedActivityV2::class),
            2 => yield activity(TestVersionedActivityV3::class),
        };

        return [
            'version' => $version,
            'result' => $result,
        ];
    }
}
