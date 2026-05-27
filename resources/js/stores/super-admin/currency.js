import {defineStore} from 'pinia';
import {apiSuperAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

const apiUrl = 'currency';

export const useCurrencyStore = defineStore('currency', {
    state: () => ({
        currencies: {
            data: [],
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
        getCurrencies() {
            this.currencies.loading = true;

            return apiSuperAdmin(apiUrl)
                .then((res) => {
                    this.currencies.data = res.data.data || [];
                    this.updateStats();
                })
                .catch((err) => {
                    showErrors(err);
                })
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
                .catch((err) => {
                    showErrors(err);
                })
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
            const rows = this.currencies.data;
            this.stats.total = rows.length;
            this.stats.active = rows.filter((c) => c.is_active).length;
            this.stats.foreign = rows.filter((c) => !c.is_base).length;
        },
    },
});
