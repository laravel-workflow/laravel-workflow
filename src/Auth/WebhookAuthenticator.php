<?php

declare(strict_types=1);

namespace Workflow\Auth;

use Illuminate\Http\Request;

interface WebhookAuthenticator
{
    public function validate(Request $request): Request;
}
