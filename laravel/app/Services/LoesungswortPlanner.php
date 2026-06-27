<?php

namespace App\Services;

class LoesungswortPlanner
{
    public function __construct(private AnthropicClient $client) {}

    /**
     * Phase 0: pick a solution word + hint before generating the word list.
     * @return array{loesungswort:string,loesungswortHinweis:string}|null
     */
    public function fetchPlan(array $settings, int $wordCount): ?array
    {
        $lang = Lang::resolve($settings);
        $langCfg = Lang::for($lang);
        $name = trim((string) ($settings['name'] ?? ''));
        $topics = (isset($settings['topics']) && is_array($settings['topics']) && count($settings['topics']))
            ? $settings['topics']
            : ['Alltag', 'Familie', 'Natur'];
        $familyStory = mb_substr(trim((string) ($settings['familyStory'] ?? '')), 0, 650);
        $customContext = mb_substr(trim((string) ($settings['customContext'] ?? '')), 0, 450);

        $ctxParts = array_filter([
            $name !== '' ? "Vorname (wenn passend): {$name}" : null,
            'Themen: ' . implode(', ', $topics),
            $familyStory !== '' ? "Lebens-/Familienkontext: {$familyStory}" : null,
            $customContext !== '' ? "Zusätzliche Wünsche: {$customContext}" : null,
        ]);
        $ctx = implode("\n", $ctxParts);

        $avoidBlock = '';
        $wordHistory = $settings['wordHistory'] ?? null;
        if (is_array($wordHistory)) {
            $used = [];
            foreach ($wordHistory as $w => $c) {
                if (strlen((string) $w) >= 4 && is_numeric($c) && $c >= 1) {
                    $used[] = [$w, (int) $c];
                }
            }
            usort($used, fn ($a, $b) => $b[1] <=> $a[1]);
            $used = array_slice(array_map(fn ($e) => $e[0], $used), 0, 40);
            if (count($used)) {
                $avoidBlock = "\nBereits verwendete Lösungswörter (NICHT nochmals wählen): " . implode(', ', $used) . "\n";
            }
        }

        $lwIntro = sprintf($langCfg['lw_intro'], $wordCount);
        $userContent = "{$lwIntro}\n\n{$ctx}\n{$avoidBlock}\nAntworten Sie mit **NUR** diesem JSON:\n{\"loesungswort\":\"WORT\",\"loesungswort_hinweis\":\"Kurzer Satz ohne das Wort wörtlich zu nennen\"}\n\nRegeln:\n- loesungswort: {$langCfg['lw_char_rule']}\n- **Abwechslungsreich und überraschend** — wähle ein frisches, unerwartetes Wort aus dem Themenkontext.\n- emotional passend zum Kontext, aber frisch und unerwartet\n- loesungswort_hinweis: ein liebevoller Satz in {$langCfg['name']}, ohne das Wort direkt zu nennen";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $text = $this->client->message(PromptBuilder::systemPrompt($lang), $userContent, 400);
                $data = AnthropicClient::parseJson($text);
                if (!is_array($data)) {
                    continue;
                }
                $lw = isset($data['loesungswort']) ? WordNormalizer::normalise((string) $data['loesungswort'], $lang) : '';
                $hint = trim((string) ($data['loesungswort_hinweis'] ?? $data['loesungswortHinweis'] ?? ''));
                $len = WordNormalizer::len($lw);
                if ($len >= 4 && $len <= 10 && $hint !== '') {
                    return ['loesungswort' => $lw, 'loesungswortHinweis' => $hint];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return null;
    }

    private function rowsForPrompt(array $placedWords, int $clueMax): array
    {
        $rows = array_map(fn ($w) => [
            'number' => $w['number'],
            'direction' => $w['direction'],
            'word' => $w['word'],
            'clue' => mb_substr((string) ($w['clue'] ?? ''), 0, $clueMax),
        ], $placedWords);
        usort($rows, function ($a, $b) {
            if ($a['number'] !== $b['number']) {
                return $a['number'] <=> $b['number'];
            }
            return strcmp((string) $a['direction'], (string) $b['direction']);
        });
        return $rows;
    }

    /**
     * Ask only for the letter-source mapping for a fixed solution word.
     * @return array{quelle:array}|null
     */
    public function fetchQuelleOnly(array $placedWords, string $fixedLw, string $title, array $settings): ?array
    {
        $lang = Lang::resolve($settings);
        if (count($placedWords) === 0 || $fixedLw === '') {
            return null;
        }
        $rows = $this->rowsForPrompt($placedWords, 160);
        $lw = WordNormalizer::normalise($fixedLw, $lang);
        $lwLen = WordNormalizer::len($lw);
        $rowsJson = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $typeExtra = PromptBuilder::puzzleTypePromptExtra('schweden');

        $userContent = "Das Kreuzworträtsel ist gelegt. Titel: " . ($title ?: 'Rätsel') . "\n\n{$typeExtra}\n\n**Lösungswort ist fest:** „{$lw}\" ({$lwLen} Buchstaben in dieser exakten Reihenfolge).\n\n=== EINTRÄGE (nur diese \"word\"-Strings verwenden) ===\n{$rowsJson}\n\nGeben Sie **nur** \"loesungswort_quelle\" zurück — ein JSON-Array mit **{$lwLen}** Objekten. Jedes Objekt: { \"word\": \"...\", \"buchstabe_index\": n }\n- Position k im Lösungswort (0-basiert) = Buchstabe lw[k]; \"word\"+\"buchstabe_index\" muss diesen Buchstaben liefern.\n- **Keine Gitterzelle doppelt.**\n- **Streuen Sie stark:** nutzen Sie **viele verschiedene** Wörter; **vermeiden Sie**, dass alle Indices aus **einem einzigen** waagerechten oder senkrechten Wort stammen (keine durchgehende Linie im Gitter).\n\nAntwort: **NUR** JSON dieser Form:\n{\"loesungswort_quelle\":[{ \"word\": \"X\", \"buchstabe_index\": 0 }]}\n(Array-Länge exakt {$lwLen})";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $text = $this->client->message(PromptBuilder::systemPrompt($lang), $userContent, 2000);
                $data = AnthropicClient::parseJson($text);
                $quelle = $data['loesungswort_quelle'] ?? $data['loesungswortQuelle'] ?? null;
                if (is_array($quelle) && count($quelle) === $lwLen) {
                    return ['quelle' => $quelle];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return null;
    }

    /**
     * Phase 2: derive a solution word AND its letter sources after placement.
     * @return array{loesungswort:string,hint:string,quelle:array}|null
     */
    public function fetchAfterPlacement(array $placedWords, string $title, array $settings): ?array
    {
        $lang = Lang::resolve($settings);
        $langCfg = Lang::for($lang);
        if (count($placedWords) === 0) {
            return null;
        }
        $rows = $this->rowsForPrompt($placedWords, 200);
        $rowsJson = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = trim((string) ($settings['name'] ?? ''));
        $dedication = $name !== '' ? "Beziehen Sie den Vornamen „{$name}\" ein, wenn es zum Thema passt." : '';
        $typeExtra = PromptBuilder::puzzleTypePromptExtra('schweden');

        $userContent = "Das Kreuzworträtsel ist **fertig gelegt**. Sie kennen **exakt** alle Lösungswörter, **Rätselnummer**, **Richtung** (across = waagerecht, down = senkrecht) und **Hinweis**.\n\nTitel: " . ($title ?: 'Rätsel') . "\n{$dedication}\n\n{$typeExtra}\n\n=== ALLE EINTRÄGE (nur diese \"word\"-Strings in \"loesungswort_quelle\" verwenden) ===\n{$rowsJson}\n=== Ende Liste ===\n\nErzeugen Sie ein **Lösungswort** im Rätselheft-Stil in **{$langCfg['name']}**: thematisch passend. Die Spielerinnen lesen die Buchstaben später aus **gekennzeichneten** Gitterfeldern — ordnen Sie jedem Buchstaben des Lösungsworts **einen** Buchstaben **eines** der Listeneinträge zu.\n\nAntworten Sie mit **NUR** diesem JSON (kein Markdown, keine Erklärung):\n{\n  \"loesungswort\": \"BEISPIEL\",\n  \"loesungswort_hinweis\": \"Ein kurzer liebevoller Satz in {$langCfg['name']} – ohne das Lösungswort wörtlich zu nennen.\",\n  \"loesungswort_quelle\": [\n    { \"word\": \"EINTAG\", \"buchstabe_index\": 0 }\n  ]\n}\n\nRegeln:\n- \"loesungswort\": {$langCfg['lw_char_rule_after']}\n- \"loesungswort_quelle\": genau so viele Objekte wie \"loesungswort\" Buchstaben\n- Jedes \"word\" muss **identisch** zu einem \"word\" in der Liste oben sein (gleiche Normalisierung)\n- \"buchstabe_index\": **0** = erster Buchstabe dieses Wortes, **1** = zweiter, …; muss zum jeweiligen Buchstaben in \"loesungswort\" passen\n- **Keine Zelle doppelt**: dieselbe physische Gitterposition darf nicht zweimal vorkommen\n- Bevorzugen Sie Buchstaben an **Kreuzungen**\n- \"loesungswort_hinweis\": emotional passend in {$langCfg['name']}, Lösungswort nicht wörtlich nennen";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $text = $this->client->message(PromptBuilder::systemPrompt($lang), $userContent, 1200);
                $data = AnthropicClient::parseJson($text);
                if (!is_array($data)) {
                    continue;
                }
                $lw = isset($data['loesungswort']) ? WordNormalizer::normalise((string) $data['loesungswort'], $lang) : '';
                $hint = trim((string) ($data['loesungswort_hinweis'] ?? $data['loesungswortHinweis'] ?? ''));
                $quelle = $data['loesungswort_quelle'] ?? $data['loesungswortQuelle'] ?? null;
                $len = WordNormalizer::len($lw);
                if ($len >= 4 && $len <= 12 && is_array($quelle) && count($quelle) === $len) {
                    return ['loesungswort' => $lw, 'hint' => $hint, 'quelle' => $quelle];
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return null;
    }
}
