<?php

declare(strict_types=1);

namespace Tests\Unit\Serializers;

use Closure;
use Tests\Fixtures\TestEnum;
use Tests\TestCase;
use Throwable;
use Workflow\Serializers\Y;

final class SerializeTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testSerialize($data): void
    {
        $unserialized = Y::unserialize(Y::serialize($data));
        if (is_object($data)) {
            if ($data instanceof Throwable) {
                $this->assertEquals([
                    'class' => get_class($data),
                    'message' => $data->getMessage(),
                    'code' => $data->getCode(),
                    'line' => $data->getLine(),
                    'file' => $data->getFile(),
                    'trace' => collect($data->getTrace())
                        ->filter(static fn ($trace) => ! $trace instanceof Closure)
                        ->toArray(),
                ], $unserialized);
            } else {
                $this->assertEqualsCanonicalizing($data, $unserialized);
            }
        } else {
            $this->assertSame($data, $unserialized);
        }
    }

    public function dataProvider(): array
    {
        return [
            'array []' => [[]],
            'array [[]]' => [[[]]],
            'array assoc' => [
                'key' => 'value',
            ],
            'bool true' => [true],
            'bool false' => [false],
            'enum' => [TestEnum::First],
            'enum[]' => [[TestEnum::First]],
            'int(PHP_INT_MIN)' => [PHP_INT_MIN],
            'int(PHP_INT_MAX)' => [PHP_INT_MAX],
            'int(-1)' => [-1],
            'int(0)' => [0],
            'int(1)' => [1],
            'exception' => [new \Exception('test')],
            'float PHP_FLOAT_EPSILON' => [PHP_FLOAT_EPSILON],
            'float PHP_FLOAT_MIN' => [PHP_FLOAT_MIN],
            'float -PHP_FLOAT_MIN' => [-PHP_FLOAT_MIN],
            'float PHP_FLOAT_MAX' => [PHP_FLOAT_MAX],
            'float(-1.123456789)' => [-1.123456789],
            'float(0.0)' => [0.0],
            'float(1.123456789)' => [1.123456789],
            'null' => [null],
            'string empty' => [''],
            'string foo' => ['foo'],
            'string bytes' => [random_bytes(4096)],
        ];
    }
}
