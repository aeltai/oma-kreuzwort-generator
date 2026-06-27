<?php

namespace App\Services;

class PuzzleHtml
{
    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render the standalone A4 print HTML for a puzzle.
     * Ported from the Node `generatePuzzleHTML`.
     */
    public static function render(array $puzzleData, bool $showSolution = false, string $omaName = '', $issueNo = null): string
    {
        $title = $puzzleData['title'] ?? '';
        $gridWidth = (int) $puzzleData['gridWidth'];
        $gridHeight = (int) $puzzleData['gridHeight'];
        $words = $puzzleData['words'] ?? [];
        $lang = $puzzleData['language'] ?? 'de';
        $langCfg = Lang::for($lang);
        $nWords = count($words);
        $loesW = $puzzleData['loesungswort'] ?? '';
        $loesWLetters = WordNormalizer::chars($loesW);
        $loesWLen = count($loesWLetters);
        $loesH = $puzzleData['loesungswortHinweis'] ?? '';
        $loesCells = $puzzleData['loesungswortCells'] ?? [];

        $loesOrderMap = [];
        foreach ($loesCells as $i => $cell) {
            $ord = $cell['n'] ?? $cell['order'] ?? ($i + 1);
            $loesOrderMap["{$cell['row']},{$cell['col']}"] = $ord;
        }

        $puzzleType = 'schweden';

        $safeTitle = self::esc($title !== '' ? $title : ($langCfg['solution_word_label'] ?? 'Kreuzworträtsel'));
        $safeName = self::esc(trim($omaName));
        $dedicLine = $safeName !== '' ? "Für {$safeName}" : 'Mit lieben Grüßen';
        $footerPlay = $showSolution
            ? '✦ Lösungsblatt ✦'
            : ($safeName !== '' ? "Viel Spaß beim Rätseln, {$safeName}! ♥" : 'Viel Spaß beim Rätseln! ♥');

        // Build letter grid
        $grid = array_fill(0, $gridHeight, array_fill(0, $gridWidth, null));
        foreach ($words as $w) {
            $letters = WordNormalizer::chars($w['word']);
            foreach ($letters as $i => $ch) {
                $r = $w['direction'] === 'down' ? $w['row'] + $i : $w['row'];
                $c = $w['direction'] === 'across' ? $w['col'] + $i : $w['col'];
                if ($r >= 0 && $r < $gridHeight && $c >= 0 && $c < $gridWidth) {
                    $grid[$r][$c] = $ch;
                }
            }
        }

        $numMap = [];
        foreach ($words as $w) {
            if (isset($w['number'])) {
                $numMap["{$w['row']},{$w['col']}"] = $w['number'];
            }
        }

        $usableWpx = 680;
        $COL_PX = max(28, min(46, (int) floor($usableWpx / max(1, $gridWidth))));
        $ROW_PX = $COL_PX;

        $clueFontPx = $nWords > 22 ? 11.5 : ($nWords > 16 ? 13 : 14.5);
        $clueGapPx = $nWords > 22 ? 4 : 6;
        $clueHeadPx = $nWords > 22 ? 10 : 11;

        // Grid cells
        $gridCells = '';
        for ($r = 0; $r < $gridHeight; $r++) {
            for ($c = 0; $c < $gridWidth; $c++) {
                $letter = $grid[$r][$c];
                if ($letter === null) {
                    $gridCells .= '<div class="cell black"></div>';
                    continue;
                }
                $key = "{$r},{$c}";
                $num = $numMap[$key] ?? null;
                $numHtml = ($num) ? "<span class=\"num\">{$num}</span>" : '';
                $loesOrd = $loesOrderMap[$key] ?? null;
                $loesOrdHtml = $loesOrd !== null ? "<span class=\"loes-zahl\">{$loesOrd}</span>" : '';
                $letterHtml = $showSolution
                    ? '<span class="letter sol">' . self::esc($letter) . '</span>'
                    : '<span class="letter"></span>';
                $gridCells .= "<div class=\"cell\">{$numHtml}{$letterHtml}{$loesOrdHtml}</div>";
            }
        }

        // Clues
        $across = array_filter($words, fn ($w) => $w['direction'] === 'across');
        $down = array_filter($words, fn ($w) => $w['direction'] === 'down');
        usort($across, fn ($a, $b) => $a['number'] <=> $b['number']);
        usort($down, fn ($a, $b) => $a['number'] <=> $b['number']);

        $modeNote = '<p class="mode-note">' . self::esc($langCfg['mode_note']) . '</p>';

        $clueList = function ($arr) {
            $out = '';
            foreach ($arr as $w) {
                $out .= '<div class="clue"><span class="cn">' . $w['number'] . '</span><span class="ct">' . self::esc((string) $w['clue']) . '</span></div>';
            }
            return $out;
        };

        $loeswortBundleHtml = '';
        if ($loesW !== '') {
            $loesUniversalHint = $langCfg['universal_hint'];
            $slotPx = max(24, min(36, $COL_PX + 4));
            $slotCells = '';
            for ($i = 0; $i < $loesWLen; $i++) {
                $inner = $showSolution ? '<span class="loeswort-slot-letter">' . self::esc($loesWLetters[$i]) . '</span>' : '';
                $slotCells .= "<div class=\"loeswort-slot\">{$inner}</div>";
            }
            $reveal = $showSolution ? '<p class="loeswort-answer"><strong>Lösung:</strong> ' . self::esc($loesW) . '</p>' : '';
            $hintText = $loesH !== '' ? self::esc($loesH) : self::esc($loesUniversalHint);
            $loeswortBundleHtml = "\n    <div class=\"loeswort-unter-gitte\">\n      <div class=\"loeswort-leiste-head\">" . self::esc($langCfg['solution_word_label']) . "</div>\n      <div class=\"loeswort-slots\" style=\"--loes-slot:{$slotPx}px\">{$slotCells}</div>\n      <p class=\"loeswort-hint\">{$hintText}</p>\n      {$reveal}\n    </div>";
        }

        $gridW = $gridWidth * $COL_PX;
        $htmlLang = in_array($lang, ['ar', 'ru', 'el', 'tr', 'es', 'it'], true) ? $lang : 'de';
        $htmlDir = ($langCfg['dir'] === 'rtl') ? ' dir="rtl"' : '';

        $loesZahlSize = max(12, (int) round($COL_PX * 0.38));
        $loesZahlFont = max(7, (int) round($COL_PX * 0.24));
        $numFont = max(7, (int) round($COL_PX * 0.27));
        $letterFont = max(15, $COL_PX - 8);
        $clueGapPlus = $clueGapPx + 2;
        $clueHeadPlus = $clueHeadPx + 1;

        $issueHtml = ($issueNo !== null && $issueNo !== '')
            ? '<span class="issue-no">Nr.&nbsp;' . self::esc((string) $issueNo) . '</span>'
            : '';

        $acrossLabel = self::esc($langCfg['across_label']);
        $downLabel = self::esc($langCfg['down_label']);
        $acrossClues = $clueList($across);
        $downClues = $clueList($down);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$htmlLang}"{$htmlDir}>
<head>
<meta charset="UTF-8">
<title>Kreuzworträtsel – {$safeTitle}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Arial', Helvetica, sans-serif; background: white; padding: 0; color: #111; }
  .page { width: 210mm; max-width: 210mm; min-height: auto; margin: 0 auto; padding: 8mm 10mm 10mm; box-sizing: border-box; }
  .top-rule { border-top: 1px solid #222; margin-bottom: 8px; }
  .title-bar { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 14px; padding-bottom: 8px; margin-bottom: 12px; border-bottom: 1px solid #333; box-shadow: 0 1px 0 #333; }
  .title-bar h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 21px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #111; flex: 1 1 auto; min-width: 0; }
  .issue-no { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #888; letter-spacing: 1px; flex-shrink: 0; }
  .title-dedication { margin-left: auto; font-family: Georgia, serif; font-size: 13px; font-style: italic; color: #444; flex-shrink: 0; }
  .layout { display: flex; flex-direction: column; align-items: stretch; width: 100%; gap: 12px; }
  .grid-col { flex: 0 0 auto; width: 100%; display: flex; justify-content: center; align-items: flex-start; }
  .grid-frame { background: #111; padding: 4px; display: inline-block; line-height: 0; }
  .clues-below { flex: 0 0 auto; width: 100%; display: grid; grid-template-columns: 1fr 1fr; gap: {$clueGapPlus}px 20px; align-items: start; box-sizing: border-box; }
  .grid { display: grid; grid-template-columns: repeat({$gridWidth}, {$COL_PX}px); grid-template-rows: repeat({$gridHeight}, {$ROW_PX}px); border: 2px solid #111; border-right: none; border-bottom: none; width: {$gridW}px; max-width: 100%; flex-shrink: 0; }
  .cell { width: {$COL_PX}px; height: {$ROW_PX}px; border-right: 1.5px solid #555; border-bottom: 1.5px solid #555; position: relative; background: white; display: flex; align-items: center; justify-content: center; }
  .cell.black { background: #111; border-color: #111; }
  .loes-zahl { position: absolute; right: 1px; bottom: 1px; min-width: {$loesZahlSize}px; height: {$loesZahlSize}px; padding: 0 2px; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; font-size: {$loesZahlFont}px; font-weight: 900; line-height: 1; letter-spacing: -0.02em; color: #fff; background: #0f766e; border-radius: 2px; box-shadow: 0 0 0 0.5px rgba(15, 118, 110, 0.5); -webkit-print-color-adjust: exact; print-color-adjust: exact; pointer-events: none; z-index: 4; }
  .mode-note { font-size: 11px; color: #444; margin: 0 0 10px; line-height: 1.45; grid-column: 1 / -1; }
  .num { position: absolute; top: 1px; left: 2px; font-size: {$numFont}px; font-weight: 700; line-height: 1; color: #222; }
  .letter { font-size: {$letterFont}px; font-weight: 900; color: #111; line-height: 1; }
  .letter.sol { color: #c8102e; }
  .clues-section { margin-bottom: 0; }
  .clues-heading { font-size: {$clueHeadPx}px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; color: #c8102e; border-bottom: 2px solid #c8102e; padding-bottom: 2px; margin-bottom: 6px; }
  .clue { display: flex; gap: 6px; margin-bottom: {$clueGapPx}px; font-size: {$clueFontPx}px; line-height: 1.4; break-inside: avoid; }
  .cn { font-weight: 900; min-width: 18px; text-align: right; color: #c8102e; flex-shrink: 0; }
  .ct { flex: 1; min-width: 0; }
  .loeswort-unter-gitte { flex: 0 0 auto; width: 100%; max-width: {$gridW}px; margin: 0 auto 12px; padding: 10px 12px; background: #f7f3ec; border: 1px solid #cfc7bb; border-radius: 4px; box-sizing: border-box; break-inside: avoid; }
  .loeswort-leiste-head { font-size: {$clueHeadPlus}px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; color: #c8102e; border-bottom: 2px solid #c8102e; padding-bottom: 3px; margin-bottom: 8px; }
  .loeswort-slots { display: flex; flex-wrap: wrap; gap: 4px 6px; margin-bottom: 8px; align-items: center; justify-content: center; }
  .loeswort-slot { width: var(--loes-slot, 20px); height: calc(var(--loes-slot, 20px) * 1.15); border: 2px solid #333; border-radius: 2px; background: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .loeswort-slot-letter { font-size: calc(var(--loes-slot, 20px) * 0.75); font-weight: 900; color: #c8102e; }
  .loeswort-hint { font-size: {$clueFontPx}px; line-height: 1.35; color: #333; margin: 0; }
  .loeswort-answer { font-size: {$clueFontPx}px; margin: 6px 0 0; color: #c8102e; font-weight: 800; }
  .footer { margin-top: 12px; border-top: 2px solid #c8102e; padding-top: 6px; text-align: center; font-size: 10px; font-style: italic; color: #666; }
</style>
</head>
<body>
<div class="page">
  <div class="top-rule"></div>
  <div class="title-bar">
    <h2>{$safeTitle}</h2>
    {$issueHtml}
    <span class="title-dedication">{$dedicLine}</span>
  </div>
  <div class="layout">
    <div class="grid-col">
      <div class="grid-frame"><div class="grid">{$gridCells}</div></div>
    </div>
    {$loeswortBundleHtml}
    <div class="clues-below">
      {$modeNote}
      <div class="clues-section">
        <div class="clues-heading">{$acrossLabel}</div>
        {$acrossClues}
      </div>
      <div class="clues-section">
        <div class="clues-heading">{$downLabel}</div>
        {$downClues}
      </div>
    </div>
  </div>
  <div class="footer">{$footerPlay}</div>
</div>
</body>
</html>
HTML
        ;
    }
}
