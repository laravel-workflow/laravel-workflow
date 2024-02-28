<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
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

        $filesystem->delete($file);
        $filesystem->deleteDirectory(self::FOLDER);

        $this->assertFalse($filesystem->exists(self::FOLDER));
        $this->assertFalse($filesystem->exists($file));

        Artisan::call('make:activity', [
            'name' => self::ACTIVITY,
        ]);

        $this->assertTrue($filesystem->exists($file));

        $filesystem->delete($file);
        $filesystem->deleteDirectory(self::FOLDER);
    }
}
