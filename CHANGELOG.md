# Changelog

Alle nennenswerten Änderungen an diesem Plugin werden in dieser Datei dokumentiert.

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/).

## [1.4.1] - 2026-07-13

### Hinzugefügt

- Lizenz: Das Plugin steht jetzt unter der **GPL-3.0-or-later** (vorher „proprietary" ohne Lizenztext); LICENSE-Datei mit dem GPL-Volltext ergänzt
- README: Abschnitte zu KI-gestützter Entwicklung (Agentic Coding mit Claude Code), Haftungsausschluss (keine Rechtsberatung, keine Gewährleistung gemäß GPL §§ 15–16) und Lizenz, plus Kurzhinweis dazu direkt am Seitenanfang
- composer.json: `homepage` und `support` verweisen auf das GitHub-Repository (https://github.com/Hotte512/HugEuLabel)

### Geändert

- README/ASSETS: Installations- und Wartungsbefehle umgebungsneutral formuliert (generisches `bin/console` statt DDEV-spezifischer Aufrufe) — Vorbereitung der Veröffentlichung auf GitHub
- README-Konfigurationsreferenz auf Stand gebracht: `gpsrMaxWidth`/`gpsrFontSize` (aus 1.4.0) und `gpsrCustomSelector`/`gpsrCustomInsert` samt erweiterter `gpsrPosition`-Optionen (aus 1.3.0) ergänzt, entfallenes `gpsrCombinedLayout` entfernt

## [1.4.0] - 2026-07-13

### Hinzugefügt

- `gpsrFontSize`: Schriftgröße der GPSR-Angaben in px (leer/0 = Standard 14 px); Überschriften und Text skalieren gemeinsam — gegen unschöne Zeilenumbrüche bei schmaler Box
- `gpsrMaxWidth`: maximale Breite der GPSR-Box in px (leer/0 = automatisch) — z. B. um sie im Nebeneinander-Layout auf die Breite der Labels zu begrenzen

### Behoben

- Nebeneinander-Layout (`slotLayout: side_by_side`): Mit allen drei Blöcken brach der GPSR-Block in eine zweite Zeile um — seine Mindest-Flexbreite ließ neben zwei Labels keinen Platz; jetzt teilen sich alle drei eine Zeile und GPSR wächst auf den Restplatz

### Geändert

- Platzhalter-Feld „Warnhinweise je Produkt (Phase B)" aus der GPSR-Konfiguration entfernt — die Config zeigt nur noch funktionale Optionen. Produktbezogene Warnhinweise (Art. 19 lit. d GPSR) bleiben als mögliche spätere Erweiterung im README vermerkt

## [1.3.0] - 2026-07-12

### Hinzugefügt

- Breite der Kompakt-Banner konfigurierbar: `compactWidth` (zugeklappter EU-Label-Kopf) und `garanNestedWidth` (GARAN-Nested-Banner)
- `slotLayout`: Stehen mehrere Blöcke (Gewährleistungslabel, GARAN, GPSR) am selben Anker, sind sie wahlweise untereinander oder nebeneinander angeordnet (bricht auf schmalen Bildschirmen um); ersetzt `gpsrCombinedLayout`, gespeicherte Alt-Werte gelten als Fallback weiter
- GPSR-Angaben sind jetzt so frei positionierbar wie die Labels: `gpsrPosition` um „Bei den Versandinformationen", „Am Seitenende" und „Benutzerdefiniert (CSS-Selektor)" erweitert (eigene Felder `gpsrCustomSelector`/`gpsrCustomInsert`)
- Eigene Label-Route `/hug-garan-label/{productId}/{variant}`: Das volle GARAN-Label (~280 KB SVG) wird als browser-cachebares `<img>` geladen statt inline gerendert — die Produktseite bleibt leicht; ETag/304-Revalidierung spart Folgetransfers

### Geändert

- Anzeige-Name des Plugins: „Produkt-Compliance: EU-Label, GARAN & GPSR" (technischer Name/Namespace unverändert)

## [1.2.0] - 2026-07-12

### Hinzugefügt

- **GARAN-Garantielabel** (DVO (EU) 2025/1960, Anhang II) für gewerbliche Haltbarkeitsgarantien über 2 Jahre: offizielle EU-Vorlagen werden pro Produkt serverseitig befüllt (Marke, Modell-Kennung mit konfigurierbarer Quelle, Dauer, QR-Code zu den Garantiebedingungen) und als Inline-SVG ausgeliefert (gecacht, IDs pro Render eindeutig)
- Datenpflege über Zusatzfelder am Hersteller (Standard) und Produkt (Abweichungen/Deaktivieren); unvollständige Daten unterdrücken das Label mit Log-Warnung, Garantien bis 2 Jahre bleiben still
- GARAN-Anzeigeflächen, alle pro Sales Channel konfigurierbar: PDP (volles Label oder aufklappbare Nested-Kompaktvariante, alle Positionen inkl. eigenem CSS-Selektor), Bestellabschluss pro Bestellposition, Listing-Produktkacheln
- GARAN-Mail-Anhänge (`garanMailMode`): gepflegte Garantiebedingungs-PDFs (dedupliziert) und/oder generierte `Garantie-Uebersicht.pdf` (dompdf)
- Neue Laufzeit-Abhängigkeit `bacon/bacon-qr-code` ^3.0 (Shop-Root) für die QR-Erzeugung

### Geändert

- Die Platzhalter-Card „Garantielabel GARAN (Phase 2)" wurde durch die funktionale GARAN-Konfiguration ersetzt
- Bestellbestätigungs-Typ-Prüfung in gemeinsamen Service extrahiert (EU-Label- und GARAN-Mail-Subscriber)

## [1.1.0] - 2026-07-12

### Hinzugefügt

- **GPSR-Pflichtangaben auf der Produktdetailseite** (Verordnung (EU) 2023/988, Art. 19; gilt seit 13.12.2024): Herstellerangaben (Name, Anschrift, elektronische Adresse) und optional die EU-verantwortliche Person werden je Hersteller in Zusatzfeldern gepflegt und direkt auf der PDP angezeigt — separat aktivierbar (`gpsrActive`), unabhängig vom EU-Gewährleistungslabel
- Custom Field Set `hug_gpsr` am Hersteller (mehrzeilige Textfelder `hug_gpsr_manufacturer_info`, `hug_gpsr_responsible_person`), angelegt per `plugin:install`/`plugin:update`; bei Deinstallation bleiben die Felder erhalten, außer „Alle App-Daten löschen" ist gewählt
- Globaler Fallback-Text (`gpsrFallbackText`, z. B. eigene Firmendaten als Quasi-Hersteller), wenn der Hersteller keine GPSR-Daten hat oder das Produkt keinen Hersteller hat; ist auch der Fallback leer, wird bewusst nichts angezeigt und nichts geloggt (Rechtsrisiko, siehe README)
- Darstellung wählbar: schlichter Textblock (`plain`) oder dezente Banner-Box (`banner`) mit 5 Farbvarianten (`neutral_grey`, `soft_blue`, `soft_green`, `outline`, `accent` = Theme-Primärfarbe); Farben über CSS Custom Properties `--hug-gpsr-*` themebar, bewusst ohne Siegel-/Warncharakter
- Positionierung: eigene PDP-Position oder `combined` = am EU-Gewährleistungslabel (auch an dessen Sonderpositionen Seitenende/CSS-Selektor/Versandinfos), dort untereinander (`stacked`) oder nebeneinander (`side_by_side`, bricht unter 768 px um); ist das EU-Label ausgeblendet, erscheinen die GPSR-Angaben allein an dessen Position
- Konfigurierbare Überschrift (`gpsrHeadline`, Standard per Snippet übersetzbar) und Zwischenüberschrift „Verantwortliche Person in der EU" als Snippet
- Platzhalter-Feld für Phase B (produktbezogene Warnhinweise, Art. 19 lit. d GPSR) — noch ohne Funktion
- Unit-Tests: Anzeige-Logik (`GpsrProvider`), Custom-Field-Lifecycle (idempotent, Update ergänzt fehlende Felder, keepUserData), Twig-Rendering (XSS-Escaping, `nl2br`, konditionaler Responsible-Person-Block)

### Geändert

- Positions-Mechanik refactored: ein gemeinsamer Positions-Slot (`hug-position-slot.html.twig`) rendert je PDP-Anker die dort aktiven Blöcke (EU-Label und/oder GPSR) — die vier Override-Templates enthalten keine eigenen Positions-Kaskaden mehr; am EU-Label-Verhalten ändert sich nichts
- Der Konfigurations-Schalter `active` heißt jetzt „EU-Gewährleistungslabel aktiv" und steuert ausdrücklich nur die EU-Label-Funktionen; die GPSR-Anzeige hat ihren eigenen Schalter

Außerdem enthält 1.1.0 die folgenden bislang unveröffentlichten EU-Label-Änderungen:

### Behoben

- Kompakt-Darstellung (Aufklappen): Das aufgeklappte Label lässt sich auch per Klick auf das Label selbst wieder einklappen
- Kompakt-Darstellung (Aufklappen): Der Kopf-Banner wird ausgeblendet, solange das volle Label aufgeklappt ist — die Label-Kopfzeile erschien sonst doppelt; der Toggle-Link wechselt dabei auf „Gewährleistungslabel ausblenden"
- Kompakt-Darstellung (Modal): Das Label nutzt jetzt die volle Dialogbreite statt der für die Seite konfigurierten Maximalbreite und ist damit im Modal gut lesbar

- PDP-Positionen `above_buy_button`/`below_buy_button`: Label wird jetzt vor/nach der gesamten Buy-Widget-Zeile eingefügt statt im Button-Block — in Themes mit Flex-Layout wurde es sonst zum Flex-Item neben dem Button und überlappte nachfolgenden Inhalt
- Label-Bild erhält einen weißen Hintergrund, da das EU-Original-SVG keinen gemalten Hintergrund hat und Seiteninhalt sonst durchscheint

### Geändert

- Konfiguration: die vier „anzeigen"-Schalter wurden durch Darstellungs-Auswahlfelder ersetzt (`pdpMode`, `checkoutConfirmMode`, `cartMode`, `offcanvasMode`)
- Platzhalter-Assets durch die offiziellen EU-Originale des Gewährleistungslabels ersetzt (Farbversion aus dem Kommissions-Paket zur DVO (EU) 2025/1960); zusätzlich zur deutschen jetzt auch die englische Sprachfassung (`en-GB`) enthalten

### Hinzugefügt

- Darstellung pro Fläche wählbar (Aus / Volles Label / Kompakt): Kompakt zeigt den Label-Kopf als Banner (CSS-Zuschnitt des unveränderten EU-Originals) und öffnet das volle Label je nach Konfiguration aufklappend oder im Modal; Rechtshinweise direkt an den Config-Feldern (PDP/Bestellabschluss: volles Label verpflichtend, Kompakt auf eigenes Risiko)
- Neue Label-Positionen: PDP bei den Versandinformationen und am Seitenende, Bestellabschluss über dem Bestell-Button, sowie frei per CSS-Selektor (PDP und Bestellabschluss, mit Einfügemodus davor/danach/hinein und Konsolen-Warnung im Fehlerfall)
- EU-Gewährleistungslabel in der Storefront: Produktdetailseite (4 wählbare Positionen), Bestellabschluss-Seite (3 Positionen), optional Warenkorb-Seite und Offcanvas-Warenkorb — vollständig, unmittelbar sichtbar und farbig gemäß Richtlinie (EU) 2024/825 / DVO (EU) 2025/1960
- Automatischer PDF-Anhang `EU-Gewaehrleistungslabel.pdf` an der Bestellbestätigungs-Mail (dauerhafter Datenträger); Fehler verhindern den Mailversand nicht und werden im Log-Channel `hug_eu_label` protokolliert
- Plugin-Konfiguration pro Sales Channel: Aktiv-Schalter, maximale Label-Breite, Anzeige-Positionen, Mail-Modus (`pdf_attachment`/`inline_image`/`disabled`) sowie Platzhalter-Card für das GARAN-Garantielabel (Phase 2)
- Locale-abhängige Label-Auflösung mit Fallback auf de-DE; neue Sprachen nur durch Ablegen neuer Dateien unter `src/Resources/public/labels/{locale}/`
- Platzhalter-Assets (SVG/PDF) und `ASSETS.md` mit Anleitung zum Einspielen der EU-Originale
- Unit-Tests für `LabelProvider` und `MailAttachmentSubscriber`
- Plugin-Grundgerüst und Dev-Tooling (PHPStan, ECS, Rector, PHPUnit, ESLint, Jest) analog zu HugMailCockpit
