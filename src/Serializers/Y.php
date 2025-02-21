<?php

declare(strict_types=1);

namespace Workflow\Serializers;

final class Y extends AbstractSerializer
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

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
}
