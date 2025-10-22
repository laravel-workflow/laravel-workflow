<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Exception;
use Illuminate\Database\Eloquent\Model;

trait WorkflowRelationships
{
    public static function getRelationshipPivotAttribute(Model $relatedModel, string $attribute, $default = null)
    {
        // Try to get pivot from relations array first (MongoDB/SQL compatible - avoids getAttribute)
        if (method_exists($relatedModel, 'relationLoaded') && $relatedModel->relationLoaded('pivot')) {
            try {
                // Access relations array directly to avoid triggering getAttribute/property access
                $relations = $relatedModel->getRelations();
                if (isset($relations['pivot']) && is_object($relations['pivot'])) {
                    $pivotModel = $relations['pivot'];
                    // Access pivot's attributes array directly to avoid __get/getAttribute on pivot model
                    if (method_exists($pivotModel, 'getAttributes')) {
                        $pivotAttributes = $pivotModel->getAttributes();
                        if (array_key_exists($attribute, $pivotAttributes)) {
                            return $pivotAttributes[$attribute];
                        }
                    }
                    // Fallback for non-MongoDB pivot
                    if (!str_contains(get_class($pivotModel), 'MongoDB')) {
                        if (isset($pivotModel->{$attribute})) {
                            return $pivotModel->{$attribute};
                        }
                    }
                }
            } catch (Exception $e) {
                // continue
            }
        }

        // For MongoDB models, don't try property access which triggers getAttribute
        // Just return default if relation wasn't loaded
        if (str_contains(get_class($relatedModel), 'MongoDB') || is_a($relatedModel, 'MongoDB\Laravel\Eloquent\Model', true)) {
            return $default;
        }
        
        // Check pivot property (SQL compatibility only)
        if (property_exists($relatedModel, 'pivot') && $relatedModel->pivot !== null) {
            return $relatedModel->pivot->{$attribute} ?? $default;
        }

        // Check as direct attribute (SQL only)
        if (isset($relatedModel->{$attribute})) {
            return $relatedModel->{$attribute};
        }

        if (method_exists($relatedModel, 'getAttribute')) {
            $value = $relatedModel->getAttribute($attribute);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }
}
