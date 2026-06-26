# ✦ Kreuzwort-Generator für Oma

> KI-gestützte Schwedenrätsel — personalisiert, mehrsprachig, druckfertig.

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2Faeltai%2Foma-kreuzwort-generator&env=ANTHROPIC_API_KEY&envDescription=Dein%20Anthropic%20API-Key&envLink=https%3A%2F%2Fconsole.anthropic.com%2F&project-name=oma-kreuzwort-generator)

---

## Was ist das?

Ein Web-App-Generator für liebevoll gestaltete **Schwedenrätsel** (Swedish-style crosswords) — erstellt mit [Claude AI](https://anthropic.com). Perfekt als Geschenk für Oma, Opa oder jeden Rätselliebhaber.

**Funktionen:**
- 🤖 KI-generierte Wörter und Hinweise (Anthropic Claude)
- 🌍 7 Sprachen: Deutsch, Spanisch, Italienisch, Türkisch, Russisch, Griechisch, Arabisch
- 💌 Personalisierung: Vorname, Themen, Familiengeschichte
- 🩺 Gesundheitsprofile (Demenz, Sehschwäche, Parkinson u.a.)
- 🔑 Verstecktes Lösungswort im Schwedenstil
- 🖨️ Export als PDF oder PNG (druckfertig)

## Schnellstart

### 1. Lokal starten

```bash
git clone https://github.com/aeltai/oma-kreuzwort-generator
cd oma-kreuzwort-generator
npm install
ANTHROPIC_API_KEY=sk-ant-... node server.js
# → http://localhost:3000
```

### 2. Auf Vercel deployen

Klick auf den Button oben — dann nur noch den `ANTHROPIC_API_KEY` als Environment Variable eintragen.

Der Code enthält bereits `vercel.json` und den Serverless-Einstiegspunkt in `api/index.js`.

## Umgebungsvariablen

| Variable | Beschreibung |
|---|---|
| `ANTHROPIC_API_KEY` | API-Key von [console.anthropic.com](https://console.anthropic.com/) |

## Sprachen & Schriften

| Code | Sprache | Schrift | Richtung |
|---|---|---|---|
| `de` | Deutsch | Latein (mit Umlauten) | LTR |
| `es` | Español | Latein | LTR |
| `it` | Italiano | Latein | LTR |
| `tr` | Türkçe | Latein (mit ç, ğ, ı, ö, ş, ü) | LTR |
| `ru` | Русский | Kyrillisch | LTR |
| `el` | Ελληνικά | Griechisch | LTR |
| `ar` | العربية | Arabisch | RTL |

## Tech-Stack

- **Backend:** Node.js + Express
- **KI:** Anthropic Claude (claude-opus-4-5)
- **PDF/PNG:** Puppeteer (Headless Chrome)
- **Hosting:** Vercel (Serverless) oder lokal

## Lizenz

MIT
