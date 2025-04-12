<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

class TokenAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): Request
    {
        if ($request->header(config('workflows.webhook_auth.token.header')) !== config(
            'workflows.webhook_auth.token.token'
        )) {
            abort(401, 'Unauthorized');
        }
        return $request;
    }
}
