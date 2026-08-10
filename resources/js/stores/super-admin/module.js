import { defineStore } from "pinia";
import { apiSuperAdmin } from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

/**
 * The module catalogue (config-defined, identical for every company), the
 * industry categories that pre-select defaults, and per-company module control.
 *
 * @see docs/saas-modular-platform-and-gym-module-plan.md §3.11
 */
export const useModuleStore = defineStore("super-admin-module", {
    state: () => ({
        catalogue: {
            data: [],
            groups: [],
            alwaysOn: [],
            sources: [],
            loaded: false,
            loading: false,
        },
        categories: {
            data: [],
            meta: {},
            loading: false,
        },
        matrix: {
            data: [],
            meta: {},
            loading: false,
        },
        events: {
            data: [],
            meta: {},
            loading: false,
        },
        saving: false,
    }),

    getters: {
        /** Catalogue entries grouped in registry order, for the pickers. */
        catalogueByGroup(state) {
            return state.catalogue.groups
                .map((group) => ({
                    group,
                    modules: state.catalogue.data.filter((m) => m.group === group),
                }))
                .filter((section) => section.modules.length > 0);
        },
        /** Matrix rows grouped the same way, for the per-company screen. */
        matrixByGroup(state) {
            const groups = state.catalogue.groups.length
                ? state.catalogue.groups
                : [...new Set(state.matrix.data.map((m) => m.group))];

            return groups
                .map((group) => ({
                    group,
                    modules: state.matrix.data.filter((m) => m.group === group),
                }))
                .filter((section) => section.modules.length > 0);
        },
        /** Keys a company may pick from — everything except the always-on core. */
        selectableKeys(state) {
            return state.catalogue.data
                .filter((m) => !m.always_on)
                .map((m) => m.key);
        },
    },

    actions: {
        getCatalogue({ force = false } = {}) {
            if (this.catalogue.loaded && !force) {
                return Promise.resolve();
            }

            this.catalogue.loading = true;

            return apiSuperAdmin("module")
                .then((res) => {
                    this.catalogue.data = res.data.data;
                    this.catalogue.groups = res.data.meta?.groups ?? [];
                    this.catalogue.alwaysOn = res.data.meta?.always_on ?? [];
                    this.catalogue.sources = res.data.meta?.sources ?? [];
                    this.catalogue.loaded = true;
                })
                .catch(showErrors)
                .finally(() => {
                    this.catalogue.loading = false;
                });
        },

        getCategories({ filter = {} } = {}) {
            this.categories.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiSuperAdmin(`company-category${query ? `?${query}` : ""}`)
                .then((res) => {
                    this.categories.data = res.data.data;
                    this.categories.meta = res.data.meta || {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.categories.loading = false;
                });
        },

        storeCategory(form) {
            return apiSuperAdmin("company-category", "post", form).then((res) => {
                this.categories.data.push(res.data.data);
                return res;
            });
        },

        updateCategory(id, form) {
            return apiSuperAdmin(`company-category/${id}`, "put", form).then((res) => {
                const index = this.categories.data.findIndex((c) => c.id === id);
                if (index !== -1) {
                    this.categories.data[index] = res.data.data;
                }
                return res;
            });
        },

        deleteCategory(id) {
            return apiSuperAdmin(`company-category/${id}`, "delete").then((res) => {
                this.categories.data = this.categories.data.filter((c) => c.id !== id);
                return res;
            });
        },

        getCompanyModules(companyId) {
            this.matrix.loading = true;

            return apiSuperAdmin(`company/${companyId}/module`)
                .then((res) => {
                    this.matrix.data = res.data.data;
                    this.matrix.meta = res.data.meta || {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.matrix.loading = false;
                });
        },

        /**
         * @param {Record<string, boolean>} modules
         * @param {{cascade?: boolean, reason?: string}} options
         */
        updateCompanyModules(companyId, modules, options = {}) {
            this.saving = true;

            return apiSuperAdmin(`company/${companyId}/module`, "put", {
                modules,
                cascade: options.cascade ?? false,
                reason: options.reason ?? null,
            })
                .then((res) => {
                    return this.getCompanyModules(companyId).then(() => res);
                })
                .finally(() => {
                    this.saving = false;
                });
        },

        applyCategory(companyId, payload) {
            this.saving = true;

            return apiSuperAdmin(`company/${companyId}/category`, "put", payload)
                .then((res) => this.getCompanyModules(companyId).then(() => res))
                .finally(() => {
                    this.saving = false;
                });
        },

        resetToCategory(companyId) {
            this.saving = true;

            return apiSuperAdmin(`company/${companyId}/module/reset`, "post")
                .then((res) => this.getCompanyModules(companyId).then(() => res))
                .finally(() => {
                    this.saving = false;
                });
        },

        getCompanyModuleEvents(companyId, { filter = {} } = {}) {
            this.events.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiSuperAdmin(`company/${companyId}/module/event${query ? `?${query}` : ""}`)
                .then((res) => {
                    this.events.data = res.data.data;
                    this.events.meta = res.data.meta || {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.events.loading = false;
                });
        },
    },
});
