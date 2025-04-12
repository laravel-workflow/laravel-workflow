<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

class SignatureAuthenticator implements WebhookAuthenticator
{
    public function validate(Request $request): bool
    {
        return hash_equals(
            $request->header(config('workflows.webhook_auth.signature.header')) ?? '',
            hash_hmac('sha256', $request->getContent(), config('workflows.webhook_auth.signature.secret'))
        );
    }
}
