#!/usr/bin/env python3
"""Bereitet die offiziellen GARAN-Vorlagen der EU-Kommission fürs Plugin auf.

Quelle: "GARAN label for website.zip" von
https://commission.europa.eu/publications/practical-guidelines-and-high-resolution-vector-files-eu-notice-and-label-product-guarantees_en

Einzige inhaltliche Änderung an der Farb-Vorlage: Der mitgelieferte
Dummy-QR-Code wird entfernt und durch eine leere Zielgruppe
<g id="hug-garan-qr"/> ersetzt, in die der GaranLabelRenderer zur Laufzeit
den echten QR-Code (Ziel: Garantiebedingungen) einsetzt. Alles andere
bleibt unverändert — die DVO (EU) 2025/1960 erlaubt nur das Befüllen der
vorgesehenen Felder.

Aufruf:
    python3 bin/prepare-garan-templates.py <quell-verzeichnis> <ziel-verzeichnis>

Erwartete Quelldateien: "GARAN Label_colour.svg", "GARAN Label_nested display.svg"
"""

import re
import sys
from pathlib import Path

QR_CLASSES = ('cls-13', 'cls-14', 'cls-15', 'cls-16', 'cls-20', 'cls-1', 'cls-2', 'cls-6')
QR_AREA = (192.0, 268.0, 80.0, 156.0)  # x-min, x-max, y-min, y-max (viewBox-Koordinaten)


def strip_dummy_qr(svg: str) -> str:
    # 1. Die acht QR-Modul-Gruppen (je <g class="cls-N"><rect …/></g>).
    cls_alt = '|'.join(sorted(QR_CLASSES, key=len, reverse=True))
    svg, n_groups = re.subn(
        rf'<g class="(?:{cls_alt})">.*?</g>\s*', '', svg, flags=re.S)

    # 2. Die zugehörigen clipPath-Definitionen (clippath-1 … clippath-8).
    #    Das suffixlose "clippath" gehört zum Häkchen-Glyph und bleibt!
    svg, n_clips = re.subn(
        r'<clipPath id="clippath-[1-8]">.*?</clipPath>\s*', '', svg, flags=re.S)

    # 3. Verbliebene Finder-/Modul-Rects im QR-Bereich (cls-18/cls-11), die
    #    weiße Grundfläche (cls-8) bleibt erhalten.
    def drop_rect(m: re.Match) -> str:
        attrs = m.group(1)
        if 'cls-8' in attrs:
            return m.group(0)
        x = re.search(r'x="([\d.]+)"', attrs)
        y = re.search(r'y="([\d.]+)"', attrs)
        if not x or not y:
            return m.group(0)
        xv, yv = float(x.group(1)), float(y.group(1))
        if QR_AREA[0] <= xv <= QR_AREA[1] and QR_AREA[2] <= yv <= QR_AREA[3]:
            return ''
        return m.group(0)

    svg, _ = re.subn(r'<rect([^>]*)/>', drop_rect, svg)

    # 4. Tote CSS-Regeln der entfernten QR-Gruppen (.cls-N { clip-path:
    #    url(#clippath-K); }) mit ausräumen — sonst bleiben url(#…)-Referenzen
    #    ins Leere zurück.
    cls_alt_css = '|'.join(c.replace('-', r'\-') for c in QR_CLASSES)
    svg, n_css = re.subn(
        rf'\.(?:{cls_alt_css})\s*\{{[^}}]*clip-path:\s*url\(#clippath-[1-8]\);[^}}]*\}}\s*',
        '', svg)

    # 5. Leere Zielgruppe für den echten QR-Code.
    svg = svg.replace('</svg>', '  <g id="hug-garan-qr"/>\n</svg>')

    if n_groups != 8:
        raise SystemExit(f'Erwartete 8 QR-Gruppen, entfernt: {n_groups} — Vorlage geändert?')
    return svg


def main() -> None:
    src, dst = Path(sys.argv[1]), Path(sys.argv[2])
    dst.mkdir(parents=True, exist_ok=True)

    colour = (src / 'GARAN Label_colour.svg').read_text()
    (dst / 'garan-colour.svg').write_text(strip_dummy_qr(colour))

    nested = (src / 'GARAN Label_nested display.svg').read_text()
    (dst / 'garan-nested.svg').write_text(nested)

    print(f'geschrieben: {dst}/garan-colour.svg, {dst}/garan-nested.svg')


if __name__ == '__main__':
    main()
