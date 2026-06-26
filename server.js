const express = require('express');
const Anthropic = require('@anthropic-ai/sdk');
const path = require('path');

const IS_SERVERLESS = !!process.env.VERCEL;

const app = express();
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

const client = new Anthropic({
  apiKey: process.env.ANTHROPIC_API_KEY,
});

// ---------------------------------------------------------------------------
// Language configuration
// ---------------------------------------------------------------------------

const LANG_CONFIG = {
  de: {
    name: 'Deutsch', dir: 'ltr',
    acrossLabel: 'Waagerecht →', downLabel: 'Senkrecht ↓',
    solutionWordLabel: 'Lösungswort',
    charRule: '- "word": 4–9 Buchstaben, nur A–Z; Umlaute als AE, OE, UE; SS statt ß\n- "clue": ein kurzer, freundlicher Satz auf Deutsch\n- Bevorzuge Buchstaben E, N, R, S, T, A, I, O für gutes Vernetzen',
    lwCharRule: '5–8 Buchstaben, deutsch, nur A–Z (Umlaute als AE, OE, UE, SS statt ß)',
    lwCharRuleAfter: '4–9 Buchstaben, nur A–Z; Umlaute als AE, OE, UE; SS statt ß',
    generateIntro: (wc) => `Erstelle genau ${wc} deutsche Wörter mit jeweils einem kurzen Hinweis (Rätselfrage) für ein Kreuzworträtsel.`,
    lwIntro: (wc) => `Für ein Kreuzworträtsel mit etwa ${wc} Begriffen wird **zuerst** das **Lösungswort** (Heft-Stil) und ein **Hinweis** festgelegt.`,
    scatterHint: 'Die kleinen Ziffern in den türkisen Marken unten rechts in den Kästchen (1, 2, 3 …) geben die Reihenfolge. Lesen Sie die Buchstaben nacheinander — so ergibt sich das Lösungswort.',
    universalHint: 'Die türkisen Zahlmarken unten rechts in den weißen Kästchen (1, 2, 3 …) gehören zum Lösungswort — nicht die schwarzen Rätselnummern oben links. Tragen Sie die Buchstaben in die Kästchen unter dem Gitter ein. Je mehr Begriffe Sie lösen, desto mehr Buchstaben kennen Sie schon.',
    modeNote: 'Schwedenstil: Knappe Hinweise nur in der Liste unten. Im Gitter wie gewohnt nur Nummern.',
    systemLang: 'deutschsprachige',
  },
  es: {
    name: 'Español', dir: 'ltr',
    acrossLabel: 'Horizontal →', downLabel: 'Vertical ↓',
    solutionWordLabel: 'Palabra Clave',
    charRule: '- "word": 4–9 letras, solo A–Z sin tildes (normaliza á→A, é→E, ñ→N, etc.)\n- "clue": una frase breve y amable en español\n- Prefiere letras frecuentes: A, E, I, O, S, N, R, L',
    lwCharRule: '5–8 letras, palabra española, solo A–Z sin tildes',
    lwCharRuleAfter: '4–9 letras, solo A–Z sin tildes ni diacríticos',
    generateIntro: (wc) => `Crea exactamente ${wc} palabras en español, cada una con una pista corta para un crucigrama. TODAS las palabras y pistas DEBEN estar en español.`,
    lwIntro: (wc) => `Para un crucigrama de unos ${wc} palabras, determina primero la PALABRA CLAVE y una pista. TODA la respuesta debe estar en español.`,
    scatterHint: 'Los pequeños números en las casillas indican el orden. Lee las letras en ese orden para encontrar la palabra clave.',
    universalHint: 'Los números en turquesa en las casillas (1, 2, 3 …) pertenecen a la palabra clave. Escribe esas letras en las casillas de abajo. Cuantas más palabras resuelvas, más letras conocerás.',
    modeNote: 'Estilo sueco: Pistas breves solo en la lista inferior. Solo números en el tablero.',
    systemLang: 'en español',
  },
  it: {
    name: 'Italiano', dir: 'ltr',
    acrossLabel: 'Orizzontale →', downLabel: 'Verticale ↓',
    solutionWordLabel: 'Parola Chiave',
    charRule: '- "word": 4–9 lettere, solo A–Z senza accenti (normalizza à→A, è→E, ecc.)\n- "clue": una breve frase in italiano\n- Preferisci lettere frequenti: A, E, I, O, N, R, S, L',
    lwCharRule: '5–8 lettere, parola italiana, solo A–Z senza accenti',
    lwCharRuleAfter: '4–9 lettere, solo A–Z senza accenti',
    generateIntro: (wc) => `Crea esattamente ${wc} parole in italiano, ognuna con un breve indizio per un cruciverba. TUTTE le parole e gli indizi DEVONO essere in italiano.`,
    lwIntro: (wc) => `Per un cruciverba di circa ${wc} parole, determina prima la PAROLA CHIAVE e un indizio. TUTTA la risposta deve essere in italiano.`,
    scatterHint: 'I piccoli numeri nelle caselle indicano l\'ordine. Leggi le lettere in quell\'ordine per trovare la parola chiave.',
    universalHint: 'I numeri in turchese nelle caselle (1, 2, 3 …) appartengono alla parola chiave. Scrivi quelle lettere nelle caselle in basso. Più parole risolvi, più lettere conoscerai.',
    modeNote: 'Stile svedese: Suggerimenti brevi solo nell\'elenco in basso. Solo numeri nel tabellone.',
    systemLang: 'in italiano',
  },
  tr: {
    name: 'Türkçe', dir: 'ltr',
    acrossLabel: 'Yatay →', downLabel: 'Dikey ↓',
    solutionWordLabel: 'Çözüm Kelimesi',
    charRule: '- "word": 4–9 harf, büyük Türkçe harfler (Ç, Ğ, İ, Ö, Ş, Ü dahil)\n- "clue": kısa, nazik bir Türkçe cümle\n- Sık kullanılan harfleri tercih et: A, E, İ, K, L, N, R, S',
    lwCharRule: '5–8 harf, Türkçe kelime, büyük harfler (Ç, Ğ, İ, Ö, Ş, Ü dahil)',
    lwCharRuleAfter: '4–9 harf, büyük Türkçe harfler',
    generateIntro: (wc) => `Tam olarak ${wc} Türkçe kelime oluştur, her biri bir bulmaca için kısa bir ipucu ile. TÜM kelimeler ve ipuçları Türkçe OLMALIDIR.`,
    lwIntro: (wc) => `Yaklaşık ${wc} kelimeli bir bulmaca için önce ÇÖZÜM KELİMESİ ve bir ipucu belirle. Tüm yanıt Türkçe olmalı.`,
    scatterHint: 'Kutucuklardaki küçük rakamlar sırayı gösterir. Harfleri o sırayla okuyarak çözüm kelimesini bulun.',
    universalHint: 'Kutucuklardaki turkuaz rakamlar (1, 2, 3 …) çözüm kelimesine aittir. O harfleri aşağıdaki kutucuklara yazın. Ne kadar çok kelime çözerseniz o kadar çok harf bilirsiniz.',
    modeNote: 'İsveç stili: Kısa ipuçları yalnızca aşağıdaki listede. Tabloda yalnızca numaralar.',
    systemLang: 'Türkçe',
  },
  ru: {
    name: 'Русский', dir: 'ltr',
    acrossLabel: 'По горизонтали →', downLabel: 'По вертикали ↓',
    solutionWordLabel: 'Ключевое слово',
    charRule: '- "word": 4–9 букв, только кириллица ЗАГЛАВНЫМИ (А–Я, Ё)\n- "clue": короткое дружелюбное предложение на русском языке\n- Предпочитай частые буквы: А, Е, И, Н, О, Р, С, Т',
    lwCharRule: '5–8 букв, русское слово, только кириллица заглавными (А–Я, Ё)',
    lwCharRuleAfter: '4–9 букв, только кириллица заглавными буквами',
    generateIntro: (wc) => `Создай ровно ${wc} русских слов, каждое с коротким подсказом для кроссворда. ВСЕ слова и подсказы ДОЛЖНЫ быть на русском языке (кириллица).`,
    lwIntro: (wc) => `Для кроссворда примерно из ${wc} слов сначала определи КЛЮЧЕВОЕ СЛОВО и подсказ. Весь ответ должен быть на русском языке.`,
    scatterHint: 'Маленькие цифры в клетках указывают порядок. Читайте буквы в этом порядке, чтобы найти ключевое слово.',
    universalHint: 'Бирюзовые цифры в клетках (1, 2, 3 …) относятся к ключевому слову. Впишите эти буквы в клетки ниже. Чем больше слов вы разгадаете, тем больше букв узнаете.',
    modeNote: 'Шведский стиль: Краткие подсказки только в списке ниже. В сетке только цифры.',
    systemLang: 'на русском языке',
  },
  el: {
    name: 'Ελληνικά', dir: 'ltr',
    acrossLabel: 'Οριζόντια →', downLabel: 'Κάθετα ↓',
    solutionWordLabel: 'Λέξη-Κλειδί',
    charRule: '- "word": 4–9 γράμματα, μόνο ελληνικά ΚΕΦΑΛΑΙΑ χωρίς τόνους\n- "clue": μια σύντομη φιλική πρόταση στα ελληνικά\n- Προτίμησε συχνά γράμματα: Α, Ε, Η, Ι, Κ, Ν, Ο, Σ, Τ',
    lwCharRule: '5–8 γράμματα, ελληνική λέξη, κεφαλαία χωρίς τόνους',
    lwCharRuleAfter: '4–9 γράμματα, μόνο ελληνικά κεφαλαία χωρίς τόνους',
    generateIntro: (wc) => `Δημιούργησε ακριβώς ${wc} ελληνικές λέξεις, η καθεμία με μια σύντομη υπόδειξη για σταυρόλεξο. ΟΛΕΣ οι λέξεις και οι υποδείξεις ΠΡΕΠΕΙ να είναι στα ελληνικά.`,
    lwIntro: (wc) => `Για ένα σταυρόλεξο με περίπου ${wc} λέξεις, καθόρισε πρώτα τη ΛΕΞΗ-ΚΛΕΙΔΙ και μια υπόδειξη. Η απάντηση πρέπει να είναι στα ελληνικά.`,
    scatterHint: 'Τα μικρά νούμερα στα κελιά δείχνουν τη σειρά. Διαβάστε τα γράμματα με αυτή τη σειρά για να βρείτε τη λέξη-κλειδί.',
    universalHint: 'Τα τιρκουάζ νούμερα στα κελιά (1, 2, 3 …) ανήκουν στη λέξη-κλειδί. Γράψτε τα γράμματα στα κελιά παρακάτω. Όσο περισσότερες λέξεις λύνετε, τόσο περισσότερα γράμματα γνωρίζετε.',
    modeNote: 'Σουηδικό στυλ: Σύντομες ενδείξεις μόνο στη λίστα παρακάτω. Στο πλέγμα μόνο αριθμοί.',
    systemLang: 'στα ελληνικά',
  },
  ar: {
    name: 'العربية', dir: 'rtl',
    acrossLabel: '← أفقي', downLabel: '↓ عمودي',
    solutionWordLabel: 'كلمة الحل',
    charRule: '- "word": 4–9 حروف، حروف عربية فقط بدون تشكيل (حركات)، اكتبها باللغة العربية\n- "clue": جملة قصيرة وودية باللغة العربية\n- فضل الحروف الشائعة: ا، ل، م، ن، و، ي، ه، ع',
    lwCharRule: '5–8 حروف، كلمة عربية بدون تشكيل',
    lwCharRuleAfter: '4–9 حروف، حروف عربية فقط بدون تشكيل',
    generateIntro: (wc) => `أنشئ بالضبط ${wc} كلمة عربية، كل منها مع تلميح قصير لكلمة متقاطعة. يجب أن تكون جميع الكلمات والتلميحات باللغة العربية بدون تشكيل.`,
    lwIntro: (wc) => `لكلمة متقاطعة من حوالي ${wc} كلمة، حدد أولاً كلمة الحل وتلميحاً لها. يجب أن تكون جميع الإجابات باللغة العربية.`,
    scatterHint: 'الأرقام الصغيرة في الخلايا تشير إلى الترتيب. اقرأ الحروف بهذا الترتيب للحصول على كلمة الحل.',
    universalHint: 'الأرقام الفيروزية في الخلايا (١، ٢، ٣ …) تنتمي إلى كلمة الحل. اكتب تلك الحروف في الخلايا أدناه.',
    modeNote: 'الأسلوب السويدي: تلميحات مختصرة في القائمة أدناه فقط. في الشبكة أرقام فقط.',
    systemLang: 'باللغة العربية',
  },
};

