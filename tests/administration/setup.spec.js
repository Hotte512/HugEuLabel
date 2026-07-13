/**
 * Smoke test: verifies the Jest wiring (babel transform + Shopware global
 * setup) works before any real admin components exist.
 */
describe('shopware global test setup', () => {
    it('collects component registrations', () => {
        const config = Shopware.Component.register('hug-eu-label-smoke', { template: '<div />' });

        expect(config).toEqual({ template: '<div />' });
        expect(Shopware.Component.getComponentRegistry().get('hug-eu-label-smoke')).toBe(config);
    });
});
