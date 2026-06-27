<?php

namespace App\Console\Commands;

use App\Services\PdfRenderer;
use App\Services\PuzzleGenerator;
use Illuminate\Console\Command;

class TestPdf extends Command
{
    protected $signature = 'puzzle:pdf {--lang=de} {--words=32}';
    protected $description = 'Generate a puzzle and render a single-page PDF to /tmp';

    public function handle(): int
    {
        $settings = [
            'name' => 'Klara', 'language' => $this->option('lang'), 'difficulty' => 'leicht',
            'wordCount' => (int) $this->option('words'), 'healthProfile' => 'demenz',
            'customContext' => 'mag Garten, Musik, Familie', 'familyStory' => '',
            'useFamilyStory' => false, 'topics' => ['Natur & Garten', 'Familie & Alltag'], 'wordHistory' => [],
        ];

        $this->info('Generating puzzle (' . $this->option('words') . ' words) …');
        $data = app(PuzzleGenerator::class)->generate($settings);
        $this->info('Rendering PDF …');
        $pdf = app(PdfRenderer::class)->pdf($data, false, 'Klara', 1);

        $path = '/tmp/raetsel-test.pdf';
        file_put_contents($path, $pdf);
        $this->info('Wrote ' . strlen($pdf) . ' bytes to ' . $path);
        return 0;
    }
}
