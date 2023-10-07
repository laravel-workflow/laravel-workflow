<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ActivityMakeCommandTest extends TestCase
{
    private const ACTIVITY = 'TestActivity';

    private const FOLDER = 'Workflows';

    public function testMakeCommand(): void
    {
        $file = self::FOLDER . '/' . self::ACTIVITY . '.php';

        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => app_path(),
        ]);

        $this->assertFalse($filesystem->exists(self::FOLDER));
        $this->assertFalse($filesystem->exists($file));

        $this->artisan('make:activity ' . self::ACTIVITY)->assertSuccessful();

        $this->assertTrue($filesystem->exists($file));

        $filesystem->delete($file);
        $filesystem->deleteDirectory(self::FOLDER);
    }
}
