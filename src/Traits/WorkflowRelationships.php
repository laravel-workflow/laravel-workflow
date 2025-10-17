<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;

trait WorkflowRelationships
{
    public static function getPivotAttribute(Model $relatedModel, string $attribute, $default = null)
    {
        if (property_exists($relatedModel, 'pivot') && $relatedModel->pivot !== null) {
            return $relatedModel->pivot->{$attribute} ?? $default;
        }

        if (isset($relatedModel->{$attribute})) {
            return $relatedModel->{$attribute};
        }

        if (method_exists($relatedModel, 'getAttribute')) {
            $value = $relatedModel->getAttribute($attribute);
            if ($value !== null) {
                return $value;
            }
        }

        if (method_exists($relatedModel, 'getRelation')) {
            try {
                $value = $relatedModel->getRelation('pivot');
                if (is_object($value) && isset($value->{$attribute})) {
                    return $value->{$attribute};
                }
            } catch (Exception $e) {
                // continue to default
            }
        }

        return $default;
    }
}
