<?php

declare(strict_types=1);

namespace Tests\Unit\Serializers;

use Tests\Fixtures\TestEnum;
use Tests\TestCase;
use Throwable;
use Workflow\Serializers\Base64;
use Workflow\Serializers\Serializer;
use Workflow\Serializers\Y;

final class SerializeTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testSerialize($data): void
    {
        $this->testSerializeUnserialize($data, Y::class, Y::class);
        $this->testSerializeUnserialize($data, Base64::class, Base64::class);
        $this->testSerializeUnserialize($data, Y::class, Base64::class);
        $this->testSerializeUnserialize($data, Base64::class, Y::class);
    }

    public static function dataProvider(): array
    {
        return [
            'array []' => [[]],
            'array [[]]' => [[[]]],
            'array assoc' => [
                [
                    'key' => 'value',
                ],
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

    private function testSerializeUnserialize($data, $serializer, $unserializer): void
    {
        config([
            'workflows.serializer' => $serializer,
        ]);
        $serialized = Serializer::serialize($data);
        config([
            'workflows.serializer' => $unserializer,
        ]);
        $unserialized = Serializer::unserialize($serialized);
        if (is_object($data)) {
            if ($data instanceof Throwable) {
                $this->assertEquals([
                    'class' => get_class($data),
                    'message' => $data->getMessage(),
                    'code' => $data->getCode(),
                    'line' => $data->getLine(),
                    'file' => $data->getFile(),
                    'trace' => collect($data->getTrace())
                        ->filter(static fn ($trace) => Serializer::serializable($trace))
                        ->toArray(),
                ], $unserialized);
            } else {
                $this->assertEqualsCanonicalizing($data, $unserialized);
            }
        } else {
            $this->assertSame($data, $unserialized);
        }
    }
}
