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
// Anthropic: only ask for words + clues, no grid layout
// ---------------------------------------------------------------------------

const SYSTEM_PROMPT = `Du bist ein einfühlsamer Redakteur für deutschsprachige Kreuzworträtsel für Seniorinnen und Senioren.
Du gibst AUSSCHLIESSLICH valides JSON zurück – kein weiterer Text, keine Markdown-Codeblöcke.
Wenn persönliche Familien- oder Lebensinformationen geliefert werden, sind sie verbindlich und dürfen nicht ignoriert werden.
Wenn ein Gesundheits- oder Unterstützungskontext (z. B. Demenz, Depression) angegeben ist, ist dieser verbindlich – respektvoll, nicht stigmatisierend formulieren.
Die gewählte Schwierigkeit (sehr leicht / leicht / mittel) ist verbindlich für Wort- und Hinweiswahl.`;

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
RÄTSELART „Schwedenstil“ (kompakte Hinweise – erscheinen später auch in den Startkästchen):
- Jeder Hinweis („clue“) maximal etwa 50 Zeichen, lieber kürzer; trotzdem für Seniorinnen verständlich.
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
  const healthProfile = normalizeHealthProfile(settings.healthProfile);
  const difficulty    = normalizeDifficulty(settings.difficulty);
  const puzzleType = ['standard', 'schweden', 'gitter'].includes(settings.puzzleType)
    ? settings.puzzleType
    : 'standard';
  const name = (settings.name || '').trim();
  const nameLine = name
    ? `Optionaler Vorname für warme, sparsame Ansprache in einzelnen Hinweisen: ${name}`
    : 'Kein Vorname angegeben – formulieren Sie die Hinweise allgemein herzlich (ohne fiktiven Namen).';

  const topics = Array.isArray(settings.topics) && settings.topics.length
    ? settings.topics
    : ['Natur & Jahreszeiten', 'Alltag & Zuhause', 'Einfache Freizeit', 'Essen & Trinken'];

  const customContext = (settings.customContext || '').trim();
  const familyStory   = (settings.familyStory || '').trim();
  const useFamily     = settings.useFamilyStory !== false && familyStory.length > 0;

  const topicBlock = topics.map(t => `• ${t}`).join('\n');

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

MISCHUNG MIT ALLGEMEINEN BEGRIFFEN (verbindliche Aufteilung der 24 Wörter):
- Mindestens 10 Lösungswörter inkl. Hinweise sollen sich eindeutig auf die persönlichen Informationen beziehen (Familie, Orte, Berufe, Beziehungen).
- Mindestens 8 Lösungswörter sollen klassische, leichte Allgemeinbegriffe sein, passend zu diesen Themen (nicht aus dem Familientext „abfischbar“ – echte allgemeine Begriffe wie Blume, Bach, Brot, Walzer, Sonne je nach Thema):
${topicBlock}
- Die restlichen bis 6 Einträge frei wählen (persönlich oder allgemein), damit das Gitter gut vernetzbar bleibt.
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

  return `Erstelle genau 24 deutsche Wörter mit jeweils einem kurzen Hinweis (Rätselfrage) für ein Kreuzworträtsel.

${difficultyBlock(difficulty)}${healthProfileBlock(healthProfile)}
${audienceLine} Ton: warm, würdevoll, positiv, sehr klar. Keine ironischen Texte, keine unnötig schweren Begriffe.

${nameLine}
${name ? `Als JSON-"title" z. B. ein herzlicher Titel in Großbuchstaben, z. B. „${name.toUpperCase()}S FAMILIENRÄTSEL“ oder „EIN RÄTSEL FÜR ${name.toUpperCase()}“ — oder eine eigene passende Kurzform.` : 'Als JSON-"title" einen kurzen, herzlichen Titel (gern in Großbuchstaben, Magazinstil).'}
${personalBlock}${generalBlock}
${puzzleTypePromptExtra(puzzleType)}

Technische Regeln:
- Pro Eintrag: "word" (4–9 Buchstaben, ideal für Kreuzungen), nur A–Z; Umlaute als AE, OE, UE; SS statt ß
- "clue": ein kurzer, freundlicher deutscher Satz
- Bevorzugen Sie Buchstaben E, N, R, S, T, A, I, O für gutes Vernetzen

Antwortformat (NUR dieses JSON, exakt 24 Objekte in "words"):
{
  "title": "Kurzer herzlicher Titel",
  "words": [
    { "word": "BEISPIEL", "clue": "Kurzer Hinweis" }
  ]
}`;
}

