<?php

use App\Services\GoogleSheetsBackupService;

it('normalizes sparse rows as list arrays for Google Sheets payloads', function () {
    $service = new class extends GoogleSheetsBackupService
    {
        public function expose(array $rows): array
        {
            $method = new ReflectionMethod(GoogleSheetsBackupService::class, 'normalizeRows');

            return $method->invoke($this, $rows);
        }
    };

    $rows = [
        ['id', 'title', 'tags'],
        [0 => 1, 1 => 'First', 4 => ['a', 'b']],
    ];

    expect($service->expose($rows))->toBe([
        ['id', 'title', 'tags'],
        [1, 'First', '["a","b"]'],
    ]);
});
