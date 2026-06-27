<?php

namespace App\Services;

use ZipArchive;

class XlsxParser
{
    /** @return array<int, array<int, string>> indexed rows with column positions as keys, skipping header */
    public function parse(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open xlsx file.');
        }

        $strings = $this->readSharedStrings($zip);
        $rows = $this->readSheet($zip, $strings);
        $zip->close();

        return array_slice($rows, 1); // skip header row
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $strings = [];
        foreach ($doc->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function readSheet(ZipArchive $zip, array $strings): array
    {
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (! $xml) {
            return [];
        }

        $doc = simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $rows = [];

        foreach ($doc->sheetData->row as $row) {
            $rowIdx = (int) $row['r'] - 1;
            foreach ($row->c as $c) {
                $colIdx = $this->colLetterToIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = (string) $c->v;

                if ($type === 's') {
                    $value = $strings[(int) $value] ?? '';
                }

                $rows[$rowIdx][$colIdx] = $value;
            }
        }

        return array_values($rows);
    }

    private function colLetterToIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $col = 0;
        foreach (str_split($m[1]) as $char) {
            $col = $col * 26 + (ord($char) - 64);
        }

        return $col - 1;
    }
}
