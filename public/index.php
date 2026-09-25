<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// No `.env` promotion hack belongs here.
//
// This file used to walk getenv() against $_ENV/$_SERVER to force real
// environment variables to win over the values in the *committed* `.env`. That
// hack existed only because `.env` was tracked, so its placeholder values were
// always present and could shadow real ones under variables_order=GPCS.
//
// The committed `.env` is gone (GUIDING-LIGHT §5.1): `.env.example` and
// `.env.test` are the tracked files, and runtime configuration comes from the
// environment. With nothing committed to shadow anything, the standard Symfony
// bootstrap is all that is needed, and it is all that is here.
return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
