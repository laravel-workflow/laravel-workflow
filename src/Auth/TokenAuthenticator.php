<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

class TokenAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): Request
    {
        if (! hash_equals(
            (string) config('workflows.webhook_auth.token.token'),
            (string) $request->header(config('workflows.webhook_auth.token.header'))
        )) {
            abort(401, 'Unauthorized');
        }
        return $request;
    }
}
