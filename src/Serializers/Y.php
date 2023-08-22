<?php

declare(strict_types=1);

namespace Workflow\Serializers;

use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

final class Y implements SerializerInterface
{
    use SerializesAndRestoresModelIdentifiers;

    private static $instance = null;

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

    public static function serializable($data): bool
    {
        try {
            serialize($data);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function serializeModels($data)
    {
        if (is_array($data)) {
            $self = self::getInstance();
            foreach ($data as $key => $value) {
                $data[$key] = $self->getSerializedPropertyValue($value);
            }
        } elseif ($data instanceof Throwable) {
            $data = [
                'class' => get_class($data),
                'message' => $data->getMessage(),
                'code' => $data->getCode(),
                'line' => $data->getLine(),
                'file' => $data->getFile(),
                'trace' => collect($data->getTrace())
                    ->filter(static fn ($trace) => self::serializable($trace))
                    ->toArray(),
            ];
        }
        return $data;
    }

    public static function unserializeModels($data)
    {
        if (is_array($data)) {
            $self = self::getInstance();
            foreach ($data as $key => $value) {
                $data[$key] = $self->getRestoredPropertyValue($value);
            }
        }
        return $data;
    }

    public static function serialize($data): string
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $data = self::serializeModels($data);
        return self::encode(serialize(new SerializableClosure(static fn () => $data)));
    }

    public static function unserialize(string $data)
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $unserialized = unserialize(self::decode($data));
        if ($unserialized instanceof SerializableClosure) {
            $unserialized = ($unserialized->getClosure())();
        }
        return self::unserializeModels($unserialized);
    }
}