function getLang(settings) {
  const lang = String(settings.language || 'de');
  return LANG_CONFIG[lang] ? lang : 'de';
}

function getLangConfig(settings) {
  return LANG_CONFIG[getLang(settings)] || LANG_CONFIG.de;
}

// ---------------------------------------------------------------------------
// Anthropic: only ask for words + clues, no grid layout
// ---------------------------------------------------------------------------

function getSystemPrompt(lang = 'de') {
  const cfg = LANG_CONFIG[lang] || LANG_CONFIG.de;
  return `You are a compassionate crossword puzzle editor creating puzzles ${cfg.systemLang} for elderly people (seniors).
You return EXCLUSIVELY valid JSON – no additional text, no Markdown code blocks.
When personal family or life information is provided, it is binding and must not be ignored.
When a health or support context is given, it is binding – formulate respectfully and without stigma.
The chosen difficulty level is binding for word and clue selection.`;
}

function healthProfileBlock(code) {
  const c = String(code || 'none');
  const blocks = {
    none: '',
    demenz: `
=== UNTERSTÜTZUNGSKONTEXT: DEMENZ / LEICHTE KOGNITIVE BEEINTRÄCHTIGUNG (verbindlich) ===
Die Rätselperson lebt mit Demenz oder vergleichbar eingeschränkter Merkfähigkeit. Wählen Sie durchweg **sehr vertraute, konkrete** Alltagswörter (Gegenstände, Natur, Essen, einfache Berufe). Hinweise: **kurz, eindeutig**, ein Bild im Kopf (z. B. „Rot und sauer, wächst am Busch“ statt literarischer Umschreibung). Keine Trickfragen, keine Doppelbedeutungen, kein Zeitdruck im Text. Positiver, würdevoller Ton.
`,
    depression: `
=== KONTEXT: STIMMUNG, ANTREIB, KONZENTRATION (verbindlich) ===
Energie und Konzentration können geschwächt sein. **Kurze, freundliche** Hinweise ohne Eile oder Leistungsdruck. Bekannte Wörter; vermeiden Sie karge oder tadelnde Formulierungen. Kleine Freuden und Gewohnheiten (Natur, Tee, Musik) sind willkommen.
`,
    schlaganfall: `
=== KONTEXT: Z. B. NACH SCHLAGANFALL — SPRACHE / LESEN (verbindlich) ===
Bevorzugen Sie **kurze, häufige** Wörter (4–6 Buchstaben wo möglich). Einfache Satzstruktur in den Hinweisen. Keine verschachtelten Sätze. Ein klares Lesebild pro Hinweis.
`,
    parkinson: `
=== KONTEXT: MOTORIK / FEINMOTORIK (verbindlich) ===
Begriffe sollen **leicht zu erkennen** sein; Hinweise klar gegliedert (gern mit Komma kurz teilen, nicht ein endloser Satz). Keine winzig-komplexen Rätselhinweise — Klarheit vor Kürze.
`,
    sehschwaeche: `
=== KONTEXT: SEHSCHWÄCHE (verbindlich) ===
Hinweistexte: **sachlich und klar**, ohne auf kleine Unterscheidungen im Schriftbild anzuspielen (keine „erkennen Sie den Unterschied“-Aufgaben). Gut unterscheidbare Alltagsbegriffe.
`,
    angst: `
=== KONTEXT: ÄNGSTLICHKEIT / UNRUHE (verbindlich) ===
Ruhige, **vorhersehbare** Formulierungen. Keine Schreck-, Druck- oder Konflikt-Themen in Hinweisen. Vertraute, behagliche Bilder.
`,
  };
  return blocks[c] || '';
}

function difficultyBlock(code) {
  const c = String(code || 'leicht');
  const blocks = {
    sehr_leicht: `
SCHWIERIGKEIT: **SEHR LEICHT** (verbindlich):
- Vorzugsweise Lösungswörter mit **4–6 Buchstaben**, alltäglich und konkret.
- Hinweise: **ein kurzer Satz**, direkt auf den Begriff zielend, ohne Umweg.
- Keine seltenen Fremdwörter, keine Kulturhistorie-Spezialisten-Namen.
`,
    leicht: `
SCHWIERIGKEIT: **LEICHT** (verbindlich):
- Lösungswörter **4–8 Buchstaben**, überwiegend alltagsnah.
- Hinweise: klar und freundlich; leichte Umschreibung erlaubt, aber immer **lösbar ohne Rätseltricks**.
`,
    mittel: `
SCHWIERIGKEIT: **MITTEL** (für noch fittere Seniorinnen — verbindlich):
- Lösungswörter bis **9 Buchstaben** möglich; gelegentlich etwas **weniger häufig**, aber nie hochakademisch.
- Hinweise dürfen **einen kleinen Denkschritt** verlangen, müssen aber fair und ohne List bleiben. Weiterhin respektvoll und positiv.
`,
  };
  return blocks[c] || blocks.leicht;
}

const HEALTH_PROFILE_KEYS = new Set([
  'none', 'demenz', 'depression', 'schlaganfall', 'parkinson', 'sehschwaeche', 'angst',
]);

const DIFFICULTY_KEYS = new Set(['sehr_leicht', 'leicht', 'mittel']);

function normalizeHealthProfile(code) {
  const c = String(code || 'none');
  return HEALTH_PROFILE_KEYS.has(c) ? c : 'none';
}

function normalizeDifficulty(code) {
  const c = String(code || 'leicht');
  return DIFFICULTY_KEYS.has(c) ? c : 'leicht';
}

function puzzleTypePromptExtra(puzzleType) {
  if (puzzleType === 'schweden') {
    return `
RÄTSELART „Schwedenstil“ (kompakte Hinweise **nur in der nummerierten Liste** unter dem Gitter):
- Jeder Hinweis („clue“) maximal etwa 50 Zeichen, lieber kürzer; trotzdem für Seniorinnen verständlich.
- Im Gitter erscheinen wie üblich **nur Nummern** an Wortanfängen, keine Hinweistexte in den weißen Feldern.
`;
  }
  if (puzzleType === 'gitter') {
    return `
RÄTSELART „Gitter ohne Nummern im Gitter“:
- Nur die Hinweisliste enthält die Nummern; sie dürfen etwas ausführlicher und liebevoller sein als beim Schwedenstil.
`;
  }
  return `
RÄTSELART „Standard-Kreuzworträtsel“:
- Hinweise als kurze, klare Sätze; bei persönlichen Themen gern etwas ausführlicher.
`;
}

