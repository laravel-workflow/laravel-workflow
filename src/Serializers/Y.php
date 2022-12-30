<?php

declare(strict_types=1);

namespace Workflow\Serializers;

use Laravel\SerializableClosure\SerializableClosure;

final class Y implements SerializerInterface
{
    public static function encode(string $data): string
    {
        $output = '';
        for ($i = 0; $i < strlen($data); ++$i) {
            $c = ord($data[$i]);
            $output .= ($c === 0 || $c === 1) ? chr(1) . chr($c + 1) : $data[$i];
        }

        return $output;
    }

    public static function decode(string $data): string
    {
        $output = '';
        $escaped = false;
        for ($i = 0; $i < strlen($data); ++$i) {
            $c = ord($data[$i]);
            if ($escaped) {
                $output .= chr($c - 1);
                $escaped = false;
            } else {
                $escaped = $c === 1;
                $output .= $escaped ? '' : $data[$i];
            }
        }

        return $output;
    }

    public static function serialize($data): string
    {
        SerializableClosure::setSecretKey(config('app.key'));
        if (is_array($data) || $data === null || is_scalar($data)) {
            $serialized = serialize($data);
        } else {
            $serialized = serialize(new SerializableClosure(static fn () => $data));
        }
        return self::encode($serialized);
    }

    public static function unserialize(string $data)
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $unserialized = unserialize(self::decode($data));
        if ($unserialized instanceof SerializableClosure) {
            $unserialized = ($unserialized->getClosure())();
        }
        return $unserialized;
    }
}
