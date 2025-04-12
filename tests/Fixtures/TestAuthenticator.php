<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Http\Request;
use Workflow\Auth\WebhookAuthenticator;

class TestAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): Request
    {
        if ($request->header('Authorization')) {
            return $request;
        }
        abort(401, 'Unauthorized');
    }
}
