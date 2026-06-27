<?php

namespace App\Services;

class PromptBuilder
{
    public const HEALTH_KEYS = ['none', 'demenz', 'depression', 'schlaganfall', 'parkinson', 'sehschwaeche', 'angst'];
    public const DIFFICULTY_KEYS = ['sehr_leicht', 'leicht', 'mittel'];
    public const WORD_COUNTS = [16, 20, 24, 28, 32];

    public static function systemPrompt(string $lang = 'de'): string
    {
        $cfg = Lang::for($lang);
        return "You are a compassionate crossword puzzle editor creating puzzles {$cfg['system_lang']} for elderly people (seniors).\n"
            . "You return EXCLUSIVELY valid JSON – no additional text, no Markdown code blocks.\n"
            . "When personal family or life information is provided, it is binding and must not be ignored.\n"
            . "When a health or support context is given, it is binding – formulate respectfully and without stigma.\n"
            . "The chosen difficulty level is binding for word and clue selection.";
    }

    public static function normalizeHealthProfile(?string $code): string
    {
        $c = (string) ($code ?? 'none');
        return in_array($c, self::HEALTH_KEYS, true) ? $c : 'none';
    }

    public static function normalizeDifficulty(?string $code): string
    {
        $c = (string) ($code ?? 'leicht');
        return in_array($c, self::DIFFICULTY_KEYS, true) ? $c : 'leicht';
    }

    public static function healthProfileBlock(string $code): string
    {
        $blocks = [
            'none' => '',
            'demenz' => "\n=== UNTERSTÜTZUNGSKONTEXT: DEMENZ / LEICHTE KOGNITIVE BEEINTRÄCHTIGUNG (verbindlich) ===\nDie Rätselperson lebt mit Demenz oder vergleichbar eingeschränkter Merkfähigkeit. Wählen Sie durchweg **sehr vertraute, konkrete** Alltagswörter (Gegenstände, Natur, Essen, einfache Berufe). Hinweise: **kurz, eindeutig**, ein Bild im Kopf (z. B. „Rot und sauer, wächst am Busch“ statt literarischer Umschreibung). Keine Trickfragen, keine Doppelbedeutungen, kein Zeitdruck im Text. Positiver, würdevoller Ton.\n",
            'depression' => "\n=== KONTEXT: STIMMUNG, ANTREIB, KONZENTRATION (verbindlich) ===\nEnergie und Konzentration können geschwächt sein. **Kurze, freundliche** Hinweise ohne Eile oder Leistungsdruck. Bekannte Wörter; vermeiden Sie karge oder tadelnde Formulierungen. Kleine Freuden und Gewohnheiten (Natur, Tee, Musik) sind willkommen.\n",
            'schlaganfall' => "\n=== KONTEXT: Z. B. NACH SCHLAGANFALL — SPRACHE / LESEN (verbindlich) ===\nBevorzugen Sie **kurze, häufige** Wörter (4–6 Buchstaben wo möglich). Einfache Satzstruktur in den Hinweisen. Keine verschachtelten Sätze. Ein klares Lesebild pro Hinweis.\n",
            'parkinson' => "\n=== KONTEXT: MOTORIK / FEINMOTORIK (verbindlich) ===\nBegriffe sollen **leicht zu erkennen** sein; Hinweise klar gegliedert (gern mit Komma kurz teilen, nicht ein endloser Satz). Keine winzig-komplexen Rätselhinweise — Klarheit vor Kürze.\n",
            'sehschwaeche' => "\n=== KONTEXT: SEHSCHWÄCHE (verbindlich) ===\nHinweistexte: **sachlich und klar**, ohne auf kleine Unterscheidungen im Schriftbild anzuspielen (keine „erkennen Sie den Unterschied“-Aufgaben). Gut unterscheidbare Alltagsbegriffe.\n",
            'angst' => "\n=== KONTEXT: ÄNGSTLICHKEIT / UNRUHE (verbindlich) ===\nRuhige, **vorhersehbare** Formulierungen. Keine Schreck-, Druck- oder Konflikt-Themen in Hinweisen. Vertraute, behagliche Bilder.\n",
        ];
        return $blocks[$code] ?? '';
    }

