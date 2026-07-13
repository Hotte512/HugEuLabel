import Plugin from 'src/plugin-system/plugin.class';
import moveElement from './move-element';

export default class HugEuLabelMovePlugin extends Plugin {
    static options = {
        selector: '',
        mode: 'after',
    };

    init() {
        if (!moveElement(this.el, this.options.selector, this.options.mode)) {
            // eslint-disable-next-line no-console
            console.warn(`[HugEuLabel] Benutzerdefinierte Label-Position: Selektor "${this.options.selector}" nicht gefunden — Label wird nicht angezeigt.`);
        }
    }
}
