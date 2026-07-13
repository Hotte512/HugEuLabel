/**
 * Verschiebt das Label-Element an den konfigurierten CSS-Selektor.
 * Liefert false (Element bleibt unsichtbar), wenn der Selektor leer,
 * ungültig oder nicht auffindbar ist — die Seite darf nie brechen.
 */
export default function moveElement(el, selector, mode) {
    if (!selector) {
        return false;
    }

    let target;
    try {
        target = document.querySelector(selector);
    } catch {
        return false;
    }

    if (!target) {
        return false;
    }

    if (mode === 'before') {
        target.before(el);
    } else if (mode === 'append') {
        target.append(el);
    } else {
        target.after(el);
    }

    el.classList.remove('d-none');

    return true;
}