    public static function difficultyBlock(string $code): string
    {
        $blocks = [
            'sehr_leicht' => "\nSCHWIERIGKEIT: **SEHR LEICHT** (verbindlich):\n- Vorzugsweise Lösungswörter mit **4–6 Buchstaben**, alltäglich und konkret.\n- Hinweise: **ein kurzer Satz**, direkt auf den Begriff zielend, ohne Umweg.\n- Keine seltenen Fremdwörter, keine Kulturhistorie-Spezialisten-Namen.\n",
            'leicht' => "\nSCHWIERIGKEIT: **LEICHT** (verbindlich):\n- Lösungswörter **4–8 Buchstaben**, überwiegend alltagsnah.\n- Hinweise: klar und freundlich; leichte Umschreibung erlaubt, aber immer **lösbar ohne Rätseltricks**.\n",
            'mittel' => "\nSCHWIERIGKEIT: **MITTEL** (für noch fittere Seniorinnen — verbindlich):\n- Lösungswörter bis **9 Buchstaben** möglich; gelegentlich etwas **weniger häufig**, aber nie hochakademisch.\n- Hinweise dürfen **einen kleinen Denkschritt** verlangen, müssen aber fair und ohne List bleiben. Weiterhin respektvoll und positiv.\n",
        ];
        return $blocks[$code] ?? $blocks['leicht'];
    }

    public static function puzzleTypePromptExtra(string $puzzleType): string
    {
        if ($puzzleType === 'schweden') {
            return "\nRÄTSELART „Schwedenstil“ (kompakte Hinweise **nur in der nummerierten Liste** unter dem Gitter):\n- Jeder Hinweis („clue“) maximal etwa 50 Zeichen, lieber kürzer; trotzdem für Seniorinnen verständlich.\n- Im Gitter erscheinen wie üblich **nur Nummern** an Wortanfängen, keine Hinweistexte in den weißen Feldern.\n";
        }
        return "\nRÄTSELART „Standard-Kreuzworträtsel“:\n- Hinweise als kurze, klare Sätze; bei persönlichen Themen gern etwas ausführlicher.\n";
    }

    /** Resolve word count from settings to one of the allowed values. */
    public static function wordCount(array $settings): int
    {
        $wc = (int) ($settings['wordCount'] ?? 24);
        return in_array($wc, self::WORD_COUNTS, true) ? $wc : 24;
    }

