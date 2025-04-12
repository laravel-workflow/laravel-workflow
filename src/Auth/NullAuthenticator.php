<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

class NullAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): Request
    {
        return $request;
    }
}