// ---------------------------------------------------------------------------
// Word normaliser
// ---------------------------------------------------------------------------

function normalise(word) {
  return String(word)
    .toUpperCase()
    .replace(/Ä/g, 'AE').replace(/Ö/g, 'OE').replace(/Ü/g, 'UE')
    .replace(/ß/g, 'SS').replace(/[^A-Z]/g, '');
}

function stripJson(text) {
  return text.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/i, '').trim();
}

// ---------------------------------------------------------------------------
// Crossword placement algorithm  (exhaustive search, densely-connected)
// ---------------------------------------------------------------------------

function placeCrossword(wordObjects) {
  // Grid size: large enough for ~20 words, small enough to stay dense
  const SIZE = 21;

  const grid = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
  // dirGrid[r][c] = 'across' | 'down' | 'both' | null — tracks which direction(s) occupy a cell
  const dirGrid = Array.from({ length: SIZE }, () => Array(SIZE).fill(null));
  const placed = [];

  function hasLetter(r, c) {
    return r >= 0 && r < SIZE && c >= 0 && c < SIZE && grid[r][c] !== null;
  }

  // Returns number of crossings if placement is valid, or -1 if invalid
  function evaluatePlace(word, row, col, dir) {
    const endR = dir === 'down' ? row + word.length - 1 : row;
    const endC = dir === 'across' ? col + word.length - 1 : col;

    if (row < 0 || col < 0 || endR >= SIZE || endC >= SIZE) return -1;

    // no letter immediately before/after in the same direction (prevents word-merging)
    if (dir === 'across') {
      if (hasLetter(row, col - 1))          return -1;
      if (hasLetter(row, col + word.length)) return -1;
    } else {
      if (hasLetter(row - 1, col))          return -1;
      if (hasLetter(row + word.length, col)) return -1;
    }

    let crossings = 0;

    for (let i = 0; i < word.length; i++) {
      const r = dir === 'down' ? row + i : row;
      const c = dir === 'across' ? col + i : col;
      const existing = grid[r][c];

      if (existing !== null) {
        // Cell occupied — letter must match
        if (existing !== word[i]) return -1;
        // Must have been placed by a word going the OTHER direction
        const cellDir = dirGrid[r][c];
        if (cellDir === dir) return -1;           // same direction overlap → invalid
        crossings++;
      } else {
        // Empty cell — no parallel neighbour allowed (prevents ghost words)
        if (dir === 'across') {
          if (hasLetter(r - 1, c) || hasLetter(r + 1, c)) return -1;
        } else {
          if (hasLetter(r, c - 1) || hasLetter(r, c + 1)) return -1;
        }
      }
    }

    // First word: no crossings needed. Every subsequent word needs ≥ 1.
    if (placed.length > 0 && crossings === 0) return -1;

    return crossings;
  }

  function commitWord(wordObj, row, col, dir) {
    const { word } = wordObj;
    for (let i = 0; i < word.length; i++) {
      const r = dir === 'down' ? row + i : row;
      const c = dir === 'across' ? col + i : col;
      grid[r][c] = word[i];
      // track direction: mark 'both' if already occupied by opposite direction
      dirGrid[r][c] = dirGrid[r][c] && dirGrid[r][c] !== dir ? 'both' : dir;
    }
    placed.push({ ...wordObj, row, col, direction: dir });
  }

  // Sort longest first — longer words offer more crossing opportunities
  const sorted = [...wordObjects].sort((a, b) => b.word.length - a.word.length);

  // Place first word horizontally through the centre
  const first = sorted[0];
  commitWord(first, Math.floor(SIZE / 2), Math.floor((SIZE - first.word.length) / 2), 'across');

  // --- Exhaustive search for every subsequent word ---
  // Score = crossings² × 100  (strongly rewards 2+ crossings)
  //       − distance from centre × 0.4  (keeps puzzle compact)
  for (let wi = 1; wi < sorted.length; wi++) {
    const wordObj = sorted[wi];
    const word = wordObj.word;
    let best = null;
    let bestScore = -Infinity;

    for (const dir of ['across', 'down']) {
      for (let r = 0; r < SIZE; r++) {
        for (let c = 0; c < SIZE; c++) {
          const crossings = evaluatePlace(word, r, c, dir);
          if (crossings < 0) continue;                    // invalid

          const dist  = Math.abs(r - SIZE / 2) + Math.abs(c - SIZE / 2);
          const score = crossings * crossings * 100 - dist * 0.4;

          if (score > bestScore) {
            bestScore = score;
            best = { row: r, col: c, direction: dir };
          }
        }
      }
    }

    if (best) commitWord(wordObj, best.row, best.col, best.direction);
  }

  if (placed.length < 10) return null;

  // Trim to bounding box
  let minR = SIZE, maxR = 0, minC = SIZE, maxC = 0;
  for (const p of placed) {
    const eR = p.direction === 'down' ? p.row + p.word.length - 1 : p.row;
    const eC = p.direction === 'across' ? p.col + p.word.length - 1 : p.col;
    minR = Math.min(minR, p.row); maxR = Math.max(maxR, eR);
    minC = Math.min(minC, p.col); maxC = Math.max(maxC, eC);
  }

  return {
    gridWidth:  maxC - minC + 1,
    gridHeight: maxR - minR + 1,
    words: placed.map(p => ({ ...p, row: p.row - minR, col: p.col - minC })),
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

// ---------------------------------------------------------------------------
// API endpoint
// ---------------------------------------------------------------------------

app.post('/api/generate', async (req, res) => {
  try {
    const settings = req.body.settings || {};
    const puzzleType = ['standard', 'schweden', 'gitter'].includes(settings.puzzleType)
      ? settings.puzzleType
      : 'standard';
    const userPrompt = buildUserPrompt({ ...settings, puzzleType });
    let wordObjects = null;
    let title = 'Kreuzworträtsel';

    for (let attempt = 0; attempt < 2; attempt++) {
      const msg = await client.messages.create({
        model: 'claude-opus-4-5',
        max_tokens: 1600,
        system: SYSTEM_PROMPT,
        messages: [{ role: 'user', content: userPrompt }],
      });

      let raw = '';
      try {
        raw = msg.content[0].text;
        const data = JSON.parse(stripJson(raw));
        title = data.title || title;

        wordObjects = (data.words || [])
          .map(w => ({ word: normalise(w.word), clue: String(w.clue || '') }))
          .filter(w => w.word.length >= 4 && w.word.length <= 10 && w.clue);

        if (wordObjects.length >= 18) break;
      } catch (e) {
        console.warn('JSON parse error, retrying:', e.message, raw.slice(0, 100));
      }
    }

    if (!wordObjects || wordObjects.length < 6) {
      return res.status(500).json({ error: 'Zu wenige Wörter von Claude erhalten. Bitte erneut versuchen.' });
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

    res.json({ title, puzzleType, ...result });
  } catch (err) {
    console.error('Fehler:', err);
    res.status(500).json({ error: err.message });
  }
});

// ---------------------------------------------------------------------------
// Standalone HTML renderer for Puppeteer
// ---------------------------------------------------------------------------

function clueShort(text, maxLen) {
  const t = String(text || '').trim();
  if (t.length <= maxLen) return t;
  return `${t.slice(0, Math.max(1, maxLen - 1)).trim()}…`;
}

function swedenCellInner(wordsHere, esc, showSolution, letter) {
  const wa = wordsHere.find(w => w.direction === 'across');
  const wd = wordsHere.find(w => w.direction === 'down');
  let inner = '';
  if (wa) inner += `<div class="sw-line"><span class="arr">→</span>${esc(clueShort(wa.clue, 54))}</div>`;
  if (wd) inner += `<div class="sw-line"><span class="arr">↓</span>${esc(clueShort(wd.clue, 54))}</div>`;
  const letterHtml = showSolution
    ? `<span class="letter sol">${letter}</span>`
    : `<span class="letter"></span>`;
  return `<div class="sweden-clues">${inner}</div>${letterHtml}`;
}

function generatePuzzleHTML(puzzleData, showSolution = false, omaName = '', issueNo = null) {
  const { title, gridWidth, gridHeight, words } = puzzleData;
  const nWords = words.length;

  const puzzleType = ['standard', 'schweden', 'gitter'].includes(puzzleData.puzzleType)
    ? puzzleData.puzzleType
    : 'standard';

  const esc = s => String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const safeTitle = esc(title || 'Kreuzworträtsel');
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
  const ROW_PX = puzzleType === 'schweden' ? Math.min(COL_PX + 22, 58) : COL_PX;

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
      const wordsHere = words.filter(w => w.row === r && w.col === c);
      const isStart = wordsHere.length > 0;
      let cellClass = 'cell';
      if (puzzleType === 'schweden' && isStart) cellClass += ' cell-sweden';

      let inner;
      if (puzzleType === 'schweden' && isStart) {
        inner = swedenCellInner(wordsHere, esc, showSolution, letter);
      } else {
        const numHtml = (puzzleType !== 'gitter' && num) ? `<span class="num">${num}</span>` : '';
        const letterHtml = showSolution
          ? `<span class="letter sol">${letter}</span>`
          : `<span class="letter"></span>`;
        inner = `${numHtml}${letterHtml}`;
      }
      gridCells += `<div class="${cellClass}">${inner}</div>`;
    }
  }

  // Clues
  const across = words.filter(w => w.direction === 'across').sort((a, b) => a.number - b.number);
  const down   = words.filter(w => w.direction === 'down').sort((a, b) => a.number - b.number);

  const modeNote = puzzleType === 'schweden'
    ? '<p class="mode-note"><strong>Schwedenstil:</strong> Kurze Hinweise stehen in den Startkästchen (→ waagerecht, ↓ senkrecht). Die Liste dient zum Nachschlagen.</p>'
    : puzzleType === 'gitter'
      ? '<p class="mode-note"><strong>Gitter ohne Nummern:</strong> Im Gitter sind keine Ziffern – die Zuordnung ergibt sich aus den Kreuzungen und der Liste unten.</p>'
      : '';

  const clueList = arr => arr.map(w =>
    `<div class="clue"><span class="cn">${w.number}</span><span class="ct">${w.clue.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span></div>`
  ).join('');

  const gridW = gridWidth * COL_PX;

  return `<!DOCTYPE html>
<html lang="de">
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
  .cell.cell-sweden {
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-end;
    padding: 2px 3px 3px;
  }
  .sweden-clues { width: 100%; flex: 0 0 auto; }
  .sw-line {
    font-size: ${Math.max(5, Math.round(COL_PX * 0.2))}px;
    line-height: 1.12;
    text-align: left;
    color: #222;
    word-break: break-word;
  }
  .sw-line .arr { color: #c8102e; font-weight: 900; margin-right: 2px; }
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
    <div class="clues-below">
      ${modeNote}
      <div class="clues-section">
        <div class="clues-heading">Waagerecht →</div>
        ${clueList(across)}
      </div>
      <div class="clues-section">
        <div class="clues-heading">Senkrecht ↓</div>
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