    /**
     * Build the main word-generation prompt. Ported from `buildUserPrompt`.
     */
    public static function userPrompt(array $settings): string
    {
        $lang = Lang::resolve($settings);
        $langCfg = Lang::for($lang);
        $healthProfile = self::normalizeHealthProfile($settings['healthProfile'] ?? null);
        $difficulty = self::normalizeDifficulty($settings['difficulty'] ?? null);
        $puzzleType = 'schweden';
        $name = trim((string) ($settings['name'] ?? ''));
        $nameLine = $name !== ''
            ? "Optionaler Vorname für warme, sparsame Ansprache in einzelnen Hinweisen: {$name}"
            : 'Kein Vorname angegeben – formulieren Sie die Hinweise allgemein herzlich (ohne fiktiven Namen).';

        $wordCount = self::wordCount($settings);

        $topics = (isset($settings['topics']) && is_array($settings['topics']) && count($settings['topics']))
            ? $settings['topics']
            : ['Natur & Jahreszeiten', 'Alltag & Zuhause', 'Einfache Freizeit', 'Essen & Trinken'];

        $customContext = trim((string) ($settings['customContext'] ?? ''));
        $familyStory = trim((string) ($settings['familyStory'] ?? ''));
        $useFamily = ($settings['useFamilyStory'] ?? true) !== false && strlen($familyStory) > 0;

        $topicBlock = implode("\n", array_map(fn ($t) => "• {$t}", $topics));

        $plannedLw = !empty($settings['plannedLoesungswort'])
            ? WordNormalizer::normalise($settings['plannedLoesungswort'], $lang)
            : '';
        $plannedLwLen = WordNormalizer::len($plannedLw);

        $loesungBlock = $plannedLw !== ''
            ? "\n=== FIXES LÖSUNGSWORT (verbindlich) ===\nDas **Lösungswort** für das Rätselheft ist bereits festgelegt:\n\n„{$plannedLw}“ ({$plannedLwLen} Buchstaben)\n\n- **Jeder** dieser Buchstaben muss irgendwo in mindestens einem Ihrer {$wordCount} „word“-Einträge **als Buchstabe vorkommen** (egal an welcher Position).\n- Kommt derselbe Buchstabe im Lösungswort mehrfach vor, muss er **mindestens so oft** über **alle** „word“-Strings zusammengezählt vorkommen.\n- **Wichtig:** Planen Sie so, dass die Buchstaben **nicht alle aus einem einzigen** Ihrer Listeneinträge stammen — streuen Sie sie über **viele verschiedene** Begriffe (sonst liegen alle Markierungen später in einer Geraden im Gitter).\n=== Ende Lösungswort-Vorgabe ===\n"
            : '';

        $personalMin = (int) round($wordCount * 0.42);
        $generalMin = (int) round($wordCount * 0.33);
        $freeMax = $wordCount - $personalMin - $generalMin;

        $personalBlock = '';
        if ($useFamily) {
            $extra = $customContext !== '' ? "\nZusätzliche Wünsche vom Ersteller: {$customContext}\n" : '';
            $personalBlock = "\n=== PERSÖNLICHE FAMILIEN- UND LEBENSINFORMATIONEN (verbindlich) ===\nDer Ersteller hat folgende Angaben gemacht. Sie MÜSSEN diese ernst nehmen.\n\nLeiten Sie Begriffe u. a. ab aus: Vornamen, Orten, Berufen, Beziehungen (Ehepartner, Kinder, Enkel), Schulen, Ländern oder Reisen – genau wie beschrieben, ohne Personen wegzulassen. Wenn ein Name kürzer als 4 Buchstaben ist, verwenden Sie stattdessen einen klaren verwandten Begriff aus dem Kontext (z. B. Stadt, Beruf, ‚LEHRER‘, ‚FAMILIE‘) oder einen passenden längeren Namen aus dem Text.\n\nHinweise: liebevoll, konkret, leicht verständlich für die gewählte Zielgruppe – keine Rätsel um Trauer, aber Ehepartner und Kinder respektvoll nennen dürfen (z. B. „Er war Lehrer für Englisch“, „Tochter unterrichtete Deutsch“).\n\n{$familyStory}\n{$extra}=== Ende der persönlichen Informationen ===\n\nMISCHUNG MIT ALLGEMEINEN BEGRIFFEN (verbindliche Aufteilung der {$wordCount} Wörter):\n- Mindestens {$personalMin} Lösungswörter inkl. Hinweise sollen sich eindeutig auf die persönlichen Informationen beziehen (Familie, Orte, Berufe, Beziehungen).\n- Mindestens {$generalMin} Lösungswörter sollen klassische, leichte Allgemeinbegriffe sein, passend zu diesen Themen (nicht aus dem Familientext „abfischbar“ – echte allgemeine Begriffe wie Blume, Bach, Brot, Walzer, Sonne je nach Thema):\n{$topicBlock}\n- Die restlichen bis {$freeMax} Einträge frei wählen (persönlich oder allgemein), damit das Gitter gut vernetzbar bleibt.\nZiel: ein ausgewogenes Rätsel – nicht nur Familie, sondern auch vertraute, „normale“ Kreuzwort-Begriffe aus den gewählten Themen.\n";
        }

        $generalBlock = $useFamily
            ? ''
            : "\nAllgemeine Themenschwerpunkte (gut mischen, einfache Begriffe):\n{$topicBlock}\n"
                . ($customContext !== '' ? "\nZusätzliche Wünsche vom Ersteller: {$customContext}\n" : '');

        $audienceLine = $healthProfile === 'none'
            ? 'Zielgruppe (ohne speziellen Gesundheitsfokus): ältere Menschen; oft mit leichter kognitiver Einschränkung (z. B. Demenz). Wortwahl und Hinweise müssen zur gewählten **Schwierigkeit** passen.'
            : 'Zielgruppe und sprachliche Leitplanken: im **Gesundheits-/Unterstützungskontext** oben beschrieben (verbindlich). Wortlänge und Alltagsnähe **zusätzlich** strikt gemäß **Schwierigkeit**.';

        $wordHistoryBlock = self::wordHistoryBlock($settings['wordHistory'] ?? null);

        $generateIntro = sprintf($langCfg['generate_intro'], $wordCount);
        $wordBuffer = min(8, max(4, (int) round($wordCount * 0.25)));
        $deliverCount = $wordCount + $wordBuffer;
        $difficultyBlock = self::difficultyBlock($difficulty);
        $healthBlock = self::healthProfileBlock($healthProfile);
        $typeExtra = self::puzzleTypePromptExtra($puzzleType);
        $charRule = $langCfg['char_rule'];
        $titleLine = $name !== ''
            ? "Als JSON-\"title\": herzlicher Titel (Versalien) in {$langCfg['name']}."
            : "Als JSON-\"title\": kurzer herzlicher Titel (Versalien, Magazinstil) in {$langCfg['name']}.";
        $lwClosing = $plannedLw !== ''
            ? 'Liefern Sie **kein** Lösungswort-Feld in JSON — es ist oben bereits festgelegt.'
            : 'Kein Lösungswort in dieser Antwort — es wird separat festgelegt.';

        return "{$generateIntro}\n\n{$difficultyBlock}{$healthBlock}\n{$audienceLine} Ton: warm, würdevoll, positiv, sehr klar. Keine ironischen Texte, keine unnötig schweren Begriffe.\n\n{$nameLine}\n{$titleLine}\n{$loesungBlock}{$wordHistoryBlock}{$personalBlock}{$generalBlock}\n{$typeExtra}\n\nTechnische Regeln:\n{$charRule}\n- Liefern Sie **{$deliverCount}** Einträge in \"words\" ({$wordCount} werden ins Gitter gelegt, {$wordBuffer} Reserve für gute Buchstaben-Verknüpfung). Bevorzugen Sie Wörter mit **gemeinsamen Buchstaben** (E, N, R, S, A, I …), damit sich das Gitter gut füllen lässt.\n\nAntwortformat (NUR dieses JSON, **genau {$deliverCount}** Objekte in \"words\"):\n{\n  \"title\": \"Kurzer herzlicher Titel\",\n  \"words\": [\n    { \"word\": \"BEISPIEL\", \"clue\": \"Kurzer Hinweis\" }\n  ]\n}\n\n{$lwClosing}";
    }