// Build prompt from user settings (general by default; personal story overrides when enabled)
function buildUserPrompt(settings = {}) {
  const lang = getLang(settings);
  const langCfg = LANG_CONFIG[lang];
  const healthProfile = normalizeHealthProfile(settings.healthProfile);
  const difficulty    = normalizeDifficulty(settings.difficulty);
  const puzzleType = settings.puzzleType === 'schweden' ? 'schweden' : 'schweden';
  const name = (settings.name || '').trim();
  const nameLine = name
    ? `Optionaler Vorname für warme, sparsame Ansprache in einzelnen Hinweisen: ${name}`
    : 'Kein Vorname angegeben – formulieren Sie die Hinweise allgemein herzlich (ohne fiktiven Namen).';

  const wcRaw = parseInt(settings.wordCount, 10);
  const wordCount = [16, 20, 24, 28, 32].includes(wcRaw) ? wcRaw : 24;

  const topics = Array.isArray(settings.topics) && settings.topics.length
    ? settings.topics
    : ['Natur & Jahreszeiten', 'Alltag & Zuhause', 'Einfache Freizeit', 'Essen & Trinken'];

  const customContext = (settings.customContext || '').trim();
  const familyStory   = (settings.familyStory || '').trim();
  const useFamily     = settings.useFamilyStory !== false && familyStory.length > 0;

  const topicBlock = topics.map(t => `• ${t}`).join('\n');

  const plannedLw = settings.plannedLoesungswort
    ? normalise(settings.plannedLoesungswort, lang)
    : '';
  const loesungBlock = plannedLw
    ? `
=== FIXES LÖSUNGSWORT (verbindlich) ===
Das **Lösungswort** für das Rätselheft ist bereits festgelegt:

„${plannedLw}“ (${plannedLw.length} Buchstaben)

- **Jeder** dieser Buchstaben muss irgendwo in mindestens einem Ihrer ${wordCount} „word“-Einträge **als Buchstabe vorkommen** (egal an welcher Position).
- Kommt derselbe Buchstabe im Lösungswort mehrfach vor, muss er **mindestens so oft** über **alle** „word“-Strings zusammengezählt vorkommen.
- **Wichtig:** Planen Sie so, dass die Buchstaben **nicht alle aus einem einzigen** Ihrer Listeneinträge stammen — streuen Sie sie über **viele verschiedene** Begriffe (sonst liegen alle Markierungen später in einer Geraden im Gitter).
=== Ende Lösungswort-Vorgabe ===
`
    : '';

  // Scale personal/general split proportionally to wordCount
  const personalMin = Math.round(wordCount * 0.42);
  const generalMin  = Math.round(wordCount * 0.33);
  const freeMax     = wordCount - personalMin - generalMin;

  let personalBlock = '';
  if (useFamily) {
    personalBlock = `
=== PERSÖNLICHE FAMILIEN- UND LEBENSINFORMATIONEN (verbindlich) ===
Der Ersteller hat folgende Angaben gemacht. Sie MÜSSEN diese ernst nehmen.

Leiten Sie Begriffe u. a. ab aus: Vornamen, Orten, Berufen, Beziehungen (Ehepartner, Kinder, Enkel), Schulen, Ländern oder Reisen – genau wie beschrieben, ohne Personen wegzulassen. Wenn ein Name kürzer als 4 Buchstaben ist, verwenden Sie stattdessen einen klaren verwandten Begriff aus dem Kontext (z. B. Stadt, Beruf, ‚LEHRER‘, ‚FAMILIE‘) oder einen passenden längeren Namen aus dem Text.

Hinweise: liebevoll, konkret, leicht verständlich für die gewählte Zielgruppe – keine Rätsel um Trauer, aber Ehepartner und Kinder respektvoll nennen dürfen (z. B. „Er war Lehrer für Englisch“, „Tochter unterrichtete Deutsch“).

${familyStory}
${customContext ? `\nZusätzliche Wünsche vom Ersteller: ${customContext}\n` : ''}
=== Ende der persönlichen Informationen ===

MISCHUNG MIT ALLGEMEINEN BEGRIFFEN (verbindliche Aufteilung der ${wordCount} Wörter):
- Mindestens ${personalMin} Lösungswörter inkl. Hinweise sollen sich eindeutig auf die persönlichen Informationen beziehen (Familie, Orte, Berufe, Beziehungen).
- Mindestens ${generalMin} Lösungswörter sollen klassische, leichte Allgemeinbegriffe sein, passend zu diesen Themen (nicht aus dem Familientext „abfischbar“ – echte allgemeine Begriffe wie Blume, Bach, Brot, Walzer, Sonne je nach Thema):
${topicBlock}
- Die restlichen bis ${freeMax} Einträge frei wählen (persönlich oder allgemein), damit das Gitter gut vernetzbar bleibt.
Ziel: ein ausgewogenes Rätsel – nicht nur Familie, sondern auch vertraute, „normale“ Kreuzwort-Begriffe aus den gewählten Themen.
`;
  }

  const generalBlock = useFamily
    ? ''
    : `
Allgemeine Themenschwerpunkte (gut mischen, einfache Begriffe):
${topicBlock}
${customContext ? `\nZusätzliche Wünsche vom Ersteller: ${customContext}\n` : ''}`;

  const audienceLine = healthProfile === 'none'
    ? 'Zielgruppe (ohne speziellen Gesundheitsfokus): ältere Menschen; oft mit leichter kognitiver Einschränkung (z. B. Demenz). Wortwahl und Hinweise müssen zur gewählten **Schwierigkeit** passen.'
    : 'Zielgruppe und sprachliche Leitplanken: im **Gesundheits-/Unterstützungskontext** oben beschrieben (verbindlich). Wortlänge und Alltagsnähe **zusätzlich** strikt gemäß **Schwierigkeit**.';

  // Word history block — avoid repetition across sessions
  const wordHistory = settings.wordHistory;
  let wordHistoryBlock = '';
  if (wordHistory && typeof wordHistory === 'object' && Object.keys(wordHistory).length > 0) {
    const entries = Object.entries(wordHistory)
      .filter(([w, c]) => w.length >= 4 && typeof c === 'number' && c >= 1)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 80);
    if (entries.length > 0) {
      const wordList = entries.map(([w, c]) => c > 1 ? `${w} (${c}\u00d7)` : w).join(', ');
      wordHistoryBlock = `
=== WORTGEDÄCHTNIS — BEREITS VERWENDETE WÖRTER (verbindlich) ===
Die folgenden Lösungswörter wurden in früheren Rätseln für diese Person bereits benutzt. **Verwende sie nicht noch einmal** — wähle stets frische, andere Begriffe, um Wiederholungen über mehrere Rätsel hinweg zu vermeiden. Besonders häufig verwendete Wörter (hohe Zahl) sind besonders wichtig zu meiden.

${wordList}
=== Ende Wortgedächtnis ===
`;
    }
  }

  return `${langCfg.generateIntro(wordCount)}

${difficultyBlock(difficulty)}${healthProfileBlock(healthProfile)}
${audienceLine} Ton: warm, würdevoll, positiv, sehr klar. Keine ironischen Texte, keine unnötig schweren Begriffe.

${nameLine}
${name ? `Als JSON-"title": herzlicher Titel (Versalien) in ${langCfg.name}.` : `Als JSON-"title": kurzer herzlicher Titel (Versalien, Magazinstil) in ${langCfg.name}.`}
${loesungBlock}${wordHistoryBlock}${personalBlock}${generalBlock}
${puzzleTypePromptExtra(puzzleType)}

Technische Regeln:
${langCfg.charRule}

Antwortformat (NUR dieses JSON, exakt ${wordCount} Objekte in "words"):
{
  "title": "Kurzer herzlicher Titel",
  "words": [
    { "word": "BEISPIEL", "clue": "Kurzer Hinweis" }
  ]
}

${plannedLw ? 'Liefern Sie **kein** Lösungswort-Feld in JSON — es ist oben bereits festgelegt.' : 'Kein Lösungswort in dieser Antwort — es wird separat festgelegt.'}`;
}

// ---------------------------------------------------------------------------
// Word normaliser (language-aware)
// ---------------------------------------------------------------------------

function normalise(word, lang = 'de') {
  if (lang === 'es' || lang === 'it') {
    return String(word).toUpperCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^A-Z]/g, '');
  }
  if (lang === 'tr') {
    return String(word).toLocaleUpperCase('tr')
      .replace(/\u0131/g, 'I')   // dotless ı → I
      .replace(/İ/g, 'I')        // dotted İ → I (uniform)
      .replace(/[^A-ZÇĞÖŞÜ]/g, '');
  }
  if (lang === 'ru') {
    return String(word).toUpperCase().replace(/[^А-ЯЁ]/g, '');
  }
  if (lang === 'el') {
    return String(word).toUpperCase()
      .normalize('NFD').replace(/[\u0300-\u036f\u0384\u0385]/g, '')
      .replace(/[^ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ]/g, '');
  }
  if (lang === 'ar') {
    return String(word)
      .replace(/[\u064B-\u065F\u0670\u0671]/g, '')  // strip harakat/wasla
      .replace(/[آأإٱ]/g, 'ا')   // normalize alef variants
      .replace(/ة/g, 'ت')         // ta marbuta → ta
      .replace(/ى/g, 'ي')         // alef maqsura → ya
      .replace(/[^\u0621-\u063A\u0641-\u064A]/g, ''); // keep basic Arabic
  }
  // de (default)
  return String(word).toUpperCase()
    .replace(/Ä/g, 'AE').replace(/Ö/g, 'OE').replace(/Ü/g, 'UE')
    .replace(/ß/g, 'SS').replace(/[^A-Z]/g, '');
}

function stripJson(text) {
  return text.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
}

/** Phase 0: Lösungswort + Hinweis vor der Wortliste (damit Buchstaben gezielt in Begriffe einbaubar sind). */
/** Phase 0: Lösungswort + Hinweis vor der Wortliste (damit Buchstaben gezielt in Begriffe einbaubar sind). */
async function fetchLoesungswortPlan(settings, wordCount) {
  const lang = getLang(settings);
  const langCfg = LANG_CONFIG[lang];
  const name = (settings.name || '').trim();
  const topics = Array.isArray(settings.topics) && settings.topics.length
    ? settings.topics
    : ['Alltag', 'Familie', 'Natur'];
  const familyStory = (settings.familyStory || '').trim().slice(0, 650);
  const customContext = (settings.customContext || '').trim().slice(0, 450);
  const ctx = [
    name && `Vorname (wenn passend): ${name}`,
    `Themen: ${topics.join(', ')}`,
    familyStory && `Lebens-/Familienkontext: ${familyStory}`,
    customContext && `Zusätzliche Wünsche: ${customContext}`,
  ]
    .filter(Boolean)
    .join('\n');

  // Build avoidance list from word history so Lösungswort is also varied
  const wordHistory = settings.wordHistory;
  let avoidBlock = '';
  if (wordHistory && typeof wordHistory === 'object') {
    const usedLw = Object.entries(wordHistory)
      .filter(([w, c]) => w.length >= 4 && typeof c === 'number' && c >= 1)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 40)
      .map(([w]) => w);
    if (usedLw.length > 0) {
      avoidBlock = `
Bereits verwendete Lösungswörter (NICHT nochmals wählen): ${usedLw.join(', ')}
`;
    }
  }

  const userContent = `${langCfg.lwIntro(wordCount)}

${ctx}
${avoidBlock}
Antworten Sie mit **NUR** diesem JSON:
{"loesungswort":"WORT","loesungswort_hinweis":"Kurzer Satz ohne das Wort wörtlich zu nennen"}

Regeln:
- loesungswort: ${langCfg.lwCharRule}
- **Abwechslungsreich und überraschend** — wähle ein frisches, unerwartetes Wort aus dem Themenkontext.
- emotional passend zum Kontext, aber frisch und unerwartet
- loesungswort_hinweis: ein liebevoller Satz in ${langCfg.name}, ohne das Wort direkt zu nennen`;

  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const msg = await client.messages.create({
        model: 'claude-opus-4-5',
        max_tokens: 400,
        system: getSystemPrompt(lang),
        messages: [{ role: 'user', content: userContent }],
      });
      const data = JSON.parse(stripJson(msg.content[0].text));
      const lw = data.loesungswort != null ? normalise(data.loesungswort, lang) : '';
      const hint = String(data.loesungswort_hinweis || data.loesungswortHinweis || '').trim();
      if (lw.length >= 4 && lw.length <= 10 && hint) {
        return { loesungswort: lw, loesungswortHinweis: hint };
      }
    } catch (e) {
      console.warn('Lösungswort Plan attempt', attempt, e.message);
    }
  }
  return null;
}

