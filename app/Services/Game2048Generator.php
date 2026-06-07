<?php

namespace App\Services;

class Game2048Generator
{
    public function generate(string $level): array
    {
        $size = $level === 'large' ? 5 : 4;
        $board = array_fill(0, $size, array_fill(0, $size, 0));

        if ($level === 'challenge') {
            // Start more constrained: 3 tiles with one higher value
            $this->addTile($board, $size, 2);
            $this->addTile($board, $size, 2);
            $this->addTile($board, $size, 4);
        } else {
            $this->addRandomTile($board, $size);
            $this->addRandomTile($board, $size);
        }

        return [
            'size' => $size,
            'board' => $board,
            'win_target' => $level === 'challenge' ? 4096 : 2048,
        ];
    }

    public function generateForDate(string $date): array
    {
        mt_srand(crc32($date.'_progressos_2048'));
        $board = array_fill(0, 4, array_fill(0, 4, 0));
        $this->addRandomTile($board, 4);
        $this->addRandomTile($board, 4);

        return [
            'size' => 4,
            'board' => $board,
            'win_target' => 2048,
        ];
    }

    private function addRandomTile(array &$board, int $size): void
    {
        $this->addTile($board, $size, mt_rand(0, 9) < 9 ? 2 : 4);
    }

    private function addTile(array &$board, int $size, int $value): void
    {
        $empty = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($board[$r][$c] === 0) {
                    $empty[] = [$r, $c];
                }
            }
        }
        if (empty($empty)) {
            return;
        }
        $pos = $empty[array_rand($empty)];
        $board[$pos[0]][$pos[1]] = $value;
    }
}
