# Label-Assets: EU-Originale

Das Plugin enthält die **offiziellen EU-Originale** des
Gewährleistungslabels für **de-DE** und **en-GB** (eingespielt am
12.07.2026, siehe „Herkunft der enthaltenen Dateien"). Weitere Sprachen
werden wie unten beschrieben ergänzt.

## Rechtlicher Rahmen

Layout und Text des Gewährleistungslabels sind durch die
**Durchführungsverordnung (EU) 2025/1960, Anhang I** exakt vorgegeben.
Das Label darf **niemals nachgebaut, verändert oder umgefärbt** werden.
Erlaubt ist ausschließlich **proportionale Skalierung**.

## Bezugsquelle

Die Original-Dateien stellt die EU-Kommission ergänzend zur Verordnung
bereit:

- **Download-Seite der EU-Kommission** (ZIP „Harmonised notice in 24
  languages", Farbe + Schwarz-Weiß, alle EU-Sprachen; außerdem das
  GARAN-Label für Phase 2 und die Practical Guidelines):
  <https://commission.europa.eu/publications/practical-guidelines-and-high-resolution-vector-files-eu-notice-and-label-product-guarantees_en>
- Durchführungsverordnung (EU) 2025/1960 im EUR-Lex-Portal:
  <https://eur-lex.europa.eu/legal-content/DE/TXT/?uri=CELEX:32025R1960>

## Herkunft der enthaltenen Dateien

Die eingecheckten Dateien wurden am **12.07.2026** aus dem offiziellen
Kommissions-ZIP `Harmonised notice in 24 languages colour and black and
white_0.zip` übernommen (`Legal guarantee_notice DEN.pdf` bzw. `… ENG.pdf`,
je 2 Seiten: Seite 1 Farbe, Seite 2 Schwarz-Weiß):

- **PDF**: Seite 1 (Farbversion — nur diese ist für E-Commerce zulässig)
  wurde unverändert als einseitige PDF extrahiert (PyMuPDF `insert_pdf`,
  Vektordaten byte-identisch übernommen). Die S/W-Seite ist nur für den
  stationären Handel gedacht und daher nicht enthalten.
- **SVG**: verlustfreie Vektor-Konvertierung derselben Seite 1 via PyMuPDF
  (`get_svg_image`, Text als Pfade — keine Font-Abhängigkeiten, exakte
  Darstellung). Keine inhaltliche Änderung.

## Ablageort und Dateinamen (exakt einhalten)

```
src/Resources/public/labels/
├── de-DE/
│   ├── gewaehrleistungslabel.svg   # Storefront-Darstellung
│   └── gewaehrleistungslabel.pdf   # Mail-Anhang (dauerhafter Datenträger)
└── en-GB/
    ├── gewaehrleistungslabel.svg
    └── gewaehrleistungslabel.pdf
```

- **PDF**: die Farbseite des **unveränderten EU-Originals** der jeweiligen
  Sprachfassung (Anhang I der DVO).
- **SVG**: wird aus dem EU-Original **ohne inhaltliche Änderung**
  konvertiert (z. B. per PyMuPDF oder Inkscape aus dem Original-PDF).
  Farben, Proportionen und Text müssen exakt dem Original entsprechen.

## GARAN-Vorlagen (Garantielabel)

Die GARAN-Vorlagen unter `src/Resources/garan-templates/` stammen aus dem
Kommissions-ZIP `GARAN label for website.zip` (gleiche Download-Seite wie
oben):

- `garan-colour.svg` — aus `GARAN Label_colour.svg`. **Einzige Änderung:**
  Der mitgelieferte Dummy-QR-Code wurde entfernt und durch die leere
  Zielgruppe `<g id="hug-garan-qr"/>` ersetzt; dort setzt das Plugin zur
  Laufzeit den echten QR-Code (Ziel: Garantiebedingungen) ein. Die
  Aufbereitung ist reproduzierbar über `bin/prepare-garan-templates.py`.
- `garan-nested.svg` — unveränderte Kopie von
  `GARAN Label_nested display.svg` (offizielle Kompaktvariante für
  E-Commerce).

Weitere Änderungen an den Vorlagen sind unzulässig — die DVO erlaubt nur
das Befüllen der vorgesehenen Felder (Marke, Modell-Kennung, Dauer,
QR-Code).

## Weitere Sprachen ergänzen (ohne Code-Änderung)

Für jede weitere Sales-Channel-Sprache einen Ordner mit dem Locale-Code
anlegen und die beiden Dateien der passenden EU-Sprachfassung ablegen:

```
src/Resources/public/labels/
└── en-GB/
    ├── gewaehrleistungslabel.svg
    └── gewaehrleistungslabel.pdf
```

Fehlt eine Sprachfassung, fällt das Plugin automatisch auf `de-DE` zurück.

## Nach dem Einspielen neuer Sprachen

```bash
# Vom Shopware-Root:
bin/console assets:install
bin/console cache:clear
```

Danach in der Storefront prüfen, dass das Label der neuen Sprache angezeigt
wird (siehe „Manueller Testplan" im README).