function lettersCoveredByWords(wordObjects, lw, lang = 'de') {
  const lwNorm = normalise(lw, lang);
  if (!lwNorm) return false;
  const need = {};
  for (const ch of lwNorm) need[ch] = (need[ch] || 0) + 1;
  const have = {};
  for (const w of wordObjects) {
    for (const ch of w.word) have[ch] = (have[ch] || 0) + 1;
  }
  for (const ch of Object.keys(need)) {
    if ((have[ch] || 0) < need[ch]) return false;
  }
  return true;
}

function shuffleInPlace(a) {
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/** Alle markierten Zellen in einer Geraden (eine waagerechte oder senkrechte Linie ohne Lücke) — typisch ein Listenwort. */
function isSingleContiguousStraightLine(points) {
  if (points.length <= 1) return false;
  const rs = points.map(p => p.r);
  const cs = points.map(p => p.c);
  if (new Set(rs).size === 1) {
    const sorted = [...cs].sort((a, b) => a - b);
    const min = sorted[0];
    for (let i = 0; i < sorted.length; i++) {
      if (sorted[i] !== min + i) return false;
    }
    return true;
  }
  if (new Set(cs).size === 1) {
    const sorted = [...rs].sort((a, b) => a - b);
    const min = sorted[0];
    for (let i = 0; i < sorted.length; i++) {
      if (sorted[i] !== min + i) return false;
    }
    return true;
  }
  return false;
}

function manhattanPairSum(points) {
  let s = 0;
  for (let i = 0; i < points.length; i++) {
    for (let j = i + 1; j < points.length; j++) {
      s += Math.abs(points[i].r - points[j].r) + Math.abs(points[i].c - points[j].c);
    }
  }
  return s;
}

/**
 * Wählt pro Lösungswort-Buchstaben eine Gitterzelle (aus den gelegten Wörtern), eindeutige Zellen,
 * bevorzugt **Streuung**; verbietet „alles in einer durchgehenden Linie“.
 */
function pickScatteredLoesungswortQuelle(placedWords, lwRaw, lang = 'de') {
  const lw = normalise(lwRaw, lang);
  if (!lw || !placedWords.length) return null;

  const byK = [];
  for (let k = 0; k < lw.length; k++) {
    const ch = lw[k];
    const opts = [];
    for (const entry of placedWords) {
      for (let idx = 0; idx < entry.word.length; idx++) {
        if (entry.word[idx] === ch) {
          const r = entry.direction === 'down' ? entry.row + idx : entry.row;
          const c = entry.direction === 'across' ? entry.col + idx : entry.col;
          opts.push({ entry, idx, r, c });
        }
      }
    }
    if (!opts.length) return null;
    byK.push(opts);
  }

  const REST = 500;
  const REST_RELAX = 300;
  let best = null;
  let bestScore = -1e9;

  function tryAssign(requireSpread) {
    const order = lw.split('').map((_, i) => i);
    shuffleInPlace(order);
    const picked = new Array(lw.length);
    const used = new Set();
    let failed = false;
    for (const k of order) {
      const opts = [...byK[k]];
      shuffleInPlace(opts);
      let choice = null;
      for (const o of opts) {
        const key = `${o.r},${o.c}`;
        if (!used.has(key)) {
          choice = o;
          break;
        }
      }
      if (!choice) {
        failed = true;
        break;
      }
      picked[k] = choice;
      used.add(`${choice.r},${choice.c}`);
    }
    if (failed) return;
    const pts = picked.map(p => ({ r: p.r, c: p.c }));
    if (requireSpread && isSingleContiguousStraightLine(pts)) return;
    const rowU = new Set(pts.map(p => p.r)).size;
    const colU = new Set(pts.map(p => p.c)).size;
    const wordU = new Set(picked.map(p => p.entry.word)).size;
    const dist = manhattanPairSum(pts);
    const score = dist + 10 * rowU * colU + 4 * wordU;
    if (score > bestScore) {
      bestScore = score;
      best = picked;
    }
  }

  for (let i = 0; i < REST; i++) tryAssign(true);
  if (!best) {
    for (let i = 0; i < REST_RELAX; i++) tryAssign(false);
  }
  if (!best) return null;

  return best.map(p => ({
    word: p.entry.word,
    buchstabe_index: p.idx,
  }));
}

function buildLoesungswortQuelleOnlyPrompt(placedWords, fixedLoesungswort, title, settings = {}) {
  const lang = getLang(settings);
  const langCfg = LANG_CONFIG[lang];
  const rows = [...placedWords]
    .sort((a, b) =>
      a.number !== b.number ? a.number - b.number : String(a.direction).localeCompare(String(b.direction)))
    .map(w => ({
      number: w.number,
      direction: w.direction,
      word: w.word,
      clue: String(w.clue || '').slice(0, 160),
    }));

  const lw = normalise(fixedLoesungswort, lang);
  return `Das Kreuzworträtsel ist gelegt. Titel: ${title || 'Rätsel'}

${puzzleTypePromptExtra('schweden')}

**Lösungswort ist fest:** „${lw}" (${lw.length} Buchstaben in dieser exakten Reihenfolge).

=== EINTRÄGE (nur diese "word"-Strings verwenden) ===
${JSON.stringify(rows, null, 2)}

Geben Sie **nur** "loesungswort_quelle" zurück — ein JSON-Array mit **${lw.length}** Objekten. Jedes Objekt: { "word": "...", "buchstabe_index": n }
- Position k im Lösungswort (0-basiert) = Buchstabe lw[k]; "word"+"buchstabe_index" muss diesen Buchstaben liefern.
- **Keine Gitterzelle doppelt.**
- **Streuen Sie stark:** nutzen Sie **viele verschiedene** Wörter; **vermeiden Sie**, dass alle Indices aus **einem einzigen** waagerechten oder senkrechten Wort stammen (keine durchgehende Linie im Gitter).

Antwort: **NUR** JSON dieser Form:
{"loesungswort_quelle":[{ "word": "X", "buchstabe_index": 0 }]}
(Array-Länge exakt ${lw.length})`;
}

async function fetchLoesungswortQuelleOnlyAfterPlacement(placedWords, fixedLw, title, settings) {
  const lang = getLang(settings);
  if (!placedWords.length || !fixedLw) return null;
  const userContent = buildLoesungswortQuelleOnlyPrompt(placedWords, fixedLw, title, settings);
  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const msg = await client.messages.create({
        model: 'claude-opus-4-5',
        max_tokens: 2000,
        system: getSystemPrompt(lang),
        messages: [{ role: 'user', content: userContent }],
      });
      const data = JSON.parse(stripJson(msg.content[0].text));
      const quelle = data.loesungswort_quelle || data.loesungswortQuelle || null;
      const lw = normalise(fixedLw, lang);
      if (Array.isArray(quelle) && quelle.length === lw.length) {
        return { quelle };
      }
    } catch (e) {
      console.warn('Lösungswort Quelle-only attempt', attempt, e.message);
    }
  }
  return null;
}

function buildLoesungswortAfterPlacementPrompt(placedWords, title, settings = {}) {
  const lang = getLang(settings);
  const langCfg = LANG_CONFIG[lang];
  const rows = [...placedWords]
    .sort((a, b) =>
      a.number !== b.number ? a.number - b.number : String(a.direction).localeCompare(String(b.direction)))
    .map(w => ({
      number: w.number,
      direction: w.direction,
      word: w.word,
      clue: String(w.clue || '').slice(0, 200),
    }));

  const name = (settings.name || '').trim();
  const dedication = name ? `Beziehen Sie den Vornamen „${name}" ein, wenn es zum Thema passt.` : '';

  return `Das Kreuzworträtsel ist **fertig gelegt**. Sie kennen **exakt** alle Lösungswörter, **Rätselnummer**, **Richtung** (across = waagerecht, down = senkrecht) und **Hinweis**.

Titel: ${title || 'Rätsel'}
${dedication}

${puzzleTypePromptExtra('schweden')}

=== ALLE EINTRÄGE (nur diese "word"-Strings in "loesungswort_quelle" verwenden) ===
${JSON.stringify(rows, null, 2)}
=== Ende Liste ===

Erzeugen Sie ein **Lösungswort** im Rätselheft-Stil in **${langCfg.name}**: thematisch passend. Die Spielerinnen lesen die Buchstaben später aus **gekennzeichneten** Gitterfeldern — ordnen Sie jedem Buchstaben des Lösungsworts **einen** Buchstaben **eines** der Listeneinträge zu.

Antworten Sie mit **NUR** diesem JSON (kein Markdown, keine Erklärung):
{
  "loesungswort": "BEISPIEL",
  "loesungswort_hinweis": "Ein kurzer liebevoller Satz in ${langCfg.name} – ohne das Lösungswort wörtlich zu nennen.",
  "loesungswort_quelle": [
    { "word": "EINTAG", "buchstabe_index": 0 }
  ]
}

Regeln:
- "loesungswort": ${langCfg.lwCharRuleAfter}
- "loesungswort_quelle": genau so viele Objekte wie "loesungswort" Buchstaben
- Jedes "word" muss **identisch** zu einem "word" in der Liste oben sein (gleiche Normalisierung)
- "buchstabe_index": **0** = erster Buchstabe dieses Wortes, **1** = zweiter, …; muss zum jeweiligen Buchstaben in "loesungswort" passen
- **Keine Zelle doppelt**: dieselbe physische Gitterposition darf nicht zweimal vorkommen
- Bevorzugen Sie Buchstaben an **Kreuzungen**
- "loesungswort_hinweis": emotional passend in ${langCfg.name}, Lösungswort nicht wörtlich nennen`;
}

