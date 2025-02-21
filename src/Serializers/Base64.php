<?php

declare(strict_types=1);

namespace Workflow\Serializers;

final class Base64 extends AbstractSerializer
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
        return 'base64:' . base64_encode($data);
    }

    public static function decode(string $data): string
    {
        return base64_decode(substr($data, 7), true);
    }
}
