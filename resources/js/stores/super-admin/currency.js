import {defineStore} from 'pinia';
import {apiSuperAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

const apiUrl = 'currency';

export const useCurrencyStore = defineStore('currency', {
    state: () => ({
        currencies: {
            data: [],
            meta: {},
            loading: false,
        },
        currency: {
            data: {},
            loading: false,
        },
        stats: {
            total: 0,
            active: 0,
            foreign: 0,
        },
    }),

    actions: {
        getCurrencies({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
                ...(filter?.search ? { search: filter.search } : {}),
            };
            this.currencies.loading = true;

            return apiSuperAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.currencies.data = res.data.data || [];
                    this.currencies.meta = res.data.meta ?? {};
                    this.updateStats();
                })
                .catch(showErrors)
                .finally(() => {
                    this.currencies.loading = false;
                });
        },
        getCurrency(id) {
            this.currency.loading = true;

            return apiSuperAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.currency.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.currency.loading = false;
                });
        },
        storeCurrency(form) {
            return apiSuperAdmin(apiUrl, 'post', form)
                .then((res) => {
                    this.currencies.data.push(res.data.data);
                    this.updateStats();
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
        updateCurrency(id, form) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.currencies.data.findIndex((c) => c.id === id);
                    if (index !== -1) {
                        this.currencies.data[index] = res.data.data;
                    }
                    this.updateStats();
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
        deleteCurrency(id) {
            return apiSuperAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.currencies.data = this.currencies.data.filter((c) => c.id !== id);
                    this.updateStats();
                    return res;
                })
                .catch((err) => {
                    throw err;
                });
        },
        updateStats() {
            const total = this.currencies.meta?.total ?? this.currencies.data.length;
            this.stats.total = total;
            this.stats.active = this.currencies.data.filter((c) => c.is_active).length;
            this.stats.foreign = this.currencies.data.filter((c) => !c.is_base).length;
        },
    },
});