async function fetchLoesungswortAfterPlacement(placedWords, title, settings) {
  const lang = getLang(settings);
  if (!placedWords || !placedWords.length) return null;
  const userContent = buildLoesungswortAfterPlacementPrompt(placedWords, title, settings);
  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const msg = await client.messages.create({
        model: 'claude-opus-4-5',
        max_tokens: 1200,
        system: getSystemPrompt(lang),
        messages: [{ role: 'user', content: userContent }],
      });
      const data = JSON.parse(stripJson(msg.content[0].text));
      const lw = data.loesungswort != null ? normalise(data.loesungswort, lang) : '';
      const hint = String(data.loesungswort_hinweis || data.loesungswortHinweis || '').trim();
      const quelle = data.loesungswort_quelle || data.loesungswortQuelle || null;
      if (lw.length >= 4 && lw.length <= 12 && Array.isArray(quelle) && quelle.length === lw.length) {
        return { loesungswort: lw, hint, quelle };
      }
    } catch (e) {
      console.warn('Lösungswort phase-2 attempt', attempt, e.message);
    }
  }
  return null;
}
// ---------------------------------------------------------------------------
// Crossword placement — letter-anchor engine, maximum density
// ---------------------------------------------------------------------------

function placeCrossword(wordObjects) {
  const nWords = wordObjects.length;

  // Tight grid — letter-anchor approach fills it much better than a scan
  const SIZE = nWords <= 16 ? 13 : nWords <= 22 ? 15 : nWords <= 28 ? 16 : 18;

  const sorted = [...wordObjects].sort((a, b) => b.word.length - a.word.length);

  function tryPlacement(ordering) {
    const grid    = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
    const dirGrid = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
    const placed  = [];
    let   totalCrossings = 0;

    // Letter → list of grid cells that carry that letter
    const letterCells = {}; // letter -> [{r,c}]

    function hasLetter(r, c) {
      return r >= 0 && r < SIZE && c >= 0 && c < SIZE && grid[r][c] !== null;
    }

    function evaluatePlace(word, row, col, dir) {
      const endR = dir === 'down'   ? row + word.length - 1 : row;
      const endC = dir === 'across' ? col + word.length - 1 : col;
      if (row < 0 || col < 0 || endR >= SIZE || endC >= SIZE) return -1;

      if (dir === 'across') {
        if (hasLetter(row, col - 1))           return -1;
        if (hasLetter(row, col + word.length))  return -1;
      } else {
        if (hasLetter(row - 1, col))            return -1;
        if (hasLetter(row + word.length, col))  return -1;
      }

      let crossings = 0;
      for (let i = 0; i < word.length; i++) {
        const r = dir === 'down'   ? row + i : row;
        const c = dir === 'across' ? col + i : col;
        const existing = grid[r][c];
        if (existing !== null) {
          if (existing !== word[i]) return -1;
          if (dirGrid[r][c] === dir) return -1;
          crossings++;
        } else {
          if (dir === 'across') {
            if (hasLetter(r - 1, c) || hasLetter(r + 1, c)) return -1;
          } else {
            if (hasLetter(r, c - 1) || hasLetter(r, c + 1)) return -1;
          }
        }
      }
      return crossings; // 0 allowed only for the first word (checked by caller)
    }

    function commitWord(wordObj, row, col, dir, crossings) {
      const { word } = wordObj;
      for (let i = 0; i < word.length; i++) {
        const r = dir === 'down'   ? row + i : row;
        const c = dir === 'across' ? col + i : col;
        grid[r][c] = word[i];
        dirGrid[r][c] = dirGrid[r][c] && dirGrid[r][c] !== dir ? 'both' : dir;
        if (!letterCells[word[i]]) letterCells[word[i]] = [];
        letterCells[word[i]].push({ r, c });
      }
      placed.push({ ...wordObj, row, col, direction: dir });
      totalCrossings += crossings;
    }

    // First word: longest, horizontal through centre
    const first = ordering[0];
    const startCol = Math.max(0, Math.floor((SIZE - first.word.length) / 2));
    commitWord(first, Math.floor(SIZE / 2), startCol, 'across', 0);

    // Remaining words: anchor-based search
    for (let wi = 1; wi < ordering.length; wi++) {
      const wordObj = ordering[wi];
      const word    = wordObj.word;
      let best      = null;
      let bestScore = -Infinity;

      // Build candidate set: only positions where word crosses an existing letter
      // Phase 1: letter-anchor (only positions that guarantee a crossing)
      const seen = new Set();
      for (let wordPos = 0; wordPos < word.length; wordPos++) {
        const ch = word[wordPos];
        const cells = letterCells[ch];
        if (!cells) continue;
        for (const { r: gr, c: gc } of cells) {
          const rowA = gr, colA = gc - wordPos;
          const keyA = `A,${rowA},${colA}`;
          if (!seen.has(keyA)) {
            seen.add(keyA);
            const cr = evaluatePlace(word, rowA, colA, 'across');
            if (cr > 0) {
              const dist  = Math.abs(rowA - SIZE / 2) + Math.abs(colA + word.length / 2 - SIZE / 2);
              const score = cr * cr * 300 - dist * 10;
              if (score > bestScore) { bestScore = score; best = { row: rowA, col: colA, direction: 'across', crossings: cr }; }
            }
          }
          const rowD = gr - wordPos, colD = gc;
          const keyD = `D,${rowD},${colD}`;
          if (!seen.has(keyD)) {
            seen.add(keyD);
            const cr = evaluatePlace(word, rowD, colD, 'down');
            if (cr > 0) {
              const dist  = Math.abs(rowD + word.length / 2 - SIZE / 2) + Math.abs(colD - SIZE / 2);
              const score = cr * cr * 300 - dist * 10;
              if (score > bestScore) { bestScore = score; best = { row: rowD, col: colD, direction: 'down', crossings: cr }; }
            }
          }
        }
      }

      // Phase 2: full grid-scan fallback for words that share no letters yet
      if (!best) {
        for (const dir of ['across', 'down']) {
          for (let r = 0; r < SIZE; r++) {
            for (let c = 0; c < SIZE; c++) {
              const cr = evaluatePlace(word, r, c, dir);
              if (cr <= 0) continue; // still require at least 1 crossing
              const dist  = Math.abs(r - SIZE / 2) + Math.abs(c + (dir==='across'?word.length/2:0) - SIZE / 2);
              const score = cr * cr * 300 - dist * 10;
              if (score > bestScore) { bestScore = score; best = { row: r, col: c, direction: dir, crossings: cr }; }
            }
          }
        }
      }

      if (best) {
        commitWord(wordObj, best.row, best.col, best.direction, best.crossings);
      }
    }
    return { placed, totalCrossings };
  }

  // Dynamic ordering: after each word is fixed, sort remaining by shared-letter count
  // with the already-placed set — most-shareable words come next
  function dynamicOrderPlacement(seedOrdering) {
    const grid    = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
    const dirGrid = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
    const placed  = [];
    let   totalCrossings = 0;
    const letterCells = {};

    function hasLetter(r, c) {
      return r >= 0 && r < SIZE && c >= 0 && c < SIZE && grid[r][c] !== null;
    }
    function evaluatePlace(word, row, col, dir) {
      const endR = dir === 'down'   ? row + word.length - 1 : row;
      const endC = dir === 'across' ? col + word.length - 1 : col;
      if (row < 0 || col < 0 || endR >= SIZE || endC >= SIZE) return -1;
      if (dir === 'across') {
        if (hasLetter(row, col - 1))           return -1;
        if (hasLetter(row, col + word.length))  return -1;
      } else {
        if (hasLetter(row - 1, col))            return -1;
        if (hasLetter(row + word.length, col))  return -1;
      }
      let crossings = 0;
      for (let i = 0; i < word.length; i++) {
        const r = dir === 'down'   ? row + i : row;
        const c = dir === 'across' ? col + i : col;
        const existing = grid[r][c];
        if (existing !== null) {
          if (existing !== word[i]) return -1;
          if (dirGrid[r][c] === dir) return -1;
          crossings++;
        } else {
          if (dir === 'across') {
            if (hasLetter(r - 1, c) || hasLetter(r + 1, c)) return -1;
          } else {
            if (hasLetter(r, c - 1) || hasLetter(r, c + 1)) return -1;
          }
        }
      }
      return crossings;
    }
    function commitWord(wordObj, row, col, dir, crossings) {
      const { word } = wordObj;
      for (let i = 0; i < word.length; i++) {
        const r = dir === 'down'   ? row + i : row;
        const c = dir === 'across' ? col + i : col;
        grid[r][c] = word[i];
        dirGrid[r][c] = dirGrid[r][c] && dirGrid[r][c] !== dir ? 'both' : dir;
        if (!letterCells[word[i]]) letterCells[word[i]] = [];
        letterCells[word[i]].push({ r, c });
      }
      placed.push({ ...wordObj, row, col, direction: dir });
      totalCrossings += crossings;
    }

    // Shared-letter score: how many letters of word appear in current grid
    function shareScore(word) {
      const placedLetterSet = {};
      for (const ch of Object.keys(letterCells)) placedLetterSet[ch] = true;
      return [...word].filter(ch => placedLetterSet[ch]).length;
    }

    // Place first word
    const first = seedOrdering[0];
    const startCol = Math.max(0, Math.floor((SIZE - first.word.length) / 2));
    commitWord(first, Math.floor(SIZE / 2), startCol, 'across', 0);

    // Pool of remaining words (already ordered by length initially)
    let pool = seedOrdering.slice(1);

    while (pool.length > 0) {
      // Re-sort pool: highest share-score first; ties broken by length
      pool.sort((a, b) => {
        const ds = shareScore(b.word) - shareScore(a.word);
        return ds !== 0 ? ds : b.word.length - a.word.length;
      });

      let placed_any = false;
      for (let pi = 0; pi < pool.length; pi++) {
        const wordObj = pool[pi];
        const word    = wordObj.word;
        let best      = null;
        let bestScore = -Infinity;

        const seen = new Set();
        for (let wordPos = 0; wordPos < word.length; wordPos++) {
          const ch = word[wordPos];
          const cells = letterCells[ch];
          if (!cells) continue;
          for (const { r: gr, c: gc } of cells) {
            const rowA = gr, colA = gc - wordPos;
            const keyA = `A,${rowA},${colA}`;
            if (!seen.has(keyA)) {
              seen.add(keyA);
              const cr = evaluatePlace(word, rowA, colA, 'across');
              if (cr > 0) {
                const dist  = Math.abs(rowA - SIZE / 2) + Math.abs(colA + word.length / 2 - SIZE / 2);
                const score = cr * cr * 300 - dist * 10;
                if (score > bestScore) { bestScore = score; best = { row: rowA, col: colA, direction: 'across', crossings: cr }; }
              }
            }
            const rowD = gr - wordPos, colD = gc;
            const keyD = `D,${rowD},${colD}`;
            if (!seen.has(keyD)) {
              seen.add(keyD);
              const cr = evaluatePlace(word, rowD, colD, 'down');
              if (cr > 0) {
                const dist  = Math.abs(rowD + word.length / 2 - SIZE / 2) + Math.abs(colD - SIZE / 2);
                const score = cr * cr * 300 - dist * 10;
                if (score > bestScore) { bestScore = score; best = { row: rowD, col: colD, direction: 'down', crossings: cr }; }
              }
            }
          }
        }
        // Grid-scan fallback if no anchor found
        if (!best) {
          for (const dir of ['across', 'down']) {
            for (let r = 0; r < SIZE; r++) {
              for (let c = 0; c < SIZE; c++) {
                const cr = evaluatePlace(word, r, c, dir);
                if (cr <= 0) continue;
                const dist = Math.abs(r - SIZE / 2) + Math.abs(c - SIZE / 2);
                const score = cr * cr * 300 - dist * 10;
                if (score > bestScore) { bestScore = score; best = { row: r, col: c, direction: dir, crossings: cr }; }
              }
            }
          }
        }
        if (best) {
          commitWord(wordObj, best.row, best.col, best.direction, best.crossings);
          pool.splice(pi, 1);
          placed_any = true;
          break; // restart loop with updated share scores
        }
      }
      if (!placed_any) break; // no remaining word can be placed
    }
    return { placed, totalCrossings };
  }

  // Run both strategies multiple times; keep best by crossing density
  let bestPlaced    = null;
  let bestScore     = -1;
  const minGood     = Math.floor(nWords * 0.85);
  const ATTEMPTS    = 24;

  function runScore(placed, crossings) {
    return crossings * 10 + placed.length;
  }

  for (let attempt = 0; attempt < ATTEMPTS; attempt++) {
    let ordering;
    if (attempt === 0) {
      ordering = sorted;
    } else {
      const top  = sorted.slice(0, 2);
      const rest = [...sorted.slice(2)];
      for (let i = rest.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [rest[i], rest[j]] = [rest[j], rest[i]];
      }
      ordering = [...top, ...rest];
    }

    // Alternate between simple anchor and dynamic-order strategies
    const { placed, totalCrossings } = attempt % 3 === 2
      ? dynamicOrderPlacement(ordering)
      : tryPlacement(ordering);

    const sc = runScore(placed, totalCrossings);
    if (sc > bestScore) {
      bestScore  = sc;
      bestPlaced = placed;
    }
    if (placed.length >= minGood && totalCrossings >= placed.length * 1.3) break;
  }

  if (!bestPlaced || bestPlaced.length < 8) return null;

  // Trim to bounding box
  let minR = SIZE, maxR = 0, minC = SIZE, maxC = 0;
  for (const p of bestPlaced) {
    const eR = p.direction === 'down'   ? p.row + p.word.length - 1 : p.row;
    const eC = p.direction === 'across' ? p.col + p.word.length - 1 : p.col;
    minR = Math.min(minR, p.row); maxR = Math.max(maxR, eR);
    minC = Math.min(minC, p.col); maxC = Math.max(maxC, eC);
  }

  return {
    gridWidth:  maxC - minC + 1,
    gridHeight: maxR - minR + 1,
    words: bestPlaced.map(p => ({ ...p, row: p.row - minR, col: p.col - minC })),
  };
}

