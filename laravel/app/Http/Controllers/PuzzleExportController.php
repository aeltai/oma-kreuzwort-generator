<?php

namespace App\Http\Controllers;

use App\Services\PdfRenderer;
use App\Services\PuzzleGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PuzzleExportController extends Controller
{
    private function exportData(): ?array
    {
        $data = session('puzzle_export');
        return is_array($data) && !empty($data['puzzle']) ? $data : null;
    }

    private function download(string $bin, string $file, string $contentType): Response
    {
        return response($bin, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Content-Length' => (string) strlen($bin),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function pdf(Request $request): Response
    {
        $export = $this->exportData();
        abort_unless($export, 404, 'Kein Rätsel zum Exportieren.');

        $solution = $request->boolean('solution');
        $bin = app(PdfRenderer::class)->pdf(
            $export['puzzle'],
            $solution,
            $export['name'] ?? '',
            $export['number'] ?? null
        );

        $file = $solution ? 'kreuzwortratsel-loesung.pdf' : 'kreuzwortratsel.pdf';

        return $this->download($bin, $file, 'application/pdf');
    }

    public function png(Request $request): Response
    {
        $export = $this->exportData();
        abort_unless($export, 404, 'Kein Rätsel zum Exportieren.');

        $solution = $request->boolean('solution');
        $bin = app(PdfRenderer::class)->png(
            $export['puzzle'],
            $solution,
            $export['name'] ?? '',
            $export['number'] ?? null
        );

        $file = $solution ? 'kreuzwortratsel-loesung.png' : 'kreuzwortratsel.png';

        return $this->download($bin, $file, 'image/png');
    }

    public function zip(Request $request): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        @set_time_limit(0);

        $settings = session('puzzle_export_settings');
        abort_unless(is_array($settings), 404, 'Export-Einstellungen fehlen.');

        $count = max(1, min(15, (int) $request->input('zipCount', 5)));
        $generator = app(PuzzleGenerator::class);
        $renderer = app(PdfRenderer::class);
        $name = $settings['name'] ?? '';

        $tmp = tempnam(sys_get_temp_dir(), 'raetsel') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'ZIP konnte nicht erstellt werden.');
        }

        try {
            for ($i = 1; $i <= $count; $i++) {
                $data = $generator->generate($settings);
                if (Auth::check()) {
                    Auth::user()->puzzles()->create([
                        'user_id' => Auth::id(),
                        'patient_id' => $settings['patient_id'] ?? null,
                        'title' => $data['title'] ?? null,
                        'language' => $data['language'] ?? 'de',
                        'data_json' => $data,
                    ]);
                }
                $zip->addFromString("raetsel-{$i}.pdf", $renderer->pdf($data, false, $name, $i));
                $zip->addFromString("raetsel-{$i}-loesung.pdf", $renderer->pdf($data, true, $name, $i));
            }
            $zip->close();
        } catch (\Throwable $e) {
            report($e);
            @$zip->close();
            @unlink($tmp);
            return back()->with('error', $e->getMessage());
        }

        $bin = file_get_contents($tmp);
        @unlink($tmp);

        return $this->download($bin, 'raetsel-stapel.zip', 'application/zip');
    }
}
