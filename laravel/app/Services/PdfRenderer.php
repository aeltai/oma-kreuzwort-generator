<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;

class PdfRenderer
{
    private function browser(string $html): Browsershot
    {
        $shot = Browsershot::html($html);

        if ($path = config('puzzle.browsershot.chrome_path')) {
            $shot->setChromePath($path);
        }
        if ($node = config('puzzle.browsershot.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($npm = config('puzzle.browsershot.npm_binary')) {
            $shot->setNpmBinary($npm);
        }

        // Look for puppeteer installed inside this Laravel app
        $shot->setIncludePath(base_path('node_modules/.bin') . ':' . getenv('PATH'))
            ->noSandbox();

        return $shot;
    }

    /** Render the puzzle to a single-page A4 PDF binary string. */
    public function pdf(array $puzzleData, bool $solution = false, string $omaName = '', $puzzleNumber = null): string
    {
        $html = PuzzleHtml::render($puzzleData, $solution, $omaName, $puzzleNumber);

        // Measure natural content height, then scale down so it always fits one A4 sheet.
        $contentH = $this->measureHeight($html);
        // Printable height with 0 PDF margins ≈ 1122px (297mm @96dpi); the .page already
        // carries its own inner padding, so we use the full sheet and keep a small safety margin.
        $target = 1110.0;
        $scale = 1.0;
        if ($contentH > $target) {
            $scale = max(0.45, ($target / $contentH) * 0.99);
        }

        return $this->browser($html)
            ->format('A4')
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->scale($scale)
            ->waitUntilNetworkIdle()
            ->pdf();
    }

    /** Measure the rendered .page height in CSS pixels (best-effort). */
    private function measureHeight(string $html): float
    {
        try {
            $val = $this->browser($html)
                ->windowSize(794, 1123)
                ->waitUntilNetworkIdle()
                ->evaluate("(function(){var b=document.querySelector('.page');return Math.ceil(b?b.getBoundingClientRect().height:document.documentElement.scrollHeight);})()");
            $h = (float) $val;
            return $h > 0 ? $h : 1110.0;
        } catch (\Throwable $e) {
            report($e);
            return 1110.0;
        }
    }

    /** Render the puzzle to a full-page PNG binary string. */
    public function png(array $puzzleData, bool $solution = false, string $omaName = '', $puzzleNumber = null): string
    {
        $html = PuzzleHtml::render($puzzleData, $solution, $omaName, $puzzleNumber);

        return $this->browser($html)
            ->windowSize(794, 1123)
            ->deviceScaleFactor(2)
            ->fullPage()
            ->waitUntilNetworkIdle()
            ->setScreenshotType('png')
            ->screenshot();
    }
}
