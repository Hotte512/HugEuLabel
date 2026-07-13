/**
 * Jest transform for *.html.twig component templates: exports the raw markup
 * as a string (the plugin's own components use plain Vue templates without
 * Twig blocks, so no block processing is needed here).
 */
module.exports = {
    process(sourceText) {
        return {
            code: `module.exports = ${JSON.stringify(sourceText)};`,
        };
    },
};
