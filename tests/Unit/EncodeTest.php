<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Workflow\Serializers\Y;

final class TestEncode extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testEncode(string $bytes): void
    {
        $decoded = Y::decode(Y::encode($bytes));
        $this->assertSame($bytes, $decoded);
    }

    public function dataProvider(): array
    {
        return [
            'empty' => [''],
            'foo' => ['foo'],
            'bytes' => [random_bytes(4096)],
            'bytes x2' => [random_bytes(8192)],
            'null' => ['\x00'],
            'null x2' => ['\x00\x00'],
            'escape x2' => ['\x01\01'],
            'null escape' => ['\x00\x01'],
            'escape next' => ['\x01\x02'],
            'null escape x2' => ['\x00\x01\x00\x01'],
            'escape next x2' => ['\x01\x02\x01\x02'],
            'escape null escape next' => ['\x01\x00\x01\x02'],
            'next escape null escape' => ['\x02\x01\x00\x01'],
        ];
    }
}
