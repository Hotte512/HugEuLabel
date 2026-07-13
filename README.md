# HugEuLabel

Shopware-6.7-Plugin (GPL-3.0). Blendet das ab dem
**27.09.2026** verpflichtende **EU-Gewährleistungslabel** in der Storefront ein,
hängt es als PDF an die Bestellbestätigungs-Mail an und zeigt die seit dem
**13.12.2024** verpflichtenden **GPSR-Herstellerangaben** (Verordnung (EU)
2023/988, Art. 19) auf der Produktdetailseite an — beide Funktionen sind
unabhängig voneinander aktivierbar.

> **Kurz vorab:** Dieses Plugin wurde überwiegend **KI-gestützt** entwickelt
> („Agentic Coding" / „Vibe Coding" mit Claude Code) und wird **ohne
> Gewährleistung** bereitgestellt (GPL-3.0) — es ist **keine Rechtsberatung**,
> die Verantwortung für die Rechtskonformität des Shops liegt beim Betreiber.
> Details: [KI-gestützte Entwicklung](#entstehung-ki-gestützte-entwicklung-agentic-coding)
> und [Haftungsausschluss](#haftungsausschluss).

## Rechtsgrundlage (Kurzfassung)

- **Richtlinie (EU) 2024/825** („Empowering Consumers for the Green
  Transition") verpflichtet Händler, Verbraucher mit einem einheitlichen
  Label auf die gesetzliche Gewährleistung hinzuweisen.
- **Durchführungsverordnung (EU) 2025/1960** legt Layout und Text des Labels
  exakt fest (Anhang I). Das Label darf **nicht nachgebaut oder verändert**
  werden — nur proportionale Skalierung ist erlaubt.
- Pflichten ab **27.09.2026**:
  - Das Label muss **vollständig, unmittelbar sichtbar und farbig**
    dargestellt werden — kein Accordion, Modal, Mouseover oder verkürzte
    Vorschau.
  - Es muss dem Verbraucher **vor Vertragsabschluss** zur Kenntnis gebracht
    werden können (Produktseite und/oder Checkout).
  - Nach dem Kauf muss es auf einem **dauerhaften Datenträger** vorliegen —
    PDF-Anhang oder eingebettetes Bild in der Bestellbestätigung. Ein bloßer
    Link genügt **nicht**.

### GPSR (Produktsicherheitsverordnung)

- **Verordnung (EU) 2023/988** (GPSR) gilt seit dem **13.12.2024** unmittelbar.
  **Art. 19** verlangt bei jedem Online-Angebot **eindeutig und gut sichtbar**:
  - (a) Name/Handelsname bzw. Handelsmarke des **Herstellers**, seine
    **Postanschrift** und eine **elektronische Adresse** (E-Mail oder
    Kontakt-URL),
  - (b) bei Herstellern außerhalb der EU zusätzlich die **verantwortliche
    Person in der EU** (Art. 16) mit denselben Angaben,
  - (c) Angaben zur **Identifizierung des Produkts** (macht die
    Produktdetailseite mit Name, Bild und Artikelnummer bereits selbst),
  - (d) ggf. **Warnhinweise/Sicherheitsinformationen** (siehe „Phase B").
- Die Angaben müssen **direkt im Produktangebot** stehen — also auf der
  Produktdetailseite selbst. Impressum, AGB oder externe Links genügen
  **nicht**. Checkout und E-Mails sind nicht gefordert; das Plugin rendert
  die GPSR-Angaben deshalb ausschließlich auf der PDP.
- Anders als beim EU-Gewährleistungslabel gibt es **keine Designvorgabe** —
  die Gestaltung ist frei, darf aber nicht wie ein amtliches EU-Siegel oder
  Prüfzeichen wirken. Die Banner-Darstellung des Plugins ist deshalb bewusst
  dezent gehalten (kein Logo, keine Icons, keine Signalfarben).

## Anforderungen

- Shopware ≥ 6.7 (`shopware/core: ~6.7.0`)
- PHP ≥ 8.2
- `bacon/bacon-qr-code` ^3.0 im **Shop-Root** (`composer require
  bacon/bacon-qr-code:^3.0`) — QR-Code-Erzeugung für das GARAN-Label

## Installation

Das Plugin-Verzeichnis nach `custom/plugins/HugEuLabel` kopieren (bzw. dort
klonen), dann vom Shopware-Root aus:

```bash
composer require bacon/bacon-qr-code:^3.0
bin/console plugin:refresh
bin/console plugin:install --activate HugEuLabel
bin/console assets:install
bin/console cache:clear
```

(In Container-Umgebungen wie DDEV/Docker die Befehle entsprechend im
Container ausführen, z. B. `ddev exec bin/console …`.)

`assets:install` kopiert die Label-Dateien nach
`public/bundles/hugeulabel/labels/`.

Das Plugin enthält die **offiziellen EU-Originale** des Labels für
**de-DE** und **en-GB**. Herkunft, Konvertierung und das Ergänzen weiterer
Sprachen sind in [ASSETS.md](ASSETS.md) dokumentiert.

## Konfiguration

Alle Optionen unter *Einstellungen → System → Plugins → EU-Label* bzw.
`HugEuLabel.config.*`, **pro Sales Channel überschreibbar**.

### Darstellungs-Modi und Kompakt-Darstellung

Jede Anzeigefläche hat ein Auswahlfeld **Darstellung**: `hidden` (Aus),
`full` (volles Label) oder `compact`. Die Kompakt-Darstellung zeigt nur den
**Label-Kopf** als Banner — ein reiner CSS-Zuschnitt des unveränderten
EU-Original-SVGs — und öffnet das volle Label per Klick, je nach
`compactBehavior` aufklappend (Bootstrap Collapse) oder im Modal.

> **Rechtshinweis:** Auf Produktdetailseite und Bestellabschluss-Seite muss
> das Label ab dem 27.09.2026 vollständig, unmittelbar sichtbar und farbig
> dargestellt werden. Die Kompakt-Darstellung erfüllt das nach herrschender
> Lesart **nicht** und erfolgt dort auf eigenes Risiko. Auf den freiwilligen
> Flächen (Warenkorb-Seite, Offcanvas) ist sie unbedenklich.

### Allgemein

| Schlüssel         | Typ           | Standard   | Beschreibung |
|-------------------|---------------|------------|--------------|
| `active`          | bool          | `true`     | Plugin global aktiv/inaktiv |
| `maxWidth`        | int           | `300`      | Maximale Label-Breite in px (nur proportionale Skalierung) |
| `compactBehavior` | single-select | `collapse` | `collapse` (aufklappen) oder `modal` — gilt überall, wo Kompakt gewählt ist |
| `compactWidth`    | int           | `300`      | Breite des zugeklappten EU-Label-Kopf-Banners in px |
| `slotLayout`      | single-select | `stacked`  | Anordnung mehrerer Blöcke am selben Anker: `stacked` / `side_by_side` (Reihenfolge: Gewährleistung, GARAN, GPSR) |

### Produktdetailseite

| Schlüssel           | Typ           | Standard           | Optionen |
|---------------------|---------------|--------------------|----------|
| `pdpMode`           | single-select | `full`             | `hidden`, `full`, `compact` |
| `pdpPosition`       | single-select | `below_buy_button` | `above_buy_button`, `below_buy_button`, `below_description`, `tab_description_end`, `near_shipping_info`, `page_end`, `custom_selector` |
| `pdpCustomSelector` | text          | leer               | CSS-Selektor für `custom_selector` (z. B. `.product-detail-price-container`) |
| `pdpCustomInsert`   | single-select | `after`            | `before`, `after`, `append` |

### Warenkorb & Checkout

| Schlüssel                 | Typ           | Standard      | Optionen |
|---------------------------|---------------|---------------|----------|
| `checkoutConfirmMode`     | single-select | `full`        | `hidden`, `full`, `compact` |
| `checkoutConfirmPosition` | single-select | `above_terms` | `above_terms`, `below_summary`, `below_line_items`, `above_submit`, `custom_selector` |
| `confirmCustomSelector`   | text          | leer          | CSS-Selektor für `custom_selector` |
| `confirmCustomInsert`     | single-select | `after`       | `before`, `after`, `append` |
| `cartMode`                | single-select | `hidden`      | `hidden`, `full`, `compact` |
| `offcanvasMode`           | single-select | `hidden`      | `hidden`, `full`, `compact` |

Bei `custom_selector` wird das Label unsichtbar gerendert und clientseitig
an den konfigurierten Selektor verschoben. Wird das Ziel nicht gefunden
(oder ist der Selektor leer), bleibt das Label unsichtbar und eine Warnung
erscheint in der Browser-Konsole — die Seite bricht nie.

### Bestellbestätigung (E-Mail)

| Schlüssel  | Typ           | Standard         | Optionen |
|------------|---------------|------------------|----------|
| `mailMode` | single-select | `pdf_attachment` | `pdf_attachment`, `inline_image`, `disabled` |

- **`pdf_attachment`** (empfohlen): Das Plugin hängt das Label automatisch als
  `EU-Gewaehrleistungslabel.pdf` an jede Mail vom Typ
  `order_confirmation_mail` an. Fehlt die PDF-Datei, geht die Mail trotzdem
  raus; der Fehler wird im Log-Channel `hug_eu_label` protokolliert.
- **`inline_image`**: Das Plugin verändert das Mail-Template bewusst
  **nicht** programmatisch (Template-Overrides von Mail-Templates sind
  fragil). Der Shop-Betreiber fügt das Bild manuell in das Mail-Template
  `order_confirmation_mail` ein (Admin → Einstellungen → E-Mail-Templates),
  z. B.:

  ```twig
  <img src="{{ asset('bundles/hugeulabel/labels/de-DE/gewaehrleistungslabel.svg', 'asset') }}"
       alt="EU-Gewährleistungslabel" width="300">
  ```

  Hinweis: Viele Mail-Clients blockieren SVG — für Mails empfiehlt sich eine
  PNG-Variante (aus dem EU-Original konvertiert, siehe ASSETS.md). Damit die
  Anforderung „dauerhafter Datenträger" erfüllt ist, muss das Bild
  eingebettet bzw. per absoluter URL dauerhaft abrufbar sein; rechtlich
  sicherer ist `pdf_attachment`.
- **`disabled`**: kein Anhang (nur sinnvoll, wenn die Pflicht anderweitig
  erfüllt wird).

### GPSR-Herstellerangaben

| Schlüssel            | Typ           | Standard       | Beschreibung |
|----------------------|---------------|----------------|--------------|
| `gpsrActive`         | bool          | `false`        | GPSR-Anzeige aktiv — unabhängig vom EU-Label-Schalter `active` |
| `gpsrDisplayMode`    | single-select | `banner`       | `plain` (schlichter Textblock) oder `banner` (dezente Box) |
| `gpsrBannerTheme`    | single-select | `neutral_grey` | `neutral_grey`, `soft_blue`, `soft_green`, `outline`, `accent` (Theme-Primärfarbe) |
| `gpsrPosition`       | single-select | `combined`     | `combined` (am EU-Label), `above_buy_button`, `below_buy_button`, `below_description`, `tab_description_end`, `near_shipping_info`, `page_end`, `custom_selector` |
| `gpsrCustomSelector` | text          | leer           | CSS-Selektor für `custom_selector` (eigenes Feld, unabhängig vom EU-Label) |
| `gpsrCustomInsert`   | single-select | `after`        | `before`, `after`, `append` |
| `gpsrMaxWidth`       | int           | leer           | Maximale Breite der GPSR-Box in px; leer/0 = automatisch (nutzt den verfügbaren Platz) — z. B. um die Box auf die Breite der Labels zu begrenzen |
| `gpsrFontSize`       | int           | leer           | Schriftgröße der GPSR-Angaben in px; leer/0 = Standard (14 px). Überschriften und Text skalieren gemeinsam |
| `gpsrFallbackText`   | textarea      | leer           | Globaler Fallback, wenn der Hersteller keine GPSR-Daten hat (z. B. eigene Firmendaten als Quasi-Hersteller) |
| `gpsrHeadline`       | text          | leer           | Überschrift des Blocks; leer = Snippet-Standard „Herstellerinformationen gemäß Produktsicherheitsverordnung (GPSR)" (`hugEuLabel.gpsr.headline`) |

Details zur Datenpflege und Anzeige-Logik im Abschnitt
[GPSR-Herstellerangaben pflegen](#gpsr-herstellerangaben-pflegen).

### Garantielabel GARAN (Phase 2)

Platzhalter-Card ohne Funktion. Das GARAN-Label für gewerbliche
Haltbarkeitsgarantien wird in einer späteren Plugin-Version ergänzt.

## GARAN-Garantielabel (gewerbliche Haltbarkeitsgarantien)

Das GARAN-Label (DVO (EU) 2025/1960, Anhang II) kennzeichnet **gewerbliche
Haltbarkeitsgarantien über 2 Jahre**. Das Plugin befüllt die offiziellen
EU-Vorlagen pro Produkt serverseitig: Marke (Herstellername), Modell-Kennung
(Quelle konfigurierbar), Garantiedauer und QR-Code zu den
Garantiebedingungen. Es gibt das **volle Label** und die offizielle
**Nested-Kompaktvariante** (auf der PDP aufklappbar zum vollen Label).

### Datenpflege

Am **Hersteller** (Zusatzfelder-Set „GARAN-Garantie (Hersteller-Standard)"):
`hug_garan_active`, `hug_garan_duration_years`, `hug_garan_conditions_url`,
`hug_garan_conditions_media` (PDF-Fallback zur URL). Am **Produkt**
(„GARAN-Garantie (Produkt-Abweichungen)"): `hug_garan_disabled` sowie
`hug_garan_product_duration_years` / `…_conditions_url` / `…_conditions_media`
als Overrides — damit kann ein Produkt abweichen oder auch ohne
Hersteller-Garantie eigene Garantiedaten tragen.

**Aktivierungsregel:** Das Label erscheint nur, wenn `garanEnabled` an ist,
das Produkt nicht deaktiviert wurde, die wirksame Dauer **> 2 Jahre** beträgt
und Bedingungen (URL oder PDF) gepflegt sind. Unvollständige Daten
unterdrücken das Label mit Warnung im Log-Channel `hug_eu_label`; Garantien
bis 2 Jahre brauchen kein Label und bleiben still.

### Konfiguration (Card „Garantielabel GARAN", pro Sales Channel)

| Schlüssel | Typ | Standard | Optionen |
|---|---|---|---|
| `garanEnabled` | bool | `false` | — |
| `garanPdpMode` | single-select | `full` | `hidden`, `full`, `nested` (aufklappbar) |
| `garanPdpPosition` | single-select | `below_buy_button` | wie `pdpPosition` inkl. `custom_selector` |
| `garanPdpCustomSelector` / `garanPdpCustomInsert` | text / select | leer / `after` | eigener Selektor, unabhängig vom EU-Label |
| `garanNestedWidth` | int | `368` | Breite des Nested-Banners in px |
| `garanConfirmMode` | single-select | `nested` | `hidden`, `nested` (pro Bestellposition) |
| `garanListingMode` | single-select | `hidden` | `hidden`, `nested` (in der Produktkachel) |
| `garanModelIdSource` | single-select | `product_number` | `product_number`, `manufacturer_number`, `ean` (leer ⇒ Produktnummer) |
| `garanMailMode` | single-select | `conditions_pdfs` | `disabled`, `conditions_pdfs`, `summary_pdf`, `both` |

### Mail-Anhänge

`conditions_pdfs` hängt die als PDF gepflegten Garantiebedingungen der
bestellten Produkte an (pro Datei dedupliziert; nur wo ein Media-PDF
existiert). `summary_pdf` erzeugt die Übersicht `Garantie-Uebersicht.pdf`
(je Position Artikel, Hersteller, Dauer, Bedingungs-Link; via dompdf).
Fehler verhindern nie den Mailversand.

## GPSR-Herstellerangaben pflegen

Die GPSR-Daten werden **je Hersteller** gepflegt: *Admin → Kataloge →
Hersteller → (Hersteller öffnen) → Zusatzfelder → Karte „GPSR
(Produktsicherheitsverordnung)"*. Das Custom Field Set `hug_gpsr` legt das
Plugin bei `plugin:install`/`plugin:update` automatisch an; bei der
Deinstallation bleiben Set und Daten erhalten, außer „Alle App-Daten
löschen" wird gewählt.

| Zusatzfeld                     | Inhalt |
|--------------------------------|--------|
| `hug_gpsr_manufacturer_info`   | Name/Handelsname, Postanschrift und elektronische Adresse des Herstellers — Zeilenumbrüche bleiben in der Storefront erhalten |
| `hug_gpsr_responsible_person`  | Nur bei Nicht-EU-Herstellern: verantwortliche Person in der EU (Art. 16 GPSR); erscheint mit eigener Zwischenüberschrift **nur zusammen mit** den Herstellerangaben |

Die Felder sind übersetzbar (Sprachumschalter am Hersteller); die Storefront
zeigt die Übersetzung der jeweiligen Sales-Channel-Sprache.

**Anzeige-Logik** (bei `gpsrActive`):

1. Hersteller hat `hug_gpsr_manufacturer_info` → wird angezeigt, ggf. mit
   verantwortlicher Person darunter.
2. Sonst (keine Daten oder Produkt ohne Hersteller) → `gpsrFallbackText`,
   sofern gefüllt.
3. Sonst → **es wird nichts angezeigt** — bewusst auch **ohne Log-Eintrag**
   (ein Eintrag pro Seitenaufruf wäre Log-Spam).

> **Rechtsrisiko:** Fall 3 bedeutet, dass ein Produkt **ohne die nach
> Art. 19 GPSR verpflichtenden Angaben** angeboten wird (abmahnfähig).
> Entweder alle Hersteller mit GPSR-Daten versorgen oder einen globalen
> Fallback-Text hinterlegen — und neu angelegte Hersteller in die
> Pflege-Routine aufnehmen.

**Positionierung:** Standard ist `combined` — die GPSR-Angaben erscheinen am
EU-Gewährleistungslabel (untereinander oder nebeneinander), und zwar auch an
dessen Sonderpositionen (`page_end`, `custom_selector`, `near_shipping_info`).
Ist das EU-Label ausgeblendet (`pdpMode: hidden`), erscheinen die
GPSR-Angaben allein an der als `pdpPosition` konfigurierten Stelle.
Alternativ bekommt der Block über `gpsrPosition` eine eigene Position.
Die GPSR-Angaben erscheinen **nur auf der Produktdetailseite** (nicht im
Checkout, nicht in E-Mails) — das entspricht der Anforderung „im
Produktangebot".

**Darstellung:** Die Banner-Farben laufen über CSS Custom Properties
(`--hug-gpsr-bg`, `--hug-gpsr-bar`, `--hug-gpsr-border`, `--hug-gpsr-color`)
und lassen sich im Theme überschreiben. Alle Varianten sind auf
WCAG-AA-Kontrast ausgelegt (dunkler Fließtext auf sehr hellen Flächen).

### Phase B (geplant): Warnhinweise je Produkt

Produktbezogene **Warnhinweise und Sicherheitsinformationen**
(Art. 19 lit. d GPSR) folgen bei Bedarf in einer späteren Version —
vorgesehen als Zusatzfelder am Produkt. Relevant, sobald das Sortiment
Produkte mit Pflicht-Warnhinweisen enthält.

## Mehrsprachigkeit der Label-Dateien

Das Plugin wählt die Label-Datei anhand des Locale-Codes der
Sales-Channel-Sprache: `src/Resources/public/labels/{locale}/`. Existiert für
eine Sprache kein Ordner bzw. keine Datei, wird auf **de-DE** zurückgefallen.
Weitere Sprachen werden **ohne Code-Änderung** ergänzt, indem ein neuer
Locale-Ordner (z. B. `en-GB/`) mit `gewaehrleistungslabel.svg` und
`gewaehrleistungslabel.pdf` abgelegt wird — danach `assets:install`
ausführen.

## Manueller Testplan

1. **PDP**: Produktdetailseite aufrufen → Label wird vollständig, farbig und
   ohne Interaktion sichtbar an der konfigurierten Position angezeigt
   (Standard: unter dem Kaufen-Button). Position in der Plugin-Config
   umstellen und erneut prüfen.
2. **Checkout**: Artikel in den Warenkorb legen, bis zur Bestellabschluss-
   Seite gehen → Label erscheint an der konfigurierten Position (Standard:
   über den AGB). Optional `showOnCartPage`/`showOnOffcanvasCart` aktivieren
   und Warenkorb-Seite bzw. Offcanvas prüfen.
3. **Testbestellung**: Bestellung abschließen → Bestellbestätigungs-Mail
   prüfen: Anhang `EU-Gewaehrleistungslabel.pdf` vorhanden und öffnet das
   Label. (In Dev-Umgebungen z. B. über einen Mail-Catcher wie Mailpit
   prüfbar.)
4. **Fehlerfall**: PDF temporär umbenennen, Testbestellung → Mail kommt ohne
   Anhang an, Warnung im Log (Log-Channel `hug_eu_label`).
5. **Kompakt-Darstellung**: `pdpMode` auf `compact` stellen → nur der blaue
   Label-Kopf erscheint als Banner plus Link „Gewährleistungslabel anzeigen".
   Klick klappt das volle Label auf (`compactBehavior: collapse`) bzw. öffnet
   es im Modal (`compactBehavior: modal`).
6. **Benutzerdefinierte Position**: `pdpPosition` auf `custom_selector`
   stellen, gültigen Selektor eintragen → Label erscheint am Ziel-Element.
   Danach absichtlich falschen Selektor eintragen → Label erscheint nicht,
   Browser-Konsole zeigt eine `[HugEuLabel]`-Warnung, die Seite funktioniert
   normal weiter.
7. **GPSR — Daten & Fallback**: `gpsrActive` einschalten. PDP eines Produkts,
   dessen Hersteller GPSR-Daten hat → Block mit Überschrift und
   Herstellerangaben erscheint (Zeilenumbrüche erhalten). Hersteller
   zusätzlich mit EU-verantwortlicher Person → zweiter Absatz mit
   Zwischenüberschrift „Verantwortliche Person in der EU". PDP eines
   Produkts, dessen Hersteller keine Daten hat → Fallback-Text erscheint;
   Fallback leeren → es erscheint nichts (und nichts im Log).
8. **GPSR — Darstellung**: `gpsrDisplayMode: plain` → schlichter Textblock
   ohne Box. `banner` → dezente Box; alle 5 Farbvarianten
   (`neutral_grey`, `soft_blue`, `soft_green`, `outline`, `accent`)
   durchschalten und prüfen, dass Text lesbar bleibt und die Box nicht wie
   ein Warnhinweis oder Siegel wirkt (`accent` übernimmt die
   Theme-Primärfarbe als Balken).
9. **GPSR — Position combined**: `gpsrPosition: combined` +
   `slotLayout: stacked` → GPSR-Block direkt unter dem EU-Label.
   `side_by_side` → Desktop: EU-Label links, GPSR rechts; Browserfenster
   unter 768 px verkleinern (oder Mobilgerät) → Blöcke stehen untereinander.
   `pdpMode: hidden` → GPSR-Block erscheint allein an der EU-Label-Position
   (kein leerer Rahmen daneben). Eigene Position (z. B.
   `below_description`) → Block erscheint dort unabhängig vom EU-Label.
10. **GPSR — Nur PDP**: Warenkorb, Checkout und Bestellbestätigungs-Mail
    prüfen → dort erscheinen keine GPSR-Angaben.

7. **GARAN-Label**: Beim Hersteller eines Testprodukts `hug_garan_active`,
   Dauer > 2 Jahre und eine Bedingungs-URL pflegen, `garanEnabled`
   aktivieren → volles Label auf der PDP (Marke, Modell-Kennung, Dauer,
   scanbarer QR-Code); `garanPdpMode: nested` → Kompakt-Banner, Klick klappt
   das volle Label auf/zu. `garanListingMode: nested` → Kompakt-Label in den
   Produktkacheln. Bestellabschluss zeigt das Kompakt-Label an der Position.
8. **GARAN-Mail**: `garanMailMode: both`, Testbestellung → Mail enthält
   `Garantie-Uebersicht.pdf` (und `Garantiebedingungen-<Hersteller>.pdf`,
   wenn ein PDF gepflegt ist). Unvollständige Garantiedaten (z. B. Dauer
   ohne Bedingungen) unterdrücken das Label mit Warnung im Log-Channel
   `hug_eu_label`.

> **Theme-Hinweis:** Die Position „Bei den Versandinformationen" greift an
> Shopwares Standard-Versandinfo-Komponente. Stark angepasste Themes (etwa
> solche, die den Buy-Widget-Bereich per JavaScript rendern) zeigen diese
> Komponente nicht — dort stattdessen `custom_selector`
> verwenden. Das Ziel eines Custom-Selektors muss im initialen HTML
> vorhanden sein; per JavaScript nachgeladene Bereiche werden nicht
> gefunden.

## Entwicklung

PHP- und JS-Checks: PHPStan, ECS, Rector, PHPUnit, ESLint, Jest.
Konfiguration: `phpstan.neon`, `ecs.php`, `rector.php`, `phpunit.xml.dist`,
`.eslintrc.json`, `jest.config.js` (Composer-Scripts: `composer ecs`,
`composer phpstan`, `composer phpunit`, `composer rector`).

Unit-Tests: `tests/Unit/` (LabelProvider, MailAttachmentSubscriber,
GpsrProvider, GpsrCustomFieldSetInstaller, GpsrInfoTemplate/Escaping).

## Entstehung: KI-gestützte Entwicklung (Agentic Coding)

Dieses Plugin wurde überwiegend **KI-gestützt** entwickelt („Agentic
Coding" bzw. „Vibe Coding" mit [Claude Code](https://claude.com/claude-code)):
Ein KI-Agent hat Konzeption, Implementierung, Tests und Dokumentation unter
menschlicher Anleitung und Review erstellt. Der Code wurde funktional
getestet (Unit-Tests, manuelle Storefront-Tests), aber nicht Zeile für
Zeile von Hand geschrieben.

Was das für dich bedeutet:

- Prüfe den Code vor dem Produktiveinsatz selbst — wie bei jedem
  Fremd-Plugin, hier aber ausdrücklich empfohlen.
- Trotz sorgfältiger Tests können Fehler enthalten sein, die ein
  menschlicher Autor so nicht gemacht hätte (und umgekehrt).
- Issues und Pull Requests sind willkommen, es besteht aber **kein
  Anspruch auf Support, Fehlerbehebung oder Weiterentwicklung**.

## Haftungsausschluss

- **Keine Rechtsberatung:** Dieses Plugin unterstützt bei der Umsetzung
  der Richtlinie (EU) 2024/825, der DVO (EU) 2025/1960 und der GPSR
  (Verordnung (EU) 2023/988). Die Zusammenfassungen der Rechtslage in
  dieser README sind sorgfältig recherchiert, aber **keine Rechtsberatung**
  und können falsch, unvollständig oder veraltet sein. Die Verantwortung
  für die Rechtskonformität des eigenen Shops (Konfiguration, Datenpflege,
  Fristen) liegt allein beim Shop-Betreiber; im Zweifel juristisch beraten
  lassen.
- **Keine Gewährleistung:** Die Software wird „wie besehen" bereitgestellt,
  ohne Gewährleistung jeglicher Art und mit Haftungsbeschränkung gemäß
  **GPL-3.0 §§ 15–16** (siehe [LICENSE](LICENSE)). Einsatz auf eigenes
  Risiko — insbesondere gibt es keine Garantie, dass die Darstellung der
  Labels und Pflichtangaben den gesetzlichen Anforderungen im konkreten
  Shop genügt.

## Lizenz

GNU General Public License v3.0 oder später (GPL-3.0-or-later) —
Volltext in [LICENSE](LICENSE). Copyright (C) 2026 Hotte512.

Die enthaltenen **EU-Label-Dateien** (Gewährleistungslabel, GARAN-Vorlagen)
stammen unverändert von der Europäischen Kommission und unterliegen deren
Vorgaben (insbesondere: keine inhaltliche Veränderung zulässig) — Herkunft
und Details in [ASSETS.md](ASSETS.md).
