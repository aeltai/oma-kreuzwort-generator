<?php

namespace App\Services;

class LoesungswortService
{
    /** Do the placed words collectively contain every letter of $lw? */
    public static function lettersCoveredByWords(array $wordObjects, string $lw, string $lang = 'de'): bool
    {
        $lwNorm = WordNormalizer::normalise($lw, $lang);
        if ($lwNorm === '') return false;
        $need = [];
        foreach (WordNormalizer::chars($lwNorm) as $ch) {
            $need[$ch] = ($need[$ch] ?? 0) + 1;
        }
        $have = [];
        foreach ($wordObjects as $w) {
            $letters = $w['letters'] ?? WordNormalizer::chars($w['word']);
            foreach ($letters as $ch) {
                $have[$ch] = ($have[$ch] ?? 0) + 1;
            }
        }
        foreach ($need as $ch => $n) {
            if (($have[$ch] ?? 0) < $n) return false;
        }
        return true;
    }

    /** All points form one contiguous straight horizontal/vertical line. */
    public static function isSingleContiguousStraightLine(array $points): bool
    {
        if (count($points) <= 1) return false;
        $rs = array_map(fn ($p) => $p['r'], $points);
        $cs = array_map(fn ($p) => $p['c'], $points);
        if (count(array_unique($rs)) === 1) {
            sort($cs);
            $min = $cs[0];
            foreach ($cs as $i => $v) {
                if ($v !== $min + $i) return false;
            }
            return true;
        }
        if (count(array_unique($cs)) === 1) {
            sort($rs);
            $min = $rs[0];
            foreach ($rs as $i => $v) {
                if ($v !== $min + $i) return false;
            }
            return true;
        }
        return false;
    }

