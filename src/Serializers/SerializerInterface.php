<?php

declare(strict_types=1);

namespace Workflow\Serializers;

interface SerializerInterface
{
    public static function encode(string $data): string;

    public static function decode(string $data): string;

    public static function serialize($data): string;

    public static function unserialize(string $data);
}
