<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Patches Laravel's built-in PHP web server router so the per-request access log
 * write doesn't emit a PHP `Notice` to STDOUT when the client closes the
 * connection early (Playwright closes HTTP connections after the response, which
 * trips `file_put_contents('php://stdout', ...)` with EPIPE / errno=32).
 *
 * Without this patch, the notice is prepended to JSON responses, which makes
 * `JSON.parse` throw and breaks the Vue SPA auth flow on `php artisan serve`.
 *
 * The patch is idempotent: re-running it is a no-op when already applied.
 */
class PatchVendorServer extends Command
{
    protected $signature = 'progressos:patch-vendor-server';

    protected $description = 'Suppress EPIPE notice in Laravel built-in server router (artisan serve)';

    public function handle(): int
    {
        $file = base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');

        if (! is_file($file)) {
            $this->warn("Server router not found at {$file} — skipping.");

            return self::SUCCESS;
        }

        $contents = file_get_contents($file);

        $original = "file_put_contents('php://stdout', \"[\$formattedDateTime] \$remoteAddress [\$requestMethod] URI: \$uri\\n\");";
        $patched = "@file_put_contents('php://stdout', \"[\$formattedDateTime] \$remoteAddress [\$requestMethod] URI: \$uri\\n\");";

        if (str_contains($contents, $patched)) {
            $this->info('Vendor server router already patched.');

            return self::SUCCESS;
        }

        if (! str_contains($contents, $original)) {
            $this->warn('Original line not found — Laravel may have changed the file format. Skipping.');

            return self::SUCCESS;
        }

        file_put_contents($file, str_replace($original, $patched, $contents));

        $this->info('Patched vendor server router to suppress EPIPE notice.');

        return self::SUCCESS;
    }
}