// ---------------------------------------------------------------------------
// Assign reading-order numbers
// ---------------------------------------------------------------------------

function numberWords(words) {
  const positions = new Map();
  for (const w of words) {
    const key = `${w.row},${w.col}`;
    if (!positions.has(key)) positions.set(key, []);
    positions.get(key).push(w);
  }

  const sorted = [...positions.keys()].sort((a, b) => {
    const [ar, ac] = a.split(',').map(Number);
    const [br, bc] = b.split(',').map(Number);
    return ar !== br ? ar - br : ac - bc;
  });

  let num = 1;
  for (const key of sorted) {
    for (const w of positions.get(key)) w.number = num;
    num++;
  }
}

/**
 * Resolve 0-based character index; accept sloppy 1-based if it matches expected letter.
 */
function resolveCharIndexInWord(entry, idxRaw, expectedChar) {
  let idx = parseInt(idxRaw, 10);
  if (!Number.isFinite(idx)) return -1;
  const w = entry.word;
  if (idx >= 0 && idx < w.length && w[idx] === expectedChar) return idx;
  if (idx >= 1 && idx <= w.length && w[idx - 1] === expectedChar) return idx - 1;
  return -1;
}

function normaliseLetterSources(raw, lang = 'de') {
  if (!Array.isArray(raw)) return null;
  return raw.map(item => ({
    word: normalise(item.word || item.wort || '', lang),
    buchstabe_index: item.buchstabe_index ?? item.index ?? item.i ?? item.char_index,
  }));
}

/**
 * Lösungswort: Buchstaben aus gewählten Gitterfeldern (Heft-Stil), sonst Fallback: ein volles Wort.
 */
function buildLoesungswortMeta(placedWords, requestedWord, requestedHint, letterSourcesRaw, lang = 'de') {
  const langCfg = LANG_CONFIG[lang] || LANG_CONFIG.de;
  const lw = normalise(requestedWord || '', lang);
  let hint = String(requestedHint || '').trim();
  const letterSources = normaliseLetterSources(letterSourcesRaw, lang);

  const scatterHintDefault = langCfg.scatterHint;

  if (letterSources && letterSources.length === lw.length && lw.length > 0 && placedWords.length) {
    const cells = [];
    let ok = true;
    for (let k = 0; k < lw.length; k++) {
      const src = letterSources[k];
      const entry = placedWords.find(w => w.word === src.word);
      if (!entry) {
        ok = false;
        break;
      }
      const idx = resolveCharIndexInWord(entry, src.buchstabe_index, lw[k]);
      if (idx < 0) {
        ok = false;
        break;
      }
      const r = entry.direction === 'down' ? entry.row + idx : entry.row;
      const c = entry.direction === 'across' ? entry.col + idx : entry.col;
      cells.push({ row: r, col: c, n: k + 1 });
    }
    if (ok) {
      const seen = new Set();
      for (const cell of cells) {
        const key = `${cell.row},${cell.col}`;
        if (seen.has(key)) {
          ok = false;
          break;
        }
        seen.add(key);
      }
    }
    if (ok && lw.length >= 4 && isSingleContiguousStraightLine(cells.map(({ row, col }) => ({ r: row, c: col })))) {
      const scattered = pickScatteredLoesungswortQuelle(placedWords, lw, lang);
      if (scattered) {
        cells.length = 0;
        for (let k = 0; k < lw.length; k++) {
          const src = scattered[k];
          const entry = placedWords.find(w => w.word === src.word);
          const idx = resolveCharIndexInWord(entry, src.buchstabe_index, lw[k]);
          const r = entry.direction === 'down' ? entry.row + idx : entry.row;
          const c = entry.direction === 'across' ? entry.col + idx : entry.col;
          cells.push({ row: r, col: c, n: k + 1 });
        }
        const seen2 = new Set();
        for (const cell of cells) {
          const key = `${cell.row},${cell.col}`;
          if (seen2.has(key)) {
            ok = false;
            break;
          }
          seen2.add(key);
        }
      }
    }
    if (ok) {
      if (!hint) hint = scatterHintDefault;
      return {
        loesungswort: lw,
        loesungswortHinweis: hint,
        loesungswortCells: cells.map(({ row, col, n }) => ({ row, col, n })),
        loesungswortNumber: null,
        loesungswortDirection: null,
        loesungswortScatter: true,
      };
    }
  }

  let entry = placedWords.find(w => w.word === lw);
  if (!entry && placedWords.length) {
    entry = [...placedWords].sort((a, b) => b.word.length - a.word.length)[0];
  }
  if (!entry) {
    return {
      loesungswort: '',
      loesungswortHinweis: '',
      loesungswortCells: [],
      loesungswortNumber: null,
      loesungswortDirection: null,
      loesungswortScatter: false,
    };
  }

  if (!hint) {
    hint = scatterHintDefault;
  }

  const loesungswortCells = [];
  for (let i = 0; i < entry.word.length; i++) {
    const r = entry.direction === 'down' ? entry.row + i : entry.row;
    const c = entry.direction === 'across' ? entry.col + i : entry.col;
    loesungswortCells.push({ row: r, col: c, n: i + 1 });
  }

  return {
    loesungswort: entry.word,
    loesungswortHinweis: hint,
    loesungswortCells,
    loesungswortNumber: null,
    loesungswortDirection: null,
    loesungswortScatter: true,
  };
}

// ---------------------------------------------------------------------------
// API endpoint
// ---------------------------------------------------------------------------

