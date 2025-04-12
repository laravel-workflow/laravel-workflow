<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

class TokenAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): bool
    {
        return $request->header(config('workflows.webhook_auth.token.header')) === config(
            'workflows.webhook_auth.token.token'
        );
    }
}
