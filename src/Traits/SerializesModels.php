<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Queue\SerializesModels as LaravelSerializesModels;

trait SerializesModels
{
    use LaravelSerializesModels {
        getSerializedPropertyValue as parentGetSerializedPropertyValue;
        getRestoredPropertyValue as parentGetRestoredPropertyValue;
    }

    public function getSerializedPropertyValue($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $index => $item) {
                $result[$index] = $this->parentGetSerializedPropertyValue($item);
            }
            return $result;
        }

        return $this->parentGetSerializedPropertyValue($value);
    }

    public function getRestoredPropertyValue($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $index => $item) {
                $result[$index] = $this->parentGetRestoredPropertyValue($item);
            }
            return $result;
        }

        return $this->parentGetRestoredPropertyValue($value);
    }
}
