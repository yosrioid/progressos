<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relax rate-limiters under Playwright
    |--------------------------------------------------------------------------
    |
    | When true, the rate-limiters (`auth`, `auth-register`, `passwords`,
    | `api-read`, `api-write`, `api-capture`, `api-export`, `api-tokens`) are
    | lifted so the e2e Playwright suite — which logs in many times within a
    | single minute per worker — doesn't trip the throttles.
    |
    | This is also auto-enabled when the env is `testing` or when a
    | `.playwright-running` marker file exists at the project root (created
    | by `tests/e2e/global-setup.ts` and removed on teardown).
    |
    */

    'relax_rate_limits' => env('PROGRESSOS_RELAX_RATE_LIMITS', false),

];
