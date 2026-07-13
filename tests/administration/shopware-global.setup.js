/**
 * Minimal Shopware global for unit tests. Component/service registration
 * calls made at import time are collected so tests can grab the component
 * configuration objects without the real admin runtime.
 */
const registeredComponents = new Map();
const registeredOverrides = new Map();
const serviceProviders = new Map();

global.Shopware = {
    Component: {
        register(name, config) {
            registeredComponents.set(name, config);

            return config;
        },
        override(name, config) {
            registeredOverrides.set(name, config);

            return config;
        },
        getComponentRegistry() {
            return registeredComponents;
        },
    },
    Application: {
        addServiceProvider(name, factory) {
            serviceProviders.set(name, factory);
        },
        getContainer() {
            return { httpClient: {} };
        },
    },
    Service(name) {
        const services = {
            acl: { can: () => true },
            loginService: {},
        };

        return services[name];
    },
    Classes: {
        ApiService: class ApiService {
            constructor(httpClient, loginService, apiEndpoint) {
                this.httpClient = httpClient;
                this.loginService = loginService;
                this.apiEndpoint = apiEndpoint;
            }

            getBasicHeaders() {
                return {};
            }

            static handleResponse(response) {
                return response && response.data !== undefined ? response.data : response;
            }
        },
    },
    Mixin: {
        getByName() {
            return {
                methods: {
                    createNotificationError() {},
                    createNotificationSuccess() {},
                },
            };
        },
    },
    Data: {
        Criteria: class Criteria {
            constructor(page = 1, limit = 25) {
                this.page = page;
                this.limit = limit;
                this.filters = [];
                this.associations = [];
                this.sortings = [];
            }

            addFilter(filter) {
                this.filters.push(filter);

                return this;
            }

            addAssociation(path) {
                this.associations.push(path);

                return this;
            }

            addSorting(sorting) {
                this.sortings.push(sorting);

                return this;
            }

            static equals(field, value) {
                return { type: 'equals', field, value };
            }

            static sort(field, order) {
                return { field, order };
            }
        },
    },
    Locale: {
        extend() {},
    },
    Context: {
        api: {},
    },
    Utils: {
        createId: () => 'test-id',
    },
};

global.Shopware.__registeredComponents = registeredComponents;
global.Shopware.__registeredOverrides = registeredOverrides;
global.Shopware.__serviceProviders = serviceProviders;