app.post('/api/generate', async (req, res) => {
  try {
    const settings = req.body.settings || {};
    const lang = getLang(settings);
    const langCfg = LANG_CONFIG[lang];
    const puzzleType = 'schweden';
    const wcRaw = parseInt(settings.wordCount, 10);
    const wordCount = [16, 20, 24, 28, 32].includes(wcRaw) ? wcRaw : 24;

    const loesPlan = await fetchLoesungswortPlan(settings, wordCount);
    const plannedLw = loesPlan ? loesPlan.loesungswort : '';
    const plannedHint = loesPlan ? loesPlan.loesungswortHinweis : '';

    const userPrompt = buildUserPrompt({
      ...settings,
      puzzleType,
      wordCount,
      plannedLoesungswort: plannedLw,
    });
    let wordObjects = null;
    let title = langCfg.solutionWordLabel || 'Kreuzworträtsel';

    const maxTokens = Math.min(2800, 1180 + wordCount * 30);
    const minAcceptable = Math.floor(wordCount * 0.75);

    for (let attempt = 0; attempt < 2; attempt++) {
      const msg = await client.messages.create({
        model: 'claude-opus-4-5',
        max_tokens: maxTokens,
        system: getSystemPrompt(lang),
        messages: [{ role: 'user', content: userPrompt }],
      });

      let raw = '';
      try {
        raw = msg.content[0].text;
        const data = JSON.parse(stripJson(raw));
        title = data.title || title;

        wordObjects = (data.words || [])
          .map(w => ({ word: normalise(w.word, lang), clue: String(w.clue || '') }))
          .filter(w => w.word.length >= 4 && w.word.length <= 10 && w.clue);

        if (wordObjects.length >= minAcceptable) {
          if (!plannedLw || lettersCoveredByWords(wordObjects, plannedLw, lang)) break;
        }
      } catch (e) {
        console.warn('JSON parse error, retrying:', e.message, raw.slice(0, 100));
      }
    }

    if (!wordObjects || wordObjects.length < 6) {
      return res.status(500).json({ error: 'Zu wenige Wörter von Claude erhalten. Bitte erneut versuchen.' });
    }

    const useLoesPlan = !!(plannedLw && lettersCoveredByWords(wordObjects, plannedLw, lang));
    if (plannedLw && !useLoesPlan) {
      console.warn('Geplantes Lösungswort nicht durch Buchstaben in Wortliste abgedeckt — nutze Fallback ohne Vorplan.');
    }

    // Remove duplicate words
    const seen = new Set();
    wordObjects = wordObjects.filter(w => {
      if (seen.has(w.word)) return false;
      seen.add(w.word);
      return true;
    });

    const result = placeCrossword(wordObjects);

    if (!result) {
      return res.status(500).json({ error: 'Konnte keine ausreichende Gitteranordnung erzeugen. Bitte erneut versuchen.' });
    }

    numberWords(result.words);

    let lwMeta;
    if (useLoesPlan) {
      let quelle = pickScatteredLoesungswortQuelle(result.words, plannedLw, lang);
      if (!quelle) {
        const qOnly = await fetchLoesungswortQuelleOnlyAfterPlacement(
          result.words,
          plannedLw,
          title,
          { ...settings, puzzleType }
        );
        quelle = qOnly ? qOnly.quelle : null;
      }
      lwMeta = buildLoesungswortMeta(
        result.words,
        plannedLw,
        plannedHint,
        quelle,
        lang
      );
    } else {
      const lwAi = await fetchLoesungswortAfterPlacement(result.words, title, {
        ...settings,
        puzzleType,
      });
      lwMeta = buildLoesungswortMeta(
        result.words,
        lwAi ? lwAi.loesungswort : '',
        lwAi ? lwAi.hint : '',
        lwAi ? lwAi.quelle : null,
        lang
      );
    }

    res.json({ title, puzzleType, language: lang, ...result, ...lwMeta });
  } catch (err) {
    console.error('Fehler:', err);
    res.status(500).json({ error: err.message });
  }
});

// ---------------------------------------------------------------------------
// Standalone HTML renderer for Puppeteer
// ---------------------------------------------------------------------------

function generatePuzzleHTML(puzzleData, showSolution = false, omaName = '', issueNo = null) {
  const { title, gridWidth, gridHeight, words } = puzzleData;
  const lang = puzzleData.language || 'de';
  const langCfg = LANG_CONFIG[lang] || LANG_CONFIG.de;
  const nWords = words.length;
  const loesW = puzzleData.loesungswort || '';
  const loesH = puzzleData.loesungswortHinweis || '';
  const loesCells = puzzleData.loesungswortCells || [];
  const loesOrderMap = {};
  loesCells.forEach((cell, i) => {
    const ord = cell.n ?? cell.order ?? i + 1;
    loesOrderMap[`${cell.row},${cell.col}`] = ord;
  });

  const puzzleType = 'schweden';

  const esc = s => String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const safeTitle = esc(title || langCfg.solutionWordLabel || 'Kreuzworträtsel');
  const safeName = esc((omaName || '').trim());
  const dedicLine = safeName ? `Für ${safeName}` : 'Mit lieben Grüßen';
  const footerPlay = showSolution
    ? '✦ Lösungsblatt ✦'
    : (safeName ? `Viel Spaß beim Rätseln, ${safeName}! ♥` : 'Viel Spaß beim Rätseln! ♥');

  // Build letter grid
  const grid = Array.from({ length: gridHeight }, () => Array(gridWidth).fill(null));
  for (const w of words) {
    for (let i = 0; i < w.word.length; i++) {
      const r = w.direction === 'down' ? w.row + i : w.row;
      const c = w.direction === 'across' ? w.col + i : w.col;
      if (r >= 0 && r < gridHeight && c >= 0 && c < gridWidth) grid[r][c] = w.word[i];
    }
  }

  // Number map
  const numMap = {};
  for (const w of words) {
    if (w.number !== undefined) numMap[`${w.row},${w.col}`] = w.number;
  }

  // ~usable content width inside A4 (px @ 96dpi) — shrink cell size so wide grids fit
  const usableWpx = 540;
  const COL_PX = Math.max(13, Math.min(34, Math.floor(usableWpx / Math.max(1, gridWidth))));
  const ROW_PX = COL_PX;

  // Tighter clue typography when many words (helps single-page PDF)
  const clueFontPx = nWords > 22 ? 9.5 : nWords > 16 ? 10.5 : 12;
  const clueGapPx = nWords > 22 ? 3 : 5;
  const clueHeadPx = nWords > 22 ? 8 : 9;

  // Grid cells
  let gridCells = '';
  for (let r = 0; r < gridHeight; r++) {
    for (let c = 0; c < gridWidth; c++) {
      const letter = grid[r][c];
      if (letter === null) {
        gridCells += `<div class="cell black"></div>`;
        continue;
      }
      const key = `${r},${c}`;
      const num = numMap[key];
      const numHtml = (puzzleType !== 'gitter' && num) ? `<span class="num">${num}</span>` : '';
      const loesOrd = loesOrderMap[key];
      const loesOrdHtml = loesOrd != null ? `<span class="loes-zahl">${loesOrd}</span>` : '';
      const letterHtml = showSolution
        ? `<span class="letter sol">${letter}</span>`
        : `<span class="letter"></span>`;
      const inner = `${numHtml}${letterHtml}${loesOrdHtml}`;
      gridCells += `<div class="cell">${inner}</div>`;
    }
  }

  // Clues
  const across = words.filter(w => w.direction === 'across').sort((a, b) => a.number - b.number);
  const down   = words.filter(w => w.direction === 'down').sort((a, b) => a.number - b.number);

  const modeNote = `<p class="mode-note">${esc(langCfg.modeNote)}</p>`;

  const clueList = arr => arr.map(w =>
    `<div class="clue"><span class="cn">${w.number}</span><span class="ct">${w.clue.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span></div>`
  ).join('');

  let loeswortBundleHtml = '';
  if (loesW) {
    const loesUniversalHint = langCfg.universalHint;
    const slotPx = Math.max(16, Math.min(24, COL_PX + 2));
    let slotCells = '';
    for (let i = 0; i < loesW.length; i++) {
      const inner = showSolution
        ? `<span class="loeswort-slot-letter">${esc(loesW[i])}</span>`
        : '';
      slotCells += `<div class="loeswort-slot">${inner}</div>`;
    }
    const reveal = showSolution
      ? `<p class="loeswort-answer"><strong>Lösung:</strong> ${esc(loesW)}</p>`
      : '';
    loeswortBundleHtml = `
    <div class="loeswort-unter-gitte">
      <div class="loeswort-leiste-head">${esc(langCfg.solutionWordLabel)}</div>
      <div class="loeswort-slots" style="--loes-slot:${slotPx}px">${slotCells}</div>
      <p class="loeswort-hint">${loesH ? esc(loesH) : esc(loesUniversalHint)}</p>
      ${reveal}
    </div>`;
  }

  const gridW = gridWidth * COL_PX;
  const htmlLang = lang === 'ar' ? 'ar' : lang === 'ru' ? 'ru' : lang === 'el' ? 'el' : lang === 'tr' ? 'tr' : lang === 'es' ? 'es' : lang === 'it' ? 'it' : 'de';
  const htmlDir = langCfg.dir === 'rtl' ? ' dir="rtl"' : '';

  return `<!DOCTYPE html>
<html lang="${htmlLang}"${htmlDir}>
<head>
<meta charset="UTF-8">
<title>Kreuzworträtsel – ${title}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Arial', Helvetica, sans-serif;
    background: white;
    padding: 0;
    color: #111;
  }
  .page {
    width: 210mm;
    max-width: 210mm;
    min-height: auto;
    margin: 0 auto;
    padding: 10mm 12mm 10mm;
    box-sizing: border-box;
  }
  .top-rule {
    border-top: 1px solid #222;
    margin-bottom: 8px;
  }
  /* Magazin-Titelleiste (wie gedrucktes Rätselheft) */
  .title-bar {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 6px 14px;
    padding-bottom: 8px;
    margin-bottom: 12px;
    border-bottom: 1px solid #333;
    box-shadow: 0 1px 0 #333;
  }
  .title-bar h2 {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 17px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #111;
    flex: 1 1 auto;
    min-width: 0;
  }
  .issue-no {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    color: #888;
    letter-spacing: 1px;
    flex-shrink: 0;
  }
  .title-dedication {
    margin-left: auto;
    font-family: Georgia, serif;
    font-size: 11px;
    font-style: italic;
    color: #444;
    flex-shrink: 0;
  }
  /* Layout: grid ON TOP, clues ONLY below (full width, never beside the grid) */
  .layout {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    gap: 12px;
  }
  .grid-col {
    flex: 0 0 auto;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: flex-start;
  }
  .grid-frame {
    background: #111;
    padding: 4px;
    display: inline-block;
    line-height: 0;
  }
  .clues-below {
    flex: 0 0 auto;
    width: 100%;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: ${clueGapPx + 2}px 20px;
    align-items: start;
    box-sizing: border-box;
  }
  /* Grid */
  .grid {
    display: grid;
    grid-template-columns: repeat(${gridWidth}, ${COL_PX}px);
    grid-template-rows: repeat(${gridHeight}, ${ROW_PX}px);
    border: 2px solid #111;
    border-right: none;
    border-bottom: none;
    width: ${gridW}px;
    max-width: 100%;
    flex-shrink: 0;
  }
  .cell {
    width: ${COL_PX}px;
    height: ${ROW_PX}px;
    border-right: 1.5px solid #555;
    border-bottom: 1.5px solid #555;
    position: relative;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .cell.black { background: #111; border-color: #111; }
  .loes-zahl {
    position: absolute;
    right: 1px;
    bottom: 1px;
    min-width: ${COL_PX < 20 ? 9 : 12}px;
    height: ${COL_PX < 20 ? 9 : 12}px;
    padding: 0 2px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: ${COL_PX < 20 ? 5 : 6.5}px;
    font-weight: 900;
    line-height: 1;
    letter-spacing: -0.02em;
    color: #fff;
    background: #0f766e;
    border-radius: 2px;
    box-shadow: 0 0 0 0.5px rgba(15, 118, 110, 0.5);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    pointer-events: none;
    z-index: 4;
  }
  .mode-note {
    font-size: 9px;
    color: #444;
    margin: 0 0 10px;
    line-height: 1.35;
    grid-column: 1 / -1;
  }
  .num {
    position: absolute;
    top: 1px; left: 2px;
    font-size: ${COL_PX < 20 ? 5.5 : 7}px;
    font-weight: 700;
    line-height: 1;
    color: #222;
  }
  .letter {
    font-size: ${Math.max(11, COL_PX - 4)}px;
    font-weight: 900;
    color: #111;
    line-height: 1;
  }
  .letter.sol { color: #c8102e; }
  /* Clues */
  .clues-section { margin-bottom: 0; }
  .clues-heading {
    font-size: ${clueHeadPx}px;
    font-weight: 900;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c8102e;
    border-bottom: 2px solid #c8102e;
    padding-bottom: 2px;
    margin-bottom: 6px;
  }
  .clue {
    display: flex;
    gap: 5px;
    margin-bottom: ${clueGapPx}px;
    font-size: ${clueFontPx}px;
    line-height: 1.25;
    break-inside: avoid;
  }
  .cn { font-weight: 900; min-width: 14px; text-align: right; color: #c8102e; flex-shrink: 0; }
  .ct { flex: 1; min-width: 0; }
  .loeswort-unter-gitte {
    flex: 0 0 auto;
    width: 100%;
    max-width: ${gridW}px;
    margin: 0 auto 12px;
    padding: 10px 12px;
    background: #f7f3ec;
    border: 1px solid #cfc7bb;
    border-radius: 4px;
    box-sizing: border-box;
    break-inside: avoid;
  }
  .loeswort-leiste-head {
    font-size: ${Math.min(clueHeadPx + 1, 10)}px;
    font-weight: 900;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #c8102e;
    border-bottom: 2px solid #c8102e;
    padding-bottom: 3px;
    margin-bottom: 8px;
  }
  .loeswort-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 6px;
    margin-bottom: 8px;
    align-items: center;
    justify-content: center;
  }
  .loeswort-slot {
    width: var(--loes-slot, 20px);
    height: calc(var(--loes-slot, 20px) * 1.15);
    border: 2px solid #333;
    border-radius: 2px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .loeswort-slot-letter {
    font-size: calc(var(--loes-slot, 20px) * 0.75);
    font-weight: 900;
    color: #c8102e;
  }
  .loeswort-hint {
    font-size: ${clueFontPx}px;
    line-height: 1.35;
    color: #333;
    margin: 0;
  }
  .loeswort-answer {
    font-size: ${clueFontPx}px;
    margin: 6px 0 0;
    color: #c8102e;
    font-weight: 800;
  }
  /* Footer */
  .footer {
    margin-top: 12px;
    border-top: 2px solid #c8102e;
    padding-top: 6px;
    text-align: center;
    font-size: 10px;
    font-style: italic;
    color: #666;
  }
</style>
</head>
<body>
<div class="page">

  <div class="top-rule"></div>

  <div class="title-bar">
    <h2>${safeTitle}</h2>
    ${issueNo != null && issueNo !== '' ? `<span class="issue-no">Nr.&nbsp;${esc(String(issueNo))}</span>` : ''}
    <span class="title-dedication">${dedicLine}</span>
  </div>

  <div class="layout">
    <div class="grid-col">
      <div class="grid-frame"><div class="grid">${gridCells}</div></div>
    </div>
    ${loeswortBundleHtml}
    <div class="clues-below">
      ${modeNote}
      <div class="clues-section">
        <div class="clues-heading">${esc(langCfg.acrossLabel)}</div>
        ${clueList(across)}
      </div>
      <div class="clues-section">
        <div class="clues-heading">${esc(langCfg.downLabel)}</div>
        ${clueList(down)}
      </div>
    </div>
  </div>

  <div class="footer">
    ${footerPlay}
  </div>

</div>
</body>
</html>`;
}

