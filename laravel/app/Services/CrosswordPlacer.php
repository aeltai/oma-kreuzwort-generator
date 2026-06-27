<?php

namespace App\Services;

/**
 * Letter-anchor crossword placement engine.
 * Ported from the original Node `placeCrossword` / `numberWords`.
 *
 * Word objects are associative arrays. Internally each carries a `letters`
 * array (multibyte-safe) and `len`.
 */
class CrosswordPlacer
{
    private int $size = 13;

    /**
     * @param  array  $wordObjects  list of ['word'=>string,'clue'=>string]
     * @return array{gridWidth:int,gridHeight:int,words:array}|null
     */
    public function place(array $wordObjects): ?array
    {
        $nWords = count($wordObjects);

        // attach letters arrays
        $words = array_map(function ($w) {
            $letters = WordNormalizer::chars($w['word']);
            return array_merge($w, ['letters' => $letters, 'len' => count($letters)]);
        }, $wordObjects);

        $this->size = match (true) {
            $nWords <= 16 => 13,
            $nWords <= 22 => 15,
            $nWords <= 28 => 17,
            default => 19,
        };

        // sort by length desc
        usort($words, fn ($a, $b) => $b['len'] <=> $a['len']);
        $sorted = $words;

        $bestPlaced = null;
        $bestScore = -1;
        $attempts = 40;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt === 0) {
                $ordering = $sorted;
                $firstDir = 'across';
            } elseif ($attempt < 10) {
                // Rotate which long word seeds the grid; alternate direction.
                $firstIdx = ($attempt - 1) % min(5, count($sorted));
                $ordering = array_merge(
                    [$sorted[$firstIdx]],
                    array_values(array_filter($sorted, fn ($_, $i) => $i !== $firstIdx, ARRAY_FILTER_USE_BOTH))
                );
                $firstDir = ($attempt % 2 === 0) ? 'across' : 'down';
            } else {
                $top = array_slice($sorted, 0, 2);
                $rest = array_slice($sorted, 2);
                shuffle($rest);
                $ordering = array_merge($top, $rest);
                $firstDir = 'across';
            }

            $result = match ($attempt % 3) {
                2 => $this->dynamicOrderPlacement($ordering, $firstDir),
                1 => $this->buildUpPlacement($ordering, $firstDir),
                default => $this->tryPlacement($ordering, $firstDir),
            };

