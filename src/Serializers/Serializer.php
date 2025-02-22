<?php

declare(strict_types=1);

namespace Workflow\Serializers;

final class Serializer
{
    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'unserialize') {
            if (str_starts_with($arguments[0], 'base64:')) {
                $instance = Base64::getInstance();
            } else {
                $instance = Y::getInstance();
            }
        } else {
            $instance = config('workflows.serializer', Y::class)::getInstance();
        }

        if (method_exists($instance, $name)) {
            return $instance->{$name}(...$arguments);
        }
    }
}
