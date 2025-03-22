<?php

declare(strict_types=1);

if (! class_exists('\Workflow\Models\Model')) {
    class_alias(\Illuminate\Database\Eloquent\Model::class, '\Workflow\Models\Model');
}