    /** Word-memory avoidance block. */
    public static function wordHistoryBlock($wordHistory): string
    {
        if (!is_array($wordHistory) || count($wordHistory) === 0) {
            return '';
        }
        $entries = [];
        foreach ($wordHistory as $w => $c) {
            if (strlen((string) $w) >= 4 && is_numeric($c) && $c >= 1) {
                $entries[] = [$w, (int) $c];
            }
        }
        if (count($entries) === 0) {
            return '';
        }
        usort($entries, fn ($a, $b) => $b[1] <=> $a[1]);
        $entries = array_slice($entries, 0, 80);
        $wordList = implode(', ', array_map(fn ($e) => $e[1] > 1 ? "{$e[0]} ({$e[1]}×)" : $e[0], $entries));

        return "\n=== WORTGEDÄCHTNIS — BEREITS VERWENDETE WÖRTER (verbindlich) ===\nDie folgenden Lösungswörter wurden in früheren Rätseln für diese Person bereits benutzt. **Verwende sie nicht noch einmal** — wähle stets frische, andere Begriffe, um Wiederholungen über mehrere Rätsel hinweg zu vermeiden. Besonders häufig verwendete Wörter (hohe Zahl) sind besonders wichtig zu meiden.\n\n{$wordList}\n=== Ende Wortgedächtnis ===\n";
    }
}
