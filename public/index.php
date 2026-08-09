<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// The committed .env ships placeholder secrets. When a value exists as a REAL
// environment variable (e.g. set by Docker) but PHP runs with
// variables_order=GPCS (no "E", e.g. PHP's built-in dev server), that real
// value only reaches getenv(), never $_ENV/$_SERVER. Dotenv then fills
// $_ENV/$_SERVER from .env and the placeholder shadows the real value.
// Promote real env values over the placeholder defaults before the runtime
// boots and reads them.
foreach (['APP_SECRET', 'API_KEY', 'DATABASE_URL', 'DEFAULT_URI'] as $name) {
    $real = getenv($name);
    $file = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    if (is_string($real) && $real !== ''
        && (null === $file || str_starts_with((string) $file, 'change_me'))) {
        $_ENV[$name] = $_SERVER[$name] = $real;
    }
}

// Ensure Dotenv reads the committed .env defaults for anything still unset.
// (autoload_runtime does this too, but only for vars absent from the real env.)
if (class_exists(Dotenv::class)) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
