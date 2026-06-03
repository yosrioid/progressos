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
        [0 => 2, 1 => 'Second', 2 => null, 4 => 'done'],
    ];

    expect($service->expose($rows))->toBe([
        ['id', 'title', 'tags'],
        [1, 'First', '["a","b"]'],
        [2, 'Second', '', 'done'],
    ]);
});
