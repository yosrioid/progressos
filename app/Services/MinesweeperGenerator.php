<?php

namespace App\Services;

class MinesweeperGenerator
{
    public function generate(int $rows, int $cols, int $mineCount, ?string $date = null): array
    {
        $total = $rows * $cols;
        $mineCount = max(1, min($mineCount, $total - 9));

        if ($date !== null) {
            mt_srand(crc32($date.':minesweeper'));
        }

        $positions = range(0, $total - 1);
        for ($i = $total - 1; $i > 0; $i--) {
            $j = $date !== null ? mt_rand(0, $i) : random_int(0, $i);
            [$positions[$i], $positions[$j]] = [$positions[$j], $positions[$i]];
        }

        $mines = array_fill(0, $total, false);
        foreach (array_slice($positions, 0, $mineCount) as $pos) {
            $mines[$pos] = true;
        }

        return [
            'rows' => $rows,
            'cols' => $cols,
            'mine_count' => $mineCount,
            'mines' => array_values($mines),
        ];
    }
}
