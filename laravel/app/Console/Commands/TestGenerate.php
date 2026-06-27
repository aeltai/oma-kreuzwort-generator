<?php

namespace App\Console\Commands;

use App\Services\PuzzleGenerator;
use Illuminate\Console\Command;

class TestGenerate extends Command
{
    protected $signature = 'puzzle:gen {--lang=de}';
    protected $description = 'End-to-end test: call Anthropic + place a real puzzle';

    public function handle(): int
    {
        $settings = [
            'name' => 'Maria',
            'language' => $this->option('lang'),
            'difficulty' => 'leicht',
            'wordCount' => 16,
            'healthProfile' => 'demenz',
            'customContext' => 'mag Rosen, klassische Musik und ihren Garten',
            'familyStory' => '',
            'useFamilyStory' => false,
            'topics' => ['Natur & Garten', 'Blumen & Pflanzen'],
            'wordHistory' => [],
        ];

        $this->info('Calling Anthropic … (model: ' . config('puzzle.anthropic.model') . ')');
        try {
            $data = app(PuzzleGenerator::class)->generate($settings);
        } catch (\Throwable $e) {
            $this->error('FAILED: ' . $e->getMessage());
            return 1;
        }

        $this->info('Title: ' . ($data['title'] ?? '—'));
        $this->info('Grid: ' . $data['gridWidth'] . 'x' . $data['gridHeight'] . ' · words: ' . count($data['words']));
        $this->info('Lösungswort: ' . ($data['loesungswort'] ?? '—') . ' (' . count($data['loesungswortCells'] ?? []) . ' cells)');
        $this->line('Sample clues:');
        foreach (array_slice($data['words'], 0, 5) as $w) {
            $this->line("  {$w['number']}. {$w['word']} — {$w['clue']}");
        }
        return 0;
    }
}