            $placedCount = count($result['placed']);
            // Prefer more placed words; crossings only break ties.
            $sc = $placedCount * 1000 + $result['totalCrossings'];
            $bestCount = $bestPlaced ? count($bestPlaced) : 0;
            if ($sc > $bestScore || $placedCount > $bestCount) {
                $bestScore = $sc;
                $bestPlaced = $result['placed'];
            }
            if ($placedCount >= $nWords) {
                break;
            }
        }

        if (!$bestPlaced || count($bestPlaced) < max(8, (int) floor($nWords * 0.7))) {
            return null;
        }

        // Trim to bounding box
        $minR = $this->size;
        $maxR = 0;
        $minC = $this->size;
        $maxC = 0;
        foreach ($bestPlaced as $p) {
            $eR = $p['direction'] === 'down' ? $p['row'] + $p['len'] - 1 : $p['row'];
            $eC = $p['direction'] === 'across' ? $p['col'] + $p['len'] - 1 : $p['col'];
            $minR = min($minR, $p['row']);
            $maxR = max($maxR, $eR);
            $minC = min($minC, $p['col']);
            $maxC = max($maxC, $eC);
        }

        $placedWords = array_map(function ($p) use ($minR, $minC) {
            return [
                'word' => $p['word'],
                'clue' => $p['clue'] ?? '',
                'letters' => $p['letters'],
                'len' => $p['len'],
                'row' => $p['row'] - $minR,
                'col' => $p['col'] - $minC,
                'direction' => $p['direction'],
            ];
        }, $bestPlaced);

        return [
            'gridWidth' => $maxC - $minC + 1,
            'gridHeight' => $maxR - $minR + 1,
            'words' => $placedWords,
        ];
    }

    /**
     * Try to place at least $targetCount words from a (possibly larger) pool.
     *
     * @param  array  $wordObjects
     * @return array{gridWidth:int,gridHeight:int,words:array}|null
     */
    public function placeForTarget(array $wordObjects, int $targetCount): ?array
    {
        $pool = array_values($wordObjects);
        if (count($pool) === 0) {
            return null;
        }

        $targetCount = max(1, min($targetCount, count($pool)));
        $minAccept = (int) floor($targetCount * 0.85);

        if (count($pool) <= $targetCount) {
            $result = $this->place($pool);
            return ($result && count($result['words']) >= $minAccept) ? $result : null;
        }

        $best = null;
        $bestCount = 0;

        // Full pool — may place more than target; then retry with that exact subset.
        for ($i = 0; $i < 6; $i++) {
            $ordered = $pool;
            if ($i > 0) {
                shuffle($ordered);
            }
            $result = $this->place($ordered);
            if (!$result) {
                continue;
            }
            $placed = count($result['words']);
            if ($placed >= $targetCount) {
                $subset = $this->pickPlacingSubset($result['words'], $targetCount);
                if ($subset) {
                    return $subset;
                }
            }
            if ($placed > $bestCount) {
                $bestCount = $placed;
                $best = $result;
            }
        }

        // Random subsets of exactly targetCount words from the oversized pool.
        for ($i = 0; $i < 20; $i++) {
            $subset = $this->pickWordSubset($pool, $targetCount, $i);
            $result = $this->place($subset);
            if (!$result) {
                continue;
            }
            $placed = count($result['words']);
            if ($placed >= $targetCount) {
                return $result;
            }
            if ($placed > $bestCount) {
                $bestCount = $placed;
                $best = $result;
            }
        }

        return ($best && $bestCount >= $minAccept) ? $best : null;
    }

    /** @param  array<int, array{word:string,clue?:string}>  $pool */
    private function pickWordSubset(array $pool, int $target, int $seed): array
    {
        $scored = array_map(function ($w) use ($pool) {
            $letters = WordNormalizer::chars($w['word']);
            $freq = $this->letterFrequencyScore($letters, $pool);
            return ['w' => $w, 'score' => $freq + count($letters) * 0.1];
        }, $pool);

        if ($seed === 0) {
            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
            return array_map(fn ($x) => $x['w'], array_slice($scored, 0, $target));
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, min(count($scored), $target + 8));
        $pick = array_map(fn ($x) => $x['w'], $top);
        shuffle($pick);
        return array_slice($pick, 0, $target);
    }

    /** @param  array<int, array{word:string,clue?:string}>  $pool */
    private function letterFrequencyScore(array $letters, array $pool): float
    {
        $freq = [];
        foreach ($pool as $w) {
            foreach (WordNormalizer::chars($w['word']) as $ch) {
                $freq[$ch] = ($freq[$ch] ?? 0) + 1;
            }
        }
        $score = 0.0;
        foreach ($letters as $ch) {
            $score += $freq[$ch] ?? 0;
        }
        return $score;
    }

    /**
     * From words known to co-exist in one grid, find a subset of $target that still fills.
     *
     * @param  array<int, array{word:string,clue?:string,...}>  $placedWords
     */
    private function pickPlacingSubset(array $placedWords, int $target): ?array
    {
        if (count($placedWords) <= $target) {
            $r = $this->place($placedWords);
            return ($r && count($r['words']) >= count($placedWords)) ? $r : null;
        }

        $sorted = $placedWords;
        usort($sorted, fn ($a, $b) => strlen($b['word']) <=> strlen($a['word']));
        $r = $this->place(array_slice($sorted, 0, $target));
        if ($r && count($r['words']) >= $target) {
            return $r;
        }

        for ($i = 0; $i < 10; $i++) {
            $pick = $placedWords;
            shuffle($pick);
            $r = $this->place(array_slice($pick, 0, $target));
            if ($r && count($r['words']) >= $target) {
                return $r;
            }
        }

        return null;
    }

    private function hasLetter(array &$grid, int $r, int $c): bool
    {
        return $r >= 0 && $r < $this->size && $c >= 0 && $c < $this->size && $grid[$r][$c] !== null;
    }

    /** @return int crossings, or -1 if invalid. */
    private function evaluatePlace(array &$grid, array &$dirGrid, array $letters, int $len, int $row, int $col, string $dir): int
    {
        $endR = $dir === 'down' ? $row + $len - 1 : $row;
        $endC = $dir === 'across' ? $col + $len - 1 : $col;
        if ($row < 0 || $col < 0 || $endR >= $this->size || $endC >= $this->size) {
            return -1;
        }

        if ($dir === 'across') {
            if ($this->hasLetter($grid, $row, $col - 1)) return -1;
            if ($this->hasLetter($grid, $row, $col + $len)) return -1;
        } else {
            if ($this->hasLetter($grid, $row - 1, $col)) return -1;
            if ($this->hasLetter($grid, $row + $len, $col)) return -1;
        }

        $crossings = 0;
        for ($i = 0; $i < $len; $i++) {
            $r = $dir === 'down' ? $row + $i : $row;
            $c = $dir === 'across' ? $col + $i : $col;
            $existing = $grid[$r][$c];
            if ($existing !== null) {
                if ($existing !== $letters[$i]) return -1;
                if ($dirGrid[$r][$c] === $dir) return -1;
                $crossings++;
            } else {
                if ($dir === 'across') {
                    if ($this->hasLetter($grid, $r - 1, $c) || $this->hasLetter($grid, $r + 1, $c)) return -1;
                } else {
                    if ($this->hasLetter($grid, $r, $c - 1) || $this->hasLetter($grid, $r, $c + 1)) return -1;
                }
            }
        }
        return $crossings;
    }

    private function commitWord(array &$grid, array &$dirGrid, array &$letterCells, array &$placed, int &$totalCrossings, array $wordObj, int $row, int $col, string $dir, int $crossings): void
    {
        $letters = $wordObj['letters'];
        $len = $wordObj['len'];
        for ($i = 0; $i < $len; $i++) {
            $r = $dir === 'down' ? $row + $i : $row;
            $c = $dir === 'across' ? $col + $i : $col;
            $grid[$r][$c] = $letters[$i];
            $existingDir = $dirGrid[$r][$c];
            $dirGrid[$r][$c] = ($existingDir && $existingDir !== $dir) ? 'both' : $dir;
            $letterCells[$letters[$i]][] = ['r' => $r, 'c' => $c];
        }
        $placed[] = array_merge($wordObj, ['row' => $row, 'col' => $col, 'direction' => $dir]);
        $totalCrossings += $crossings;
    }

    private function makeGrid(): array
    {
        return array_fill(0, $this->size, array_fill(0, $this->size, null));
    }

    private function tryPlacement(array $ordering, string $firstDir = 'across'): array
    {
        $grid = $this->makeGrid();
        $dirGrid = $this->makeGrid();
        $placed = [];
        $totalCrossings = 0;
        $letterCells = [];

        $first = $ordering[0];
        if ($firstDir === 'down') {
            $startRow = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, $startRow, (int) floor($this->size / 2), 'down', 0);
        } else {
            $startCol = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, (int) floor($this->size / 2), $startCol, 'across', 0);
        }

        for ($wi = 1; $wi < count($ordering); $wi++) {
            $wordObj = $ordering[$wi];
            $letters = $wordObj['letters'];
            $len = $wordObj['len'];
            $best = null;
            $bestScore = -INF;
            $seen = [];

            for ($wordPos = 0; $wordPos < $len; $wordPos++) {
                $ch = $letters[$wordPos];
                $cells = $letterCells[$ch] ?? null;
                if (!$cells) continue;
                foreach ($cells as $cell) {
                    $gr = $cell['r'];
                    $gc = $cell['c'];

                    $rowA = $gr;
                    $colA = $gc - $wordPos;
                    $keyA = "A,{$rowA},{$colA}";
                    if (!isset($seen[$keyA])) {
                        $seen[$keyA] = true;
                        $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowA, $colA, 'across');
                        if ($cr > 0) {
                            $dist = abs($rowA - $this->size / 2) + abs($colA + $len / 2 - $this->size / 2);
                            $score = $cr * $cr * 300 - $dist * 10;
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $best = ['row' => $rowA, 'col' => $colA, 'direction' => 'across', 'crossings' => $cr];
                            }
                        }
                    }

                    $rowD = $gr - $wordPos;
                    $colD = $gc;
                    $keyD = "D,{$rowD},{$colD}";
                    if (!isset($seen[$keyD])) {
                        $seen[$keyD] = true;
                        $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowD, $colD, 'down');
                        if ($cr > 0) {
                            $dist = abs($rowD + $len / 2 - $this->size / 2) + abs($colD - $this->size / 2);
                            $score = $cr * $cr * 300 - $dist * 10;
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $best = ['row' => $rowD, 'col' => $colD, 'direction' => 'down', 'crossings' => $cr];
                            }
                        }
                    }
                }
            }

            if (!$best) {
                foreach (['across', 'down'] as $dir) {
                    for ($r = 0; $r < $this->size; $r++) {
                        for ($c = 0; $c < $this->size; $c++) {
                            $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $r, $c, $dir);
                            if ($cr <= 0) continue;
                            $dist = abs($r - $this->size / 2) + abs($c + ($dir === 'across' ? $len / 2 : 0) - $this->size / 2);
                            $score = $cr * $cr * 300 - $dist * 10;
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $best = ['row' => $r, 'col' => $c, 'direction' => $dir, 'crossings' => $cr];
                            }
                        }
                    }
                }
            }

            if ($best) {
                $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $wordObj, $best['row'], $best['col'], $best['direction'], $best['crossings']);
            }
        }

        return ['placed' => $placed, 'totalCrossings' => $totalCrossings];
    }

    private function dynamicOrderPlacement(array $seedOrdering, string $firstDir = 'across'): array
    {
        $grid = $this->makeGrid();
        $dirGrid = $this->makeGrid();
        $placed = [];
        $totalCrossings = 0;
        $letterCells = [];

        $shareScore = function (array $letters) use (&$letterCells): int {
            $n = 0;
            foreach ($letters as $ch) {
                if (isset($letterCells[$ch])) $n++;
            }
            return $n;
        };

        $first = $seedOrdering[0];
        if ($firstDir === 'down') {
            $startRow = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, $startRow, (int) floor($this->size / 2), 'down', 0);
        } else {
            $startCol = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, (int) floor($this->size / 2), $startCol, 'across', 0);
        }

        $pool = array_slice($seedOrdering, 1);

        while (count($pool) > 0) {
            usort($pool, function ($a, $b) use ($shareScore) {
                $ds = $shareScore($b['letters']) - $shareScore($a['letters']);
                return $ds !== 0 ? $ds : ($b['len'] <=> $a['len']);
            });

            $placedAny = false;
            for ($pi = 0; $pi < count($pool); $pi++) {
                $wordObj = $pool[$pi];
                $letters = $wordObj['letters'];
                $len = $wordObj['len'];
                $best = null;
                $bestScore = -INF;
                $seen = [];

                for ($wordPos = 0; $wordPos < $len; $wordPos++) {
                    $ch = $letters[$wordPos];
                    $cells = $letterCells[$ch] ?? null;
                    if (!$cells) continue;
                    foreach ($cells as $cell) {
                        $gr = $cell['r'];
                        $gc = $cell['c'];

                        $rowA = $gr;
                        $colA = $gc - $wordPos;
                        $keyA = "A,{$rowA},{$colA}";
                        if (!isset($seen[$keyA])) {
                            $seen[$keyA] = true;
                            $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowA, $colA, 'across');
                            if ($cr > 0) {
                                $dist = abs($rowA - $this->size / 2) + abs($colA + $len / 2 - $this->size / 2);
                                $score = $cr * $cr * 300 - $dist * 10;
                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $best = ['row' => $rowA, 'col' => $colA, 'direction' => 'across', 'crossings' => $cr];
                                }
                            }
                        }

                        $rowD = $gr - $wordPos;
                        $colD = $gc;
                        $keyD = "D,{$rowD},{$colD}";
                        if (!isset($seen[$keyD])) {
                            $seen[$keyD] = true;
                            $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowD, $colD, 'down');
                            if ($cr > 0) {
                                $dist = abs($rowD + $len / 2 - $this->size / 2) + abs($colD - $this->size / 2);
                                $score = $cr * $cr * 300 - $dist * 10;
                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $best = ['row' => $rowD, 'col' => $colD, 'direction' => 'down', 'crossings' => $cr];
                                }
                            }
                        }
                    }
                }

                if (!$best) {
                    foreach (['across', 'down'] as $dir) {
                        for ($r = 0; $r < $this->size; $r++) {
                            for ($c = 0; $c < $this->size; $c++) {
                                $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $r, $c, $dir);
                                if ($cr <= 0) continue;
                                $dist = abs($r - $this->size / 2) + abs($c - $this->size / 2);
                                $score = $cr * $cr * 300 - $dist * 10;
                                if ($score > $bestScore) {
                                    $bestScore = $score;
                                    $best = ['row' => $r, 'col' => $c, 'direction' => $dir, 'crossings' => $cr];
                                }
                            }
                        }
                    }
                }

                if ($best) {
                    $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $wordObj, $best['row'], $best['col'], $best['direction'], $best['crossings']);
                    array_splice($pool, $pi, 1);
                    $placedAny = true;
                    break;
                }
            }
            if (!$placedAny) break;
        }

        return ['placed' => $placed, 'totalCrossings' => $totalCrossings];
    }

    /** Greedily pick the next word from the pool that fits best on the current grid. */
    private function buildUpPlacement(array $seedOrdering, string $firstDir = 'across'): array
    {
        $grid = $this->makeGrid();
        $dirGrid = $this->makeGrid();
        $placed = [];
        $totalCrossings = 0;
        $letterCells = [];

        $first = $seedOrdering[0];
        if ($firstDir === 'down') {
            $startRow = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, $startRow, (int) floor($this->size / 2), 'down', 0);
        } else {
            $startCol = max(0, (int) floor(($this->size - $first['len']) / 2));
            $this->commitWord($grid, $dirGrid, $letterCells, $placed, $totalCrossings, $first, (int) floor($this->size / 2), $startCol, 'across', 0);
        }

        $pool = array_slice($seedOrdering, 1);

        while (count($pool) > 0) {
            $bestPi = null;
            $bestPlacement = null;
            $bestScore = -INF;

            foreach ($pool as $pi => $wordObj) {
                $candidate = $this->findBestPlacement($grid, $dirGrid, $letterCells, $wordObj);
                if ($candidate && $candidate['score'] > $bestScore) {
                    $bestScore = $candidate['score'];
                    $bestPi = $pi;
                    $bestPlacement = $candidate;
                }
            }

            if ($bestPi === null || !$bestPlacement) {
                break;
            }

            $wordObj = $pool[$bestPi];
            $this->commitWord(
                $grid,
                $dirGrid,
                $letterCells,
                $placed,
                $totalCrossings,
                $wordObj,
                $bestPlacement['row'],
                $bestPlacement['col'],
                $bestPlacement['direction'],
                $bestPlacement['crossings']
            );
            array_splice($pool, $bestPi, 1);
        }

        return ['placed' => $placed, 'totalCrossings' => $totalCrossings];
    }

    /** @return array{row:int,col:int,direction:string,crossings:int,score:float}|null */
    private function findBestPlacement(array &$grid, array &$dirGrid, array &$letterCells, array $wordObj): ?array
    {
        $letters = $wordObj['letters'];
        $len = $wordObj['len'];
        $best = null;
        $bestScore = -INF;
        $seen = [];

        for ($wordPos = 0; $wordPos < $len; $wordPos++) {
            $ch = $letters[$wordPos];
            $cells = $letterCells[$ch] ?? null;
            if (!$cells) {
                continue;
            }
            foreach ($cells as $cell) {
                $gr = $cell['r'];
                $gc = $cell['c'];

                $rowA = $gr;
                $colA = $gc - $wordPos;
                $keyA = "A,{$rowA},{$colA}";
                if (!isset($seen[$keyA])) {
                    $seen[$keyA] = true;
                    $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowA, $colA, 'across');
                    if ($cr > 0) {
                        $dist = abs($rowA - $this->size / 2) + abs($colA + $len / 2 - $this->size / 2);
                        $score = $cr * $cr * 300 - $dist * 10;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $best = ['row' => $rowA, 'col' => $colA, 'direction' => 'across', 'crossings' => $cr, 'score' => $score];
                        }
                    }
                }

                $rowD = $gr - $wordPos;
                $colD = $gc;
                $keyD = "D,{$rowD},{$colD}";
                if (!isset($seen[$keyD])) {
                    $seen[$keyD] = true;
                    $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $rowD, $colD, 'down');
                    if ($cr > 0) {
                        $dist = abs($rowD + $len / 2 - $this->size / 2) + abs($colD - $this->size / 2);
                        $score = $cr * $cr * 300 - $dist * 10;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $best = ['row' => $rowD, 'col' => $colD, 'direction' => 'down', 'crossings' => $cr, 'score' => $score];
                        }
                    }
                }
            }
        }

        if (!$best) {
            foreach (['across', 'down'] as $dir) {
                for ($r = 0; $r < $this->size; $r++) {
                    for ($c = 0; $c < $this->size; $c++) {
                        $cr = $this->evaluatePlace($grid, $dirGrid, $letters, $len, $r, $c, $dir);
                        if ($cr <= 0) {
                            continue;
                        }
                        $dist = abs($r - $this->size / 2) + abs($c + ($dir === 'across' ? $len / 2 : 0) - $this->size / 2);
                        $score = $cr * $cr * 300 - $dist * 10;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $best = ['row' => $r, 'col' => $c, 'direction' => $dir, 'crossings' => $cr, 'score' => $score];
                        }
                    }
                }
            }
        }

        return $best;
    }

    /** Assign reading-order numbers (mutates words by reference). */
    public static function numberWords(array &$words): void
    {
        $positions = [];
        foreach ($words as $i => $w) {
            $key = "{$w['row']},{$w['col']}";
            $positions[$key][] = $i;
        }

        $keys = array_keys($positions);
        usort($keys, function ($a, $b) {
            [$ar, $ac] = array_map('intval', explode(',', $a));
            [$br, $bc] = array_map('intval', explode(',', $b));
            return $ar !== $br ? $ar - $br : $ac - $bc;
        });

        $num = 1;
        foreach ($keys as $key) {
            foreach ($positions[$key] as $idx) {
                $words[$idx]['number'] = $num;
            }
            $num++;
        }
    }
}
