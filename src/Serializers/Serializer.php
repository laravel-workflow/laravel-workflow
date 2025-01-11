<?php

declare(strict_types=1);

namespace Workflow\Serializers;

final class Serializer
{
    public static function __callStatic(string $name, array $arguments)
    {
        $instance = static::make();

        if (method_exists($instance, $name)) {
            return $instance->{$name}(...$arguments);
        }
    }

    public static function make(): AbstractSerializer
    {
        return config('serializer', Y::class)::getInstance();
    }
}