// ---------------------------------------------------------------------------
// Puppeteer – local reuses a singleton; Vercel creates a fresh browser each time
// ---------------------------------------------------------------------------

let _localBrowser = null;

async function getBrowser() {
  if (IS_SERVERLESS) {
    // Vercel / Lambda: use pre-built minimal Chromium
    const chromium      = require('@sparticuz/chromium');
    const puppeteerCore = require('puppeteer-core');
    return await puppeteerCore.launch({
      args:            chromium.args,
      defaultViewport: chromium.defaultViewport,
      executablePath:  await chromium.executablePath(),
      headless:        chromium.headless,
    });
  }

  // Local dev: reuse a single browser instance
  if (!_localBrowser || !_localBrowser.isConnected()) {
    const puppeteer = require('puppeteer');
    _localBrowser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
    });
  }
  return _localBrowser;
}

// ---------------------------------------------------------------------------
// /api/pdf  – returns a proper A4 PDF
// ---------------------------------------------------------------------------

app.post('/api/pdf', async (req, res) => {
  const { puzzleData, solution = false, omaName = '', puzzleNumber } = req.body;
  if (!puzzleData) return res.status(400).json({ error: 'puzzleData fehlt' });

  let browser, page;
  try {
    browser = await getBrowser();
    page    = await browser.newPage();

    await page.setViewport({ width: 794, height: 2200, deviceScaleFactor: 1 });
    await page.setContent(generatePuzzleHTML(puzzleData, solution, omaName, puzzleNumber ?? null), { waitUntil: 'networkidle0' });

    const contentH = await page.evaluate(() => {
      const box = document.querySelector('.page');
      return box ? Math.ceil(box.getBoundingClientRect().height) : document.documentElement.scrollHeight;
    });
    // A4 printable area ≈ 1040 CSS px at 96dpi with small margins — scale down if taller (one sheet only)
    const TARGET_ONE_PAGE_PX = 1020;
    let pdfScale = 1;
    if (contentH > TARGET_ONE_PAGE_PX) {
      pdfScale = Math.max(0.5, (TARGET_ONE_PAGE_PX / contentH) * 0.98);
    }

    const pdfRaw = await page.pdf({
      format: 'A4',
      printBackground: true,
      margin: { top: '6mm', right: '6mm', bottom: '6mm', left: '6mm' },
      scale: pdfScale,
    });

    const pdfBuf  = Buffer.isBuffer(pdfRaw) ? pdfRaw : Buffer.from(pdfRaw);
    const filename = solution ? 'kreuzwortratsel-loesung.pdf' : 'kreuzwortratsel-oma.pdf';
    res.set('Content-Type', 'application/pdf');
    res.set('Content-Disposition', `attachment; filename="${filename}"`);
    res.end(pdfBuf);
  } catch (err) {
    console.error('PDF-Fehler:', err);
    if (!res.headersSent) res.status(500).json({ error: err.message });
  } finally {
    if (page)    try { await page.close();    } catch {}
    if (IS_SERVERLESS && browser) try { await browser.close(); } catch {}
  }
});

// ---------------------------------------------------------------------------
// /api/png  – returns a PNG screenshot (handy for WhatsApp / sharing)
// ---------------------------------------------------------------------------

app.post('/api/png', async (req, res) => {
  const { puzzleData, solution = false, omaName = '', puzzleNumber } = req.body;
  if (!puzzleData) return res.status(400).json({ error: 'puzzleData fehlt' });

  let browser, page;
  try {
    browser = await getBrowser();
    page    = await browser.newPage();
    await page.setViewport({ width: 794, height: 1123, deviceScaleFactor: 2 });

    await page.setContent(generatePuzzleHTML(puzzleData, solution, omaName, puzzleNumber ?? null), { waitUntil: 'networkidle0' });

    const pngRaw  = await page.screenshot({ fullPage: true, type: 'png' });
    const pngBuf  = Buffer.isBuffer(pngRaw) ? pngRaw : Buffer.from(pngRaw);
    const filename = solution ? 'kreuzwortratsel-loesung.png' : 'kreuzwortratsel-oma.png';
    res.set('Content-Type', 'image/png');
    res.set('Content-Disposition', `attachment; filename="${filename}"`);
    res.end(pngBuf);
  } catch (err) {
    console.error('PNG-Fehler:', err);
    if (!res.headersSent) res.status(500).json({ error: err.message });
  } finally {
    if (page)    try { await page.close();    } catch {}
    if (IS_SERVERLESS && browser) try { await browser.close(); } catch {}
  }
});

// ---------------------------------------------------------------------------

const PORT = process.env.PORT || 3000;
const server = IS_SERVERLESS
  ? { close: () => {} }                      // no-op on Vercel
  : app.listen(PORT, () => {
      console.log(`\n✦ Kreuzworträtsel-Generator läuft auf http://localhost:${PORT}\n`);
    });

// Clean up local Puppeteer on exit
process.on('SIGINT', async () => {
  if (_localBrowser) await _localBrowser.close();
  server.close();
  process.exit(0);
});

// Vercel needs the Express app exported
module.exports = app;
