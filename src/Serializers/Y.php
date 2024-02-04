<?php

declare(strict_types=1);

namespace Workflow\Serializers;

use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;

final class Y implements SerializerInterface
{
    use SerializesAndRestoresModelIdentifiers;

    private static self $instance;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
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

    public static function serializable(mixed $data): bool
    {
        try {
            serialize($data);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * @template T
     * @param T $data
     * @return (T is Throwable ? array{class: string, message: string, code: int|int, line: int, file: string, trace: mixed[]} : T)
     */
    public static function serializeModels(mixed $data): mixed
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

    public static function unserializeModels(mixed $data): mixed
    {
        if (is_array($data)) {
            $self = self::getInstance();
            foreach ($data as $key => $value) {
                $data[$key] = $self->getRestoredPropertyValue($value);
            }
        }
        return $data;
    }

    public static function serialize(mixed $data): string
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $data = self::serializeModels($data);
        return self::encode(serialize(new SerializableClosure(static fn () => $data)));
    }

    public static function unserialize(string $data): mixed
    {
        SerializableClosure::setSecretKey(config('app.key'));
        $unserialized = unserialize(self::decode($data));
        if ($unserialized instanceof SerializableClosure) {
            $unserialized = ($unserialized->getClosure())();
        }
        return self::unserializeModels($unserialized);
    }
}
