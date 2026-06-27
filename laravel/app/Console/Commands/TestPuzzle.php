<?php

namespace App\Console\Commands;

use App\Services\CrosswordPlacer;
use App\Services\LoesungswortService;
use App\Services\PuzzleHtml;
use App\Services\WordNormalizer;
use Illuminate\Console\Command;

class TestPuzzle extends Command
{
    protected $signature = 'puzzle:test';
    protected $description = 'Smoke-test the crossword placement + HTML rendering (no API)';

    public function handle(): int
    {
        $placer = new CrosswordPlacer();
        $raw = ['GARTEN', 'BLUME', 'SONNE', 'ROSE', 'BAUM', 'WIESE', 'VOGEL', 'NEST', 'REGEN', 'WOLKE', 'BACH', 'BERG', 'SEE', 'WALD', 'MOOS', 'HASE'];
        $wordObjects = array_map(fn ($w) => ['word' => WordNormalizer::normalise($w, 'de'), 'clue' => 'Hinweis zu ' . $w], $raw);

        $result = $placer->place($wordObjects);
        if (!$result) {
            $this->error('PLACE FAILED');
            return 1;
        }
        CrosswordPlacer::numberWords($result['words']);
        $this->info("grid: {$result['gridWidth']}x{$result['gridHeight']}, placed: " . count($result['words']));

        $meta = LoesungswortService::buildMeta($result['words'], 'ROSEN', 'Schön und rot', null, 'de');
        $this->info("loesungswort: {$meta['loesungswort']} cells:" . count($meta['loesungswortCells']) . ' scatter:' . ($meta['loesungswortScatter'] ? 'y' : 'n'));

        $data = array_merge(['title' => 'TESTRÄTSEL', 'language' => 'de'], $result, $meta);
        $html = PuzzleHtml::render($data, false, 'Maria', 3);
        $this->info('html length: ' . strlen($html));
        $this->info((str_contains($html, 'TESTRÄTSEL') && str_contains($html, 'loeswort-slot')) ? 'HTML OK' : 'HTML MISSING PARTS');

        // Multibyte test (Russian)
        $ruRaw = ['КОШКА', 'МАМА', 'ДОМ', 'ОКНО', 'СТОЛ', 'РЕКА', 'ЛЕТО', 'СНЕГ', 'ЛУНА', 'РОЗА'];
        $ruWords = array_map(fn ($w) => ['word' => WordNormalizer::normalise($w, 'ru'), 'clue' => 'подсказка'], $ruRaw);
        $ruResult = (new CrosswordPlacer())->place($ruWords);
        $this->info('RU placed: ' . ($ruResult ? count($ruResult['words']) : 'FAILED'));

        return 0;
    }
}
