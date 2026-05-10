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

const SYSTEM_PROMPT = `Du bist ein freundlicher Redakteur für deutschsprachige Kreuzworträtsel.
Du gibst AUSSCHLIESSLICH valides JSON zurück – kein weiterer Text, keine Markdown-Blöcke.`;

// Build a prompt from user-supplied settings
function buildUserPrompt(settings = {}) {
  const name          = (settings.name        || 'Maria').trim();
  const topics        = Array.isArray(settings.topics) && settings.topics.length
                          ? settings.topics
                          : ['Klassische Musik', 'Bayern & Heimat', 'Kochen & Backen', 'Natur & Blumen'];
  const customContext = (settings.customContext || '').trim();

  const topicBullets = topics.map(t => `• ${t}`).join('\n');
  const contextLine  = customContext ? `\nPersönlicher Hinweis: ${customContext}` : '';

  return `Erstelle eine Liste von 24 deutschen Wörtern mit Rätselfragen für ein Kreuzworträtsel.
Das Rätsel ist für Oma ${name}, die leichte Demenz hat – alles soll sehr leicht, vertraut und positiv sein.${contextLine}

Themen (bitte gut mischen):
${topicBullets}

Regeln:
- Wörter: 4–9 Buchstaben
- NUR Großbuchstaben, KEINE Umlaute (ä→AE, ö→OE, ü→UE, ß→SS)
- Nur sehr bekannte, einfache Begriffe
- Rätselfragen: kurz, freundlich, auf Deutsch
- Bevorzuge Wörter mit häufigen Buchstaben (E, N, R, S, T, A, I) für gute Vernetzung im Gitter

Antwortformat (NUR dieses JSON, alle 24 Wörter):
{
  "title": "Passender kurzer Rätseltitel",
  "words": [
    { "word": "MOZART", "clue": "Großer Komponist aus Salzburg" },
    { "word": "TULPE",  "clue": "Bunte Frühlingsblume" }
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
    const userPrompt = buildUserPrompt(settings);
    let wordObjects = null;
    let title = 'Bayerisches Rätsel';

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

    res.json({ title, ...result });
  } catch (err) {
    console.error('Fehler:', err);
    res.status(500).json({ error: err.message });
  }
});

// ---------------------------------------------------------------------------
// Standalone HTML renderer for Puppeteer
// ---------------------------------------------------------------------------

function generatePuzzleHTML(puzzleData, showSolution = false, omaName = 'Maria') {
  const { title, gridWidth, gridHeight, words } = puzzleData;

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

  // Cell size in px (for the PDF grid)
  const CELL = 36;

  // Grid cells
  let gridCells = '';
  for (let r = 0; r < gridHeight; r++) {
    for (let c = 0; c < gridWidth; c++) {
      const letter = grid[r][c];
      if (letter === null) {
        gridCells += `<div class="cell black"></div>`;
      } else {
        const num = numMap[`${r},${c}`];
        const letterHtml = showSolution
          ? `<span class="letter sol">${letter}</span>`
          : `<span class="letter"></span>`;
        gridCells += `<div class="cell">${num ? `<span class="num">${num}</span>` : ''}${letterHtml}</div>`;
      }
    }
  }

  // Clues
  const across = words.filter(w => w.direction === 'across').sort((a, b) => a.number - b.number);
  const down   = words.filter(w => w.direction === 'down').sort((a, b) => a.number - b.number);

  const clueList = arr => arr.map(w =>
    `<div class="clue"><span class="cn">${w.number}</span><span class="ct">${w.clue.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span></div>`
  ).join('');

  const gridW = gridWidth  * CELL;
  const gridH = gridHeight * CELL;

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
    min-height: 297mm;
    padding: 12mm 14mm 12mm;
  }
  /* Masthead */
  .masthead {
    background: #c8102e;
    color: white;
    text-align: center;
    padding: 9px 16px 7px;
    margin-bottom: 14px;
  }
  .masthead-eyebrow {
    font-size: 10px;
    letter-spacing: 5px;
    text-transform: uppercase;
    opacity: 0.85;
    margin-bottom: 2px;
  }
  .masthead h1 {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 34px;
    font-weight: 900;
    letter-spacing: 2px;
    line-height: 1;
    margin-bottom: 3px;
  }
  .masthead-sub {
    font-size: 10px;
    letter-spacing: 3px;
    text-transform: uppercase;
    opacity: 0.88;
  }
  /* Title bar */
  .title-bar {
    display: flex;
    align-items: baseline;
    gap: 12px;
    border-bottom: 3px double #333;
    padding-bottom: 6px;
    margin-bottom: 14px;
  }
  .title-bar h2 {
    font-family: Georgia, serif;
    font-size: 18px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .title-dedication {
    margin-left: auto;
    font-size: 12px;
    font-style: italic;
    color: #555;
  }
  /* Layout – grid full width, clues in two columns below */
  .layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .grid-col { flex-shrink: 0; }
  .clues-below {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 28px;
    align-items: start;
    width: 100%;
  }
  /* Grid */
  .grid {
    display: grid;
    grid-template-columns: repeat(${gridWidth}, ${CELL}px);
    border: 3px solid #111;
    border-right: none;
    border-bottom: none;
    width: ${gridW}px;
  }
  .cell {
    width: ${CELL}px;
    height: ${CELL}px;
    border-right: 1.5px solid #555;
    border-bottom: 1.5px solid #555;
    position: relative;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .cell.black { background: #111; border-color: #111; }
  .num {
    position: absolute;
    top: 2px; left: 2px;
    font-size: 7.5px;
    font-weight: 700;
    line-height: 1;
    color: #222;
  }
  .letter {
    font-size: 16px;
    font-weight: 900;
    color: #111;
    line-height: 1;
  }
  .letter.sol { color: #c8102e; }
  /* Clues */
  .clues-section { margin-bottom: 16px; }
  .clues-heading {
    font-size: 9px;
    font-weight: 900;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #c8102e;
    border-bottom: 2px solid #c8102e;
    padding-bottom: 3px;
    margin-bottom: 8px;
  }
  .clue {
    display: flex;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 13px;
    line-height: 1.3;
  }
  .cn { font-weight: 900; min-width: 17px; text-align: right; color: #c8102e; flex-shrink: 0; }
  .ct { flex: 1; }
  /* Footer */
  .footer {
    margin-top: 20px;
    border-top: 2px solid #c8102e;
    padding-top: 8px;
    text-align: center;
    font-size: 12px;
    font-style: italic;
    color: #666;
  }
</style>
</head>
<body>
<div class="page">

  <div class="masthead">
    <div class="masthead-eyebrow">✦ &nbsp; Omas Rätselheft &nbsp; ✦</div>
    <h1>Kreuzworträtsel</h1>
    <div class="masthead-sub">Bayerische Ausgabe &nbsp;·&nbsp; Klassische Musik &amp; Genuss</div>
  </div>

  <div class="title-bar">
    <h2>${title}</h2>
    <span class="title-dedication">Für Oma ${omaName} ♥</span>
  </div>

  <div class="layout">
    <div class="grid-col">
      <div class="grid">${gridCells}</div>
    </div>
    <div class="clues-below">
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
    ${showSolution ? '✦ Lösungsblatt ✦' : `Viel Spaß beim Rätseln, liebe Oma ${omaName}! ♥`}
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
  const { puzzleData, solution = false, omaName = 'Maria' } = req.body;
  if (!puzzleData) return res.status(400).json({ error: 'puzzleData fehlt' });

  let browser, page;
  try {
    browser = await getBrowser();
    page    = await browser.newPage();

    await page.setContent(generatePuzzleHTML(puzzleData, solution, omaName), { waitUntil: 'networkidle0' });

    const pdfRaw = await page.pdf({
      format: 'A4',
      printBackground: true,
      margin: { top: '0mm', right: '0mm', bottom: '0mm', left: '0mm' },
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
  const { puzzleData, solution = false, omaName = 'Maria' } = req.body;
  if (!puzzleData) return res.status(400).json({ error: 'puzzleData fehlt' });

  let browser, page;
  try {
    browser = await getBrowser();
    page    = await browser.newPage();
    await page.setViewport({ width: 794, height: 1123, deviceScaleFactor: 2 });

    await page.setContent(generatePuzzleHTML(puzzleData, solution, omaName), { waitUntil: 'networkidle0' });

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
