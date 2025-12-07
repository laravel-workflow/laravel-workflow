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
        return strtr($data, [
            "\x00" => "\x01\x01",
            "\x01" => "\x01\x02",
        ]);
    }

    public static function decode(string $data): string
    {
        return strtr($data, [
            "\x01\x01" => "\x00",
            "\x01\x02" => "\x01",
        ]);
    }
}
