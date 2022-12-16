<?php

declare(strict_types=1);

namespace Workflow\Serializers;

use Opis\Closure\SerializableClosure;
use function Opis\Closure\serialize as s;
use function Opis\Closure\unserialize as u;

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
        return self::encode(s($data));
    }

    public static function unserialize(string $data)
    {
        SerializableClosure::setSecretKey(config('app.key'));
        return u(self::decode($data));
    }
}
