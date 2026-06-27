<?php

use App\Models\Patient;
use App\Models\Puzzle;
use App\Services\PuzzleGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    // ---- Settings ----
    public string $name = '';
    public string $language = 'de';        // UI language (chrome)
    public string $puzzleLanguage = 'de';  // language of the generated puzzle content
    public string $difficulty = 'leicht';
    public int $wordCount = 24;
    public string $customContext = '';
    public string $familyStory = '';
    public bool $useFamilyStory = true;
    public array $selectedTopics = [];

    // ---- Mode ----
    public string $mode = 'single';
    public int $zipCount = 5;

    // ---- UI state ----
    public string $activePanel = 'create';
    public ?array $puzzle = null;
    public int $puzzleNumber = 0;
    public string $status = 'idle';
    public string $errorMessage = '';

    // ---- Auth ----
    public string $authMode = 'login';
    public string $authName = '';
    public string $authEmail = '';
    public string $authPassword = '';
    public bool $authIsPflegeheim = false;

    // ---- Patients ----
    public array $patients = [];
    public ?int $selectedPatientId = null;
    public ?int $editingPatientId = null;
    public string $newPatientName = '';
    public string $patientSaveMessage = '';

    // ---- History ----
    public array $history = [];

    public const TOPIC_IDS = [
        'musik', 'bayern', 'kochen', 'natur', 'blumen',
        'religion', 'familie', 'tiere', 'geschichte', 'gesundheit',
    ];

    private const TOPIC_KEYS = [
        'musik' => 'topicMusik',
        'bayern' => 'topicBayern',
        'kochen' => 'topicKochen',
        'natur' => 'topicNatur',
        'blumen' => 'topicBlumen',
        'religion' => 'topicReligion',
        'familie' => 'topicFamilie',
        'tiere' => 'topicTiere',
        'geschichte' => 'topicGeschichte',
        'gesundheit' => 'topicGesundheit',
    ];

    /** @deprecated Legacy German labels → topic ids */
    private const LEGACY_TOPIC_LABELS = [
        'Klassische Musik' => 'musik',
        'Bayern & Heimat' => 'bayern',
        'Kochen & Backen' => 'kochen',
        'Natur & Garten' => 'natur',
        'Blumen & Pflanzen' => 'blumen',
        'Religion & Kirche' => 'religion',
        'Familie & Alltag' => 'familie',
        'Tiere' => 'tiere',
        'Heimat & Geschichte' => 'geschichte',
        'Gesundheit & Essen' => 'gesundheit',
    ];

    /** Livewire props that only touch the form — skip re-rendering the puzzle grid. */
    private const LIGHT_UPDATE_PROPS = [
        'selectedTopics', 'name', 'customContext', 'familyStory',
        'useFamilyStory', 'difficulty', 'wordCount', 'puzzleLanguage',
        'mode', 'zipCount',
    ];

    public function mount(): void
    {
        $this->selectedTopics = ['kochen', 'natur', 'familie', 'geschichte'];
        $this->normalizeSelectedTopics();
        if (Auth::check()) {
            $this->refreshUserData();
            $this->syncExportSettings();
        }
    }

    private function syncExportSession(): void
    {
        if (!$this->puzzle) {
            return;
        }
        session([
            'puzzle_export' => [
                'puzzle' => $this->puzzle,
                'name' => $this->name,
                'number' => $this->puzzleNumber,
            ],
        ]);
    }

    private function syncExportSettings(): void
    {
        if (!Auth::check()) {
            return;
        }
        session([
            'puzzle_export_settings' => array_merge($this->buildSettings(), [
                'patient_id' => $this->selectedPatientId,
            ]),
        ]);
    }

    private function requireAuth(): bool
    {
        if (Auth::check()) {
            return true;
        }
        $this->authMode = 'login';
        return false;
    }

    // ---------- UI string helper ----------
    public function t(string $key, ...$args): string
    {
        $ui = config('ui', []);
        $val = $ui[$this->language][$key] ?? ($ui['de'][$key] ?? $key);
        if (!empty($args) && is_string($val) && str_contains($val, '%')) {
            return vsprintf($val, $args);
        }
        return $val;
    }

    public function getLangCfgProperty(): array
    {
        return config("languages.{$this->language}") ?? config('languages.de');
    }

    public function getIsRtlProperty(): bool
    {
        return ($this->langCfg['dir'] ?? 'ltr') === 'rtl';
    }

    public function getCurrentUserProperty()
    {
        return Auth::user();
    }

    public function getIsPflegeheimProperty(): bool
    {
        return Auth::check() && Auth::user()->isPflegeheim();
    }

    // ---------- Navigation ----------
    public function setPanel(string $panel): void
    {
        if (!$this->requireAuth()) {
            return;
        }
        $this->activePanel = $panel;
        if ($panel === 'history') {
            $this->loadHistory();
        }
        if ($panel === 'patients') {
            $this->loadPatients();
        }
    }

    public function updatedLanguage(): void
    {
        $this->js(sprintf(
            'document.title = %s; document.documentElement.lang = %s;',
            json_encode($this->t('pageTitle')),
            json_encode($this->language)
        ));
    }

    public function setLanguage(string $lang): void
    {
        if (config("languages.$lang")) {
            $this->language = $lang;
        }
    }

    public function topicLabel(string $id, ?string $lang = null): string
    {
        $key = self::TOPIC_KEYS[$id] ?? $id;
        $lang = $lang ?? $this->language;
        $ui = config('ui', []);

        return $ui[$lang][$key] ?? ($ui['de'][$key] ?? $id);
    }

    public function getTopicsProperty(): array
    {
        return array_map(
            fn (string $id) => ['id' => $id, 'label' => $this->topicLabel($id)],
            self::TOPIC_IDS
        );
    }

    private function normalizeSelectedTopics(): void
    {
        $this->selectedTopics = array_values(array_unique(array_filter(array_map(
            fn (string $topic) => self::LEGACY_TOPIC_LABELS[$topic] ?? $topic,
            $this->selectedTopics
        ), fn (string $topic) => in_array($topic, self::TOPIC_IDS, true))));
    }

    private function resolvedTopicsForGeneration(): array
    {
        $this->normalizeSelectedTopics();

        return array_map(
            fn (string $id) => $this->topicLabel($id, $this->puzzleLanguage),
            $this->selectedTopics
        );
    }

    public function toggleTopic(string $id): void
    {
        $this->normalizeSelectedTopics();
        if (in_array($id, $this->selectedTopics, true)) {
            $this->selectedTopics = array_values(array_filter($this->selectedTopics, fn ($t) => $t !== $id));
        } else {
            $this->selectedTopics[] = $id;
        }
        if ($this->puzzle) {
            $this->skipRender();
        }
    }

    // ---------- Generation ----------
    public function generate(): mixed
    {
        if (!$this->requireAuth()) {
            return null;
        }
        @set_time_limit(180);
        $this->status = 'loading';
        $this->errorMessage = '';

        try {
            $settings = $this->buildSettings();
            $data = app(PuzzleGenerator::class)->generate($settings);
            $this->puzzle = $data;
            $this->puzzleNumber++;
            $this->status = 'done';
            $this->syncExportSession();
            $this->syncExportSettings();

            Puzzle::create([
                'user_id' => Auth::id(),
                'patient_id' => $this->selectedPatientId,
                'title' => $data['title'] ?? null,
                'language' => $data['language'] ?? $this->puzzleLanguage,
                'data_json' => $data,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->status = 'error';
            $this->errorMessage = $e->getMessage();
        }

        return null;
    }

    private function buildSettings(): array
    {
        return [
            'name' => $this->name,
            'language' => $this->puzzleLanguage,
            'difficulty' => $this->difficulty,
            'wordCount' => $this->wordCount,
            'healthProfile' => 'demenz',
            'customContext' => $this->customContext,
            'familyStory' => $this->familyStory,
            'useFamilyStory' => $this->useFamilyStory,
            'topics' => $this->resolvedTopicsForGeneration(),
            'wordHistory' => $this->buildWordHistory(),
        ];
    }

    private function buildWordHistory(): array
    {
        $counts = [];
        foreach ($this->memoryStats(limit: 30)['wordFreq'] as $row) {
            $counts[$row['word']] = $row['count'];
        }
        return $counts;
    }

    private function scopedPuzzlesQuery()
    {
        $query = Auth::user()->puzzles()->latest();
        if ($this->selectedPatientId) {
            $query->where('patient_id', $this->selectedPatientId);
        }
        return $query;
    }

    public function memoryStats(int $limit = 100): array
    {
        if (!Auth::check()) {
            return ['puzzleCount' => 0, 'uniqueWords' => 0, 'wordFreq' => [], 'entries' => []];
        }

        $puzzles = $this->scopedPuzzlesQuery()->limit($limit)->get();
        $counts = [];
        $entries = [];

        foreach ($puzzles as $p) {
            $data = $p->data_json ?? [];
            $words = [];
            foreach (($data['words'] ?? []) as $w) {
                $word = isset($w['word']) ? mb_strtoupper((string) $w['word']) : null;
                if ($word) {
                    $counts[$word] = ($counts[$word] ?? 0) + 1;
                    $words[$word] = true;
                }
            }
            if (!empty($data['loesungswort'])) {
                $lw = mb_strtoupper((string) $data['loesungswort']);
                $counts[$lw] = ($counts[$lw] ?? 0) + 1;
                $words[$lw] = true;
            }
            $entries[] = [
                'id' => $p->id,
                'title' => $p->title,
                'created_at' => $p->created_at?->format('d.m.Y H:i'),
                'word_count' => count($words),
            ];
        }

        arsort($counts);
        $wordFreq = [];
        foreach ($counts as $word => $count) {
            $wordFreq[] = ['word' => $word, 'count' => $count];
        }

        return [
            'puzzleCount' => count($entries),
            'uniqueWords' => count($counts),
            'wordFreq' => $wordFreq,
            'entries' => $entries,
        ];
    }

    public function getShowMemoryProperty(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if ($this->isPflegeheim) {
            return (bool) $this->selectedPatientId;
        }
        return true;
    }

    public function copyWordList(): void
    {
        $lines = array_map(
            fn ($row) => $row['word'].': '.$row['count'].'×',
            $this->memoryStats()['wordFreq']
        );
        if ($lines === []) {
            return;
        }
        $this->js('navigator.clipboard.writeText('.json_encode(implode("\n", $lines), JSON_UNESCAPED_UNICODE).')');
    }

    public function clearScopedMemory(): void
    {
        if (!Auth::check()) {
            return;
        }
        $this->scopedPuzzlesQuery()->delete();
        $this->puzzle = null;
        $this->status = 'idle';
        $this->loadHistory();
    }

    // ---------- Auth ----------
    public function login(): void
    {
        $data = $this->validate([
            'authEmail' => 'required|email',
            'authPassword' => 'required',
        ]);
        if (!Auth::attempt(['email' => $data['authEmail'], 'password' => $data['authPassword']], true)) {
            $this->addError('authEmail', $this->t('errLoginFailed'));
            return;
        }
        $this->afterAuth();
    }

    public function register(): void
    {
        $data = $this->validate([
            'authName' => 'required|min:2',
            'authEmail' => 'required|email|unique:users,email',
            'authPassword' => 'required|min:6',
        ]);
        $user = \App\Models\User::create([
            'name' => $data['authName'],
            'email' => $data['authEmail'],
            'password' => $data['authPassword'],
            'role' => $this->authIsPflegeheim ? 'pflegeheim' : 'user',
        ]);
        Auth::login($user, true);
        $this->afterAuth();
    }

    private function afterAuth(): void
    {
        $this->reset(['authName', 'authEmail', 'authPassword', 'authIsPflegeheim']);
        $this->authMode = 'login';
        $this->activePanel = 'create';
        $this->refreshUserData();
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $this->reset([
            'patients', 'history', 'selectedPatientId', 'puzzle', 'puzzleNumber',
            'status', 'errorMessage', 'name', 'familyStory', 'customContext',
        ]);
        $this->authMode = 'login';
        $this->activePanel = 'create';
        $this->status = 'idle';
    }

    private function refreshUserData(): void
    {
        if ($this->isPflegeheim) {
            $this->loadPatients();
        }
        $this->loadHistory();
    }

    // ---------- Patients ----------
    public function loadPatients(): void
    {
        if (!$this->isPflegeheim) {
            return;
        }
        $this->patients = Auth::user()->patients()->orderBy('name')->get()->map(function (Patient $p) {
            $story = trim($p->family_story ?? '');

            return [
                'id' => $p->id,
                'name' => $p->name,
                'language' => $p->language,
                'difficulty' => $p->difficulty,
                'has_story' => $story !== '',
                'story_chars' => mb_strlen($story),
            ];
        })->values()->all();
    }

    private function persistSelectedPatient(): bool
    {
        if (!$this->selectedPatientId || !$this->isPflegeheim) {
            return false;
        }
        $patient = Auth::user()?->patients()->find($this->selectedPatientId);
        if (!$patient) {
            return false;
        }
        $patient->update([
            'name' => $this->name,
            'family_story' => $this->familyStory,
            'custom_context' => $this->customContext,
            'language' => $this->puzzleLanguage,
            'difficulty' => $this->difficulty,
        ]);
        $this->loadPatients();

        return true;
    }

    public function createPatient(): void
    {
        $this->validate(['newPatientName' => 'required|min:2'], [], ['newPatientName' => 'Name']);
        if (!$this->isPflegeheim) {
            return;
        }
        $p = Auth::user()->patients()->create([
            'name' => $this->newPatientName,
            'language' => $this->puzzleLanguage,
            'difficulty' => $this->difficulty,
        ]);
        $this->newPatientName = '';
        $this->loadPatients();
        $this->editingPatientId = $p->id;
        $this->selectPatient($p->id);
        $this->activePanel = 'patients';
        $this->patientSaveMessage = '';
    }

    public function selectPatient($id): void
    {
        $id = $id === '' || $id === null ? null : (int) $id;
        $this->selectedPatientId = $id;
        if (!$id) {
            return;
        }
        $patient = Auth::user()->patients()->find($id);
        if (!$patient) {
            return;
        }
        $this->name = $patient->name;
        $this->familyStory = $patient->family_story ?? '';
        $this->customContext = $patient->custom_context ?? '';
        $this->puzzleLanguage = $patient->language ?: 'de';
        $this->difficulty = $patient->difficulty ?: 'leicht';
        $this->loadHistory();
    }

    public function editPatient($id): void
    {
        $this->editingPatientId = (int) $id;
        $this->selectPatient($id);
        $this->activePanel = 'patients';
        $this->patientSaveMessage = '';
    }

    public function savePatientProfile(): void
    {
        if (!$this->editingPatientId || !$this->isPflegeheim) {
            return;
        }
        $this->validate(['name' => 'required|min:2'], [], ['name' => $this->t('labelPatientName')]);
        $this->selectedPatientId = $this->editingPatientId;
        if ($this->persistSelectedPatient()) {
            $this->patientSaveMessage = $this->t('patientSavedOk');
        }
    }

    public function cancelPatientEdit(): void
    {
        $this->editingPatientId = null;
        $this->patientSaveMessage = '';
    }

    public function deletePatient($id): void
    {
        $patient = Auth::user()->patients()->find((int) $id);
        if ($patient) {
            $patient->delete();
            if ($this->selectedPatientId === (int) $id) {
                $this->selectedPatientId = null;
            }
            if ($this->editingPatientId === (int) $id) {
                $this->editingPatientId = null;
                $this->patientSaveMessage = '';
            }
            $this->loadPatients();
        }
    }

    public function updatedSelectedPatientId($value): void
    {
        $this->selectPatient($value);
    }

    public function updated($prop): void
    {
        if (Auth::check() && !in_array($prop, ['authEmail', 'authPassword', 'authName'], true)) {
            $this->syncExportSettings();
        }

        if ($this->puzzle && in_array($prop, self::LIGHT_UPDATE_PROPS, true)) {
            $this->skipRender();
        }

        // Auto-save patient-specific fields when a patient is selected
        if ($this->selectedPatientId && in_array($prop, ['name', 'familyStory', 'customContext', 'puzzleLanguage', 'difficulty'], true)) {
            $this->persistSelectedPatient();
        }
    }

    // ---------- History ----------
    public function loadHistory(): void
    {
        if (!Auth::check()) {
            return;
        }
        $this->history = Auth::user()->puzzles()
            ->when($this->selectedPatientId, fn ($q) => $q->where('patient_id', $this->selectedPatientId))
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'language', 'patient_id', 'created_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'language' => $p->language,
                'patient_id' => $p->patient_id,
                'created_at' => $p->created_at?->format('d.m.Y H:i'),
            ])->toArray();
    }

    public function openHistoryItem($id): void
    {
        $p = Auth::user()?->puzzles()->find((int) $id);
        if ($p) {
            $this->puzzle = $p->data_json;
            $this->status = 'done';
            $this->syncExportSession();
            $this->activePanel = 'create';
        }
    }

    public function deleteHistoryItem($id): void
    {
        $p = Auth::user()?->puzzles()->find((int) $id);
        if ($p) {
            $p->delete();
            $this->loadHistory();
        }
    }

    /**
     * Build a structured view model for on-screen puzzle rendering.
     */
    public function gridView(): ?array
    {
        if (!$this->puzzle) {
            return null;
        }
        $p = $this->puzzle;
        $gw = (int) $p['gridWidth'];
        $gh = (int) $p['gridHeight'];
        $words = $p['words'] ?? [];
        $lang = $p['language'] ?? 'de';
        $langCfg = config("languages.$lang") ?? config('languages.de');

        $grid = array_fill(0, $gh, array_fill(0, $gw, null));
        foreach ($words as $w) {
            $letters = \App\Services\WordNormalizer::chars($w['word']);
            foreach ($letters as $i => $ch) {
                $r = $w['direction'] === 'down' ? $w['row'] + $i : $w['row'];
                $c = $w['direction'] === 'across' ? $w['col'] + $i : $w['col'];
                if ($r >= 0 && $r < $gh && $c >= 0 && $c < $gw) {
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

        $loesOrderMap = [];
        foreach (($p['loesungswortCells'] ?? []) as $i => $cell) {
            $loesOrderMap["{$cell['row']},{$cell['col']}"] = $cell['n'] ?? ($i + 1);
        }

        $cells = [];
        for ($r = 0; $r < $gh; $r++) {
            $row = [];
            for ($c = 0; $c < $gw; $c++) {
                $key = "$r,$c";
                $row[] = [
                    'black' => $grid[$r][$c] === null,
                    'letter' => $grid[$r][$c],
                    'num' => $numMap[$key] ?? null,
                    'loes' => $loesOrderMap[$key] ?? null,
                ];
            }
            $cells[] = $row;
        }

        $across = array_values(array_filter($words, fn ($w) => $w['direction'] === 'across'));
        $down = array_values(array_filter($words, fn ($w) => $w['direction'] === 'down'));
        usort($across, fn ($a, $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));
        usort($down, fn ($a, $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));

        return [
            'gw' => $gw,
            'gh' => $gh,
            'cells' => $cells,
            'across' => $across,
            'down' => $down,
            'loesWord' => $p['loesungswort'] ?? '',
            'loesLen' => \App\Services\WordNormalizer::len($p['loesungswort'] ?? ''),
            'loesHint' => ($p['loesungswortHinweis'] ?? '') !== '' ? $p['loesungswortHinweis'] : ($langCfg['universal_hint'] ?? ''),
            'loesLabel' => $langCfg['solution_word_label'] ?? 'Lösungswort',
            'acrossLabel' => $langCfg['across_label'] ?? '',
            'downLabel' => $langCfg['down_label'] ?? '',
            'rtl' => ($langCfg['dir'] ?? 'ltr') === 'rtl',
        ];
    }

    public function getPageTitleProperty(): string
    {
        return $this->t('pageTitle');
    }

    public function render()
    {
        return view('pages.puzzle-app.puzzle-app')
            ->layout('layouts::app', [
                'htmlLang' => $this->language,
            ])
            ->title($this->t('pageTitle'));
    }

    public function with(): array
    {
        return [
            'grid' => $this->gridView(),
            'memory' => $this->memoryStats(),
        ];
    }
};
