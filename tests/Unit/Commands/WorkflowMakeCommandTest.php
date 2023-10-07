<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WorkflowMakeCommandTest extends TestCase
{
    private const WORKFLOW = 'TestWorkflow';

    private const FOLDER = 'Workflows';

    public function testMakeCommand(): void
    {
        $file = self::FOLDER . '/' . self::WORKFLOW . '.php';

        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => app_path(),
        ]);

        $this->assertFalse($filesystem->exists(self::FOLDER));
        $this->assertFalse($filesystem->exists($file));

        $this->artisan('make:workflow ' . self::WORKFLOW)->assertSuccessful();

        $this->assertTrue($filesystem->exists($file));

        $filesystem->delete($file);
        $filesystem->deleteDirectory(self::FOLDER);
    }
}