    public static function manhattanPairSum(array $points): int
    {
        $s = 0;
        $n = count($points);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $s += abs($points[$i]['r'] - $points[$j]['r']) + abs($points[$i]['c'] - $points[$j]['c']);
            }
        }
        return $s;
    }

    /**
     * Choose one grid cell per solution-word letter, preferring spread.
     * @return array<array{word:string,buchstabe_index:int}>|null
     */
    public static function pickScattered(array $placedWords, string $lwRaw, string $lang = 'de'): ?array
    {
        $lw = WordNormalizer::normalise($lwRaw, $lang);
        $lwLetters = WordNormalizer::chars($lw);
        $lwLen = count($lwLetters);
        if ($lwLen === 0 || count($placedWords) === 0) return null;

        $byK = [];
        for ($k = 0; $k < $lwLen; $k++) {
            $ch = $lwLetters[$k];
            $opts = [];
            foreach ($placedWords as $ei => $entry) {
                $letters = $entry['letters'] ?? WordNormalizer::chars($entry['word']);
                foreach ($letters as $idx => $lch) {
                    if ($lch === $ch) {
                        $r = $entry['direction'] === 'down' ? $entry['row'] + $idx : $entry['row'];
                        $c = $entry['direction'] === 'across' ? $entry['col'] + $idx : $entry['col'];
                        $opts[] = ['entryIndex' => $ei, 'idx' => $idx, 'r' => $r, 'c' => $c];
                    }
                }
            }
            if (count($opts) === 0) return null;
            $byK[$k] = $opts;
        }

        $REST = 500;
        $REST_RELAX = 300;
        $best = null;
        $bestScore = -1e9;

        $tryAssign = function (bool $requireSpread) use (&$best, &$bestScore, $byK, $lwLen, $placedWords) {
            $order = range(0, $lwLen - 1);
            shuffle($order);
            $picked = array_fill(0, $lwLen, null);
            $used = [];
            foreach ($order as $k) {
                $opts = $byK[$k];
                shuffle($opts);
                $choice = null;
                foreach ($opts as $o) {
                    $key = "{$o['r']},{$o['c']}";
                    if (!isset($used[$key])) {
                        $choice = $o;
                        break;
                    }
                }
                if (!$choice) {
                    return;
                }
                $picked[$k] = $choice;
                $used["{$choice['r']},{$choice['c']}"] = true;
            }
            $pts = array_map(fn ($p) => ['r' => $p['r'], 'c' => $p['c']], $picked);
            if ($requireSpread && self::isSingleContiguousStraightLine($pts)) return;
            $rowU = count(array_unique(array_map(fn ($p) => $p['r'], $pts)));
            $colU = count(array_unique(array_map(fn ($p) => $p['c'], $pts)));
            $wordU = count(array_unique(array_map(fn ($p) => $placedWords[$p['entryIndex']]['word'], $picked)));
            $dist = self::manhattanPairSum($pts);
            $score = $dist + 10 * $rowU * $colU + 4 * $wordU;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $picked;
            }
        };

        for ($i = 0; $i < $REST; $i++) $tryAssign(true);
        if (!$best) {
            for ($i = 0; $i < $REST_RELAX; $i++) $tryAssign(false);
        }
        if (!$best) return null;

        return array_map(fn ($p) => [
            'word' => $placedWords[$p['entryIndex']]['word'],
            'buchstabe_index' => $p['idx'],
        ], $best);
    }

    /** Resolve a 0-based (or sloppy 1-based) char index that matches expected char. */
    public static function resolveCharIndexInWord(array $entry, $idxRaw, string $expectedChar): int
    {
        $idx = is_numeric($idxRaw) ? (int) $idxRaw : null;
        if ($idx === null) return -1;
        $letters = $entry['letters'] ?? WordNormalizer::chars($entry['word']);
        $len = count($letters);
        if ($idx >= 0 && $idx < $len && $letters[$idx] === $expectedChar) return $idx;
        if ($idx >= 1 && $idx <= $len && $letters[$idx - 1] === $expectedChar) return $idx - 1;
        return -1;
    }

    public static function normaliseLetterSources($raw, string $lang = 'de'): ?array
    {
        if (!is_array($raw)) return null;
        return array_map(fn ($item) => [
            'word' => WordNormalizer::normalise((string) ($item['word'] ?? $item['wort'] ?? ''), $lang),
            'buchstabe_index' => $item['buchstabe_index'] ?? $item['index'] ?? $item['i'] ?? $item['char_index'] ?? null,
        ], $raw);
    }

    private static function findByWord(array $placedWords, string $word): ?array
    {
        foreach ($placedWords as $w) {
            if ($w['word'] === $word) return $w;
        }
        return null;
    }

    /**
     * Build the solution-word metadata (letters from scattered grid cells, else fallback to a full word).
     */
    public static function buildMeta(array $placedWords, string $requestedWord, string $requestedHint, $letterSourcesRaw, string $lang = 'de'): array
    {
        $langCfg = Lang::for($lang);
        $lw = WordNormalizer::normalise($requestedWord, $lang);
        $lwLetters = WordNormalizer::chars($lw);
        $lwLen = count($lwLetters);
        $hint = trim($requestedHint);
        $letterSources = self::normaliseLetterSources($letterSourcesRaw, $lang);
        $scatterHintDefault = $langCfg['scatter_hint'];

        if ($letterSources && count($letterSources) === $lwLen && $lwLen > 0 && count($placedWords)) {
            $cells = [];
            $ok = true;
            for ($k = 0; $k < $lwLen; $k++) {
                $src = $letterSources[$k];
                $entry = self::findByWord($placedWords, $src['word']);
                if (!$entry) { $ok = false; break; }
                $idx = self::resolveCharIndexInWord($entry, $src['buchstabe_index'], $lwLetters[$k]);
                if ($idx < 0) { $ok = false; break; }
                $r = $entry['direction'] === 'down' ? $entry['row'] + $idx : $entry['row'];
                $c = $entry['direction'] === 'across' ? $entry['col'] + $idx : $entry['col'];
                $cells[] = ['row' => $r, 'col' => $c, 'n' => $k + 1];
            }
            if ($ok) {
                $seen = [];
                foreach ($cells as $cell) {
                    $key = "{$cell['row']},{$cell['col']}";
                    if (isset($seen[$key])) { $ok = false; break; }
                    $seen[$key] = true;
                }
            }
            if ($ok && $lwLen >= 4 && self::isSingleContiguousStraightLine(array_map(fn ($c) => ['r' => $c['row'], 'c' => $c['col']], $cells))) {
                $scattered = self::pickScattered($placedWords, $lw, $lang);
                if ($scattered) {
                    $cells = [];
                    for ($k = 0; $k < $lwLen; $k++) {
                        $src = $scattered[$k];
                        $entry = self::findByWord($placedWords, $src['word']);
                        $idx = self::resolveCharIndexInWord($entry, $src['buchstabe_index'], $lwLetters[$k]);
                        $r = $entry['direction'] === 'down' ? $entry['row'] + $idx : $entry['row'];
                        $c = $entry['direction'] === 'across' ? $entry['col'] + $idx : $entry['col'];
                        $cells[] = ['row' => $r, 'col' => $c, 'n' => $k + 1];
                    }
                    $seen2 = [];
                    foreach ($cells as $cell) {
                        $key = "{$cell['row']},{$cell['col']}";
                        if (isset($seen2[$key])) { $ok = false; break; }
                        $seen2[$key] = true;
                    }
                }
            }
            if ($ok) {
                if ($hint === '') $hint = $scatterHintDefault;
                return [
                    'loesungswort' => $lw,
                    'loesungswortHinweis' => $hint,
                    'loesungswortCells' => array_map(fn ($c) => ['row' => $c['row'], 'col' => $c['col'], 'n' => $c['n']], $cells),
                    'loesungswortNumber' => null,
                    'loesungswortDirection' => null,
                    'loesungswortScatter' => true,
                ];
            }
        }

        $entry = self::findByWord($placedWords, $lw);
        if (!$entry && count($placedWords)) {
            $sorted = $placedWords;
            usort($sorted, fn ($a, $b) => ($b['len'] ?? mb_strlen($b['word'])) <=> ($a['len'] ?? mb_strlen($a['word'])));
            $entry = $sorted[0];
        }
        if (!$entry) {
            return [
                'loesungswort' => '',
                'loesungswortHinweis' => '',
                'loesungswortCells' => [],
                'loesungswortNumber' => null,
                'loesungswortDirection' => null,
                'loesungswortScatter' => false,
            ];
        }

        if ($hint === '') $hint = $scatterHintDefault;

        $entryLetters = $entry['letters'] ?? WordNormalizer::chars($entry['word']);
        $loesungswortCells = [];
        foreach ($entryLetters as $i => $_) {
            $r = $entry['direction'] === 'down' ? $entry['row'] + $i : $entry['row'];
            $c = $entry['direction'] === 'across' ? $entry['col'] + $i : $entry['col'];
            $loesungswortCells[] = ['row' => $r, 'col' => $c, 'n' => $i + 1];
        }

        return [
            'loesungswort' => $entry['word'],
            'loesungswortHinweis' => $hint,
            'loesungswortCells' => $loesungswortCells,
            'loesungswortNumber' => null,
            'loesungswortDirection' => null,
            'loesungswortScatter' => true,
        ];
    }
}
