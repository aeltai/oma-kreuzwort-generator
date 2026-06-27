<?php

use App\Http\Controllers\PuzzleExportController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::puzzle-app')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/export/pdf', [PuzzleExportController::class, 'pdf'])->name('puzzle.export.pdf');
    Route::get('/export/png', [PuzzleExportController::class, 'png'])->name('puzzle.export.png');
    Route::post('/export/zip', [PuzzleExportController::class, 'zip'])->name('puzzle.export.zip');
});
