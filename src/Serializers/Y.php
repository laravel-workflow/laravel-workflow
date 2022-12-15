<?php

declare(strict_types=1);

namespace Workflow\Serializers;

final class Y
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
        return self::encode(serialize($data));
    }

    public static function unserialize(string $data)
    {
        return unserialize(self::decode($data));
    }
}
