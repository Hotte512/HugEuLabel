/**
 * @jest-environment jsdom
 */
import moveElement from '../src/move-element';

describe('moveElement', () => {
    let el;

    beforeEach(() => {
        document.body.innerHTML = '<div id="target"><span id="child"></span></div>';
        el = document.createElement('div');
        el.classList.add('d-none');
        document.body.appendChild(el);
    });

    it('fügt das Element nach dem Ziel ein und macht es sichtbar', () => {
        expect(moveElement(el, '#target', 'after')).toBe(true);
        expect(document.querySelector('#target').nextElementSibling).toBe(el);
        expect(el.classList.contains('d-none')).toBe(false);
    });

    it('fügt das Element vor dem Ziel ein', () => {
        expect(moveElement(el, '#target', 'before')).toBe(true);
        expect(document.querySelector('#target').previousElementSibling).toBe(el);
    });

    it('hängt das Element ans Ende des Ziels an', () => {
        expect(moveElement(el, '#target', 'append')).toBe(true);
        expect(document.querySelector('#target').lastElementChild).toBe(el);
    });

    it('liefert false bei leerem Selektor und lässt das Element unsichtbar', () => {
        expect(moveElement(el, '', 'after')).toBe(false);
        expect(el.classList.contains('d-none')).toBe(true);
    });

    it('liefert false, wenn der Selektor nichts findet', () => {
        expect(moveElement(el, '#gibt-es-nicht', 'after')).toBe(false);
        expect(el.classList.contains('d-none')).toBe(true);
    });

    it('liefert false bei syntaktisch ungültigem Selektor statt zu werfen', () => {
        expect(moveElement(el, ':::kaputt', 'after')).toBe(false);
    });
});
