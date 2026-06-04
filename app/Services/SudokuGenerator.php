<?php

namespace App\Services;

class SudokuGenerator
{
    private const CLUE_COUNTS = [
        'easy'   => 40,
        'medium' => 32,
        'hard'   => 26,
        'expert' => 22,
        'daily'  => 30,
    ];

    public function generate(string $level): array
    {
        $solution = $this->generateSolution();
        $targetClues = self::CLUE_COUNTS[$level] ?? 40;
        $puzzle = $this->removeCells($solution, $targetClues);

        return ['puzzle' => $puzzle, 'solution' => $solution];
    }

    public function generateForDate(string $date): array
    {
        mt_srand(crc32($date));
        return $this->generate('daily');
    }

    private function generateSolution(): array
    {
        $grid = array_fill(0, 9, array_fill(0, 9, null));
        $this->fillGrid($grid);

        return $grid;
    }

    private function fillGrid(array &$grid): bool
    {
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                if ($grid[$row][$col] === null) {
                    $numbers = range(1, 9);
                    shuffle($numbers);
                    foreach ($numbers as $num) {
                        if ($this->isValidPlacement($grid, $row, $col, $num)) {
                            $grid[$row][$col] = $num;
                            if ($this->fillGrid($grid)) {
                                return true;
                            }
                            $grid[$row][$col] = null;
                        }
                    }

                    return false;
                }
            }
        }

        return true;
    }

    private function isValidPlacement(array $grid, int $row, int $col, int $num): bool
    {
        for ($c = 0; $c < 9; $c++) {
            if ($grid[$row][$c] === $num) {
                return false;
            }
        }
        for ($r = 0; $r < 9; $r++) {
            if ($grid[$r][$col] === $num) {
                return false;
            }
        }
        $br = intdiv($row, 3) * 3;
        $bc = intdiv($col, 3) * 3;
        for ($r = $br; $r < $br + 3; $r++) {
            for ($c = $bc; $c < $bc + 3; $c++) {
                if ($grid[$r][$c] === $num) {
                    return false;
                }
            }
        }

        return true;
    }

    private function removeCells(array $solution, int $targetClues): array
    {
        $puzzle = $solution;
        $positions = range(0, 80);
        shuffle($positions);

        $maxRemove = 81 - $targetClues;
        $removed = 0;

        foreach ($positions as $pos) {
            if ($removed >= $maxRemove) {
                break;
            }

            $row = intdiv($pos, 9);
            $col = $pos % 9;
            $backup = $puzzle[$row][$col];
            $puzzle[$row][$col] = null;

            if ($this->countSolutions($puzzle) === 1) {
                $removed++;
            } else {
                $puzzle[$row][$col] = $backup;
            }
        }

        return $puzzle;
    }

    private function countSolutions(array $grid, int $limit = 2): int
    {
        $minCount = 10;
        $minRow = -1;
        $minCol = -1;

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                if ($grid[$r][$c] === null) {
                    $candidates = $this->getCandidates($grid, $r, $c);
                    $count = count($candidates);
                    if ($count === 0) {
                        return 0;
                    }
                    if ($count < $minCount) {
                        $minCount = $count;
                        $minRow = $r;
                        $minCol = $c;
                        if ($count === 1) {
                            break 2;
                        }
                    }
                }
            }
        }

        if ($minRow === -1) {
            return 1;
        }

        $solutions = 0;
        foreach ($this->getCandidates($grid, $minRow, $minCol) as $num) {
            $grid[$minRow][$minCol] = $num;
            $solutions += $this->countSolutions($grid, $limit - $solutions);
            $grid[$minRow][$minCol] = null;
            if ($solutions >= $limit) {
                return $solutions;
            }
        }

        return $solutions;
    }

    private function getCandidates(array $grid, int $row, int $col): array
    {
        $used = [];
        for ($c = 0; $c < 9; $c++) {
            if ($grid[$row][$c] !== null) {
                $used[$grid[$row][$c]] = true;
            }
        }
        for ($r = 0; $r < 9; $r++) {
            if ($grid[$r][$col] !== null) {
                $used[$grid[$r][$col]] = true;
            }
        }
        $br = intdiv($row, 3) * 3;
        $bc = intdiv($col, 3) * 3;
        for ($r = $br; $r < $br + 3; $r++) {
            for ($c = $bc; $c < $bc + 3; $c++) {
                if ($grid[$r][$c] !== null) {
                    $used[$grid[$r][$c]] = true;
                }
            }
        }

        return array_values(array_diff(range(1, 9), array_keys($used)));
    }
}
