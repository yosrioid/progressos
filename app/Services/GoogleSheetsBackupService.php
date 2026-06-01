<?php

namespace App\Services;

use App\Models\BackupConnection;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\ValueRange;
use InvalidArgumentException;

class GoogleSheetsBackupService
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function append(BackupConnection $connection, string $sheetName, array $rows): string
    {
        if (! filled($connection->spreadsheet_id)) {
            throw new InvalidArgumentException('Spreadsheet ID is required before syncing to Google Sheets.');
        }

        $credentials = $connection->credentials;
        if (! is_array($credentials) || ! filled($credentials['client_email'] ?? null) || ! filled($credentials['private_key'] ?? null)) {
            throw new InvalidArgumentException('A valid Google service account JSON credential is required before syncing.');
        }

        $service = $this->service($credentials);
        $this->ensureSheetExists($service, $connection->spreadsheet_id, $sheetName);

        $body = new ValueRange(['values' => $this->normalizeRows($rows)]);
        $service->spreadsheets_values->append($connection->spreadsheet_id, $this->quoteSheetName($sheetName).'!A1', $body, [
            'valueInputOption' => 'RAW',
            'insertDataOption' => 'INSERT_ROWS',
        ]);

        return "https://docs.google.com/spreadsheets/d/{$connection->spreadsheet_id}/edit";
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function service(array $credentials): Sheets
    {
        $client = new GoogleClient;
        $client->setApplicationName('ProgressOS');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig($credentials);

        return new Sheets($client);
    }

    private function ensureSheetExists(Sheets $service, string $spreadsheetId, string $sheetName): void
    {
        try {
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);
            foreach ($spreadsheet->getSheets() ?? [] as $sheet) {
                if ($sheet->getProperties()?->getTitle() === $sheetName) {
                    return;
                }
            }

            $service->spreadsheets->batchUpdate($spreadsheetId, new BatchUpdateSpreadsheetRequest([
                'requests' => [[
                    'addSheet' => [
                        'properties' => [
                            'title' => $sheetName,
                        ],
                    ],
                ]],
            ]));
        } catch (GoogleServiceException $exception) {
            throw new InvalidArgumentException('Google Sheets could not prepare the target sheet: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, string|int|float|null>>
     */
    private function normalizeRows(array $rows): array
    {
        return array_values(array_map(fn (array $row) => array_values(array_map(fn (mixed $value) => $this->normalizeCell($value), $row)), $rows));
    }

    private function normalizeCell(mixed $value): string|int|float|null
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            $encoded = json_encode($value);

            return $encoded === false ? null : $encoded;
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }

    private function quoteSheetName(string $sheetName): string
    {
        return "'".str_replace("'", "''", $sheetName)."'";
    }
}
