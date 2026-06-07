<?php

namespace App\Services;

class MemoryMatchGenerator
{
    public function generate(int $rows, int $cols, int $pairs, ?string $seed = null): array
    {
        $cards = array_merge(range(1, $pairs), range(1, $pairs));

        if ($seed !== null) {
            mt_srand(crc32($seed.'_progressos_memory'));
            for ($i = count($cards) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
            }
        } else {
            shuffle($cards);
        }

        return [
            'rows' => $rows,
            'cols' => $cols,
            'pairs' => $pairs,
            'cards' => $cards,
        ];
    }
}
