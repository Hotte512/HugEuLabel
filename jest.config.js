module.exports = {
    testEnvironment: 'jsdom',
    roots: [
        '<rootDir>/tests/administration',
        '<rootDir>/src/Resources/app/administration',
        '<rootDir>/src/Resources/app/storefront',
    ],
    moduleFileExtensions: ['js', 'json'],
    transform: {
        '^.+\\.js$': 'babel-jest',
        '^.+\\.html\\.twig$': '<rootDir>/tests/administration/twig-transformer.js',
    },
    moduleNameMapper: {
        // Runtime compiler build so components can use template strings.
        '^vue$': 'vue/dist/vue.cjs.js',
        '\\.scss$': '<rootDir>/tests/administration/style-stub.js',
    },
    setupFiles: ['<rootDir>/tests/administration/shopware-global.setup.js'],
    snapshotSerializers: [],
};
