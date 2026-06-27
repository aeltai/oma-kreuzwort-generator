<?php

namespace App\Services;

use RuntimeException;

class PuzzleGenerator
{
    public function __construct(
        private AnthropicClient $client,
        private LoesungswortPlanner $planner,
        private CrosswordPlacer $placer,
    ) {}

    /**
     * Full generation pipeline. Returns the puzzle data array.
     * Ported from the Node `/api/generate` handler.
     */
    public function generate(array $settings): array
    {
        $lang = Lang::resolve($settings);
        $langCfg = Lang::for($lang);
        $puzzleType = 'schweden';
        $wordCount = PromptBuilder::wordCount($settings);

        $loesPlan = $this->planner->fetchPlan($settings, $wordCount);
        $plannedLw = $loesPlan['loesungswort'] ?? '';
        $plannedHint = $loesPlan['loesungswortHinweis'] ?? '';

        $userPrompt = PromptBuilder::userPrompt(array_merge($settings, [
            'puzzleType' => $puzzleType,
            'wordCount' => $wordCount,
            'plannedLoesungswort' => $plannedLw,
        ]));

        $wordObjects = null;
        $title = $langCfg['solution_word_label'] ?? 'Kreuzworträtsel';
        $maxTokens = min(3200, 1200 + $wordCount * 38);
        $minAcceptable = (int) floor($wordCount * 0.75);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $raw = $this->client->message(PromptBuilder::systemPrompt($lang), $userPrompt, $maxTokens);
                $data = AnthropicClient::parseJson($raw);
                if (!is_array($data)) {
                    continue;
                }
                $title = $data['title'] ?? $title;

                $candidate = [];
                foreach (($data['words'] ?? []) as $w) {
                    $norm = WordNormalizer::normalise((string) ($w['word'] ?? ''), $lang);
                    $clue = (string) ($w['clue'] ?? '');
                    $len = WordNormalizer::len($norm);
                    if ($len >= 4 && $len <= 10 && $clue !== '') {
                        $candidate[] = ['word' => $norm, 'clue' => $clue];
                    }
                }
                $wordObjects = $candidate;

                if (count($wordObjects) >= $minAcceptable) {
                    if ($plannedLw === '' || LoesungswortService::lettersCoveredByWords(
                        array_map(fn ($w) => $w + ['letters' => WordNormalizer::chars($w['word'])], $wordObjects),
                        $plannedLw,
                        $lang
                    )) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (!$wordObjects || count($wordObjects) < 6) {
            throw new RuntimeException('Zu wenige Wörter von Claude erhalten. Bitte erneut versuchen.');
        }

        // attach letters for coverage check
        $withLetters = array_map(fn ($w) => $w + ['letters' => WordNormalizer::chars($w['word'])], $wordObjects);
        $useLoesPlan = $plannedLw !== '' && LoesungswortService::lettersCoveredByWords($withLetters, $plannedLw, $lang);

        // de-dupe words
        $seen = [];
        $wordObjects = array_values(array_filter($wordObjects, function ($w) use (&$seen) {
            if (isset($seen[$w['word']])) return false;
            $seen[$w['word']] = true;
            return true;
        }));

        $minPlaced = (int) floor($wordCount * 0.85);
        $result = null;
        $bestPlaced = 0;
        for ($placeAttempt = 0; $placeAttempt < 4; $placeAttempt++) {
            $candidate = $this->placer->placeForTarget($wordObjects, $wordCount);
            if (!$candidate) {
                continue;
            }
            $placed = count($candidate['words']);
            if ($placed > $bestPlaced) {
                $bestPlaced = $placed;
                $result = $candidate;
            }
            if ($placed >= $minPlaced) {
                break;
            }
        }
        if (!$result) {
            throw new RuntimeException('Konnte keine ausreichende Gitteranordnung erzeugen. Bitte erneut versuchen.');
        }
        if (count($result['words']) < $minPlaced) {
            throw new RuntimeException(
                'Nur '.count($result['words']).' von '.$wordCount.' Wörtern konnten ins Gitter gelegt werden. Bitte erneut generieren.'
            );
        }

        CrosswordPlacer::numberWords($result['words']);

        if ($useLoesPlan) {
            $quelle = LoesungswortService::pickScattered($result['words'], $plannedLw, $lang);
            if (!$quelle) {
                $qOnly = $this->planner->fetchQuelleOnly($result['words'], $plannedLw, $title, array_merge($settings, ['puzzleType' => $puzzleType]));
                $quelle = $qOnly['quelle'] ?? null;
            }
            $lwMeta = LoesungswortService::buildMeta($result['words'], $plannedLw, $plannedHint, $quelle, $lang);
        } else {
            $lwAi = $this->planner->fetchAfterPlacement($result['words'], $title, array_merge($settings, ['puzzleType' => $puzzleType]));
            $lwMeta = LoesungswortService::buildMeta(
                $result['words'],
                $lwAi['loesungswort'] ?? '',
                $lwAi['hint'] ?? '',
                $lwAi['quelle'] ?? null,
                $lang
            );
        }

        // Clean words for output (drop internal letters/len keys)
        $cleanWords = array_map(fn ($w) => [
            'word' => $w['word'],
            'clue' => $w['clue'] ?? '',
            'row' => $w['row'],
            'col' => $w['col'],
            'direction' => $w['direction'],
            'number' => $w['number'] ?? null,
        ], $result['words']);

        return array_merge([
            'title' => $title,
            'puzzleType' => $puzzleType,
            'language' => $lang,
            'gridWidth' => $result['gridWidth'],
            'gridHeight' => $result['gridHeight'],
            'words' => $cleanWords,
        ], $lwMeta);
    }
}
