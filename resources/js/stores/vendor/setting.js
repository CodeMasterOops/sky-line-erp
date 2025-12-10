import {defineStore} from 'pinia'
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'setting';

export const useVendorSettingStore = defineStore('vendor-setting', {
    state: () => ({
        setting: {
            data: {},
            loading: false
        },
        productCategories: {
            data: [],
            loading: false
        },
        brands: {
            data: [],
            loading: false
        },
        tags: {
            data: [],
            loading: false
        },
        productAttributes: {
            data: [],
            loading: false
        }
    }),
    actions: {
        getSetting() {
            if (!Object.keys(this.setting.data).length) {
                this.setting.loading = true;
                return apiVendor(`${apiUrl}`)
                    .then((res) => {
                        this.setting.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.setting.loading = false;
                    });
            }
        },
        updateSetting(form) {
            return apiVendor(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.setting.data = res.data.data;
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        },
        getProductCategories() {
            if (!this.productCategories.data.length) {
                this.productCategories.loading = true;
                return apiVendor(`${apiUrl}/product-category`)
                    .then((res) => {
                        this.productCategories.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.productCategories.loading = false;
                    });
            }
        },
        getBrands() {
            if (!this.brands.data.length) {
                this.brands.loading = true;
                return apiVendor(`${apiUrl}/brand`)
                    .then((res) => {
                        this.brands.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.brands.loading = false;
                    });
            }
        },
        getTags() {
            if (!this.tags.data.length) {
                this.tags.loading = true;
                return apiVendor(`${apiUrl}/tag`)
                    .then((res) => {
                        this.tags.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.tags.loading = false;
                    });
            }
        },
        getProductAttributes() {
            if (!this.productAttributes.data.length) {
                this.productAttributes.loading = true;
                return apiVendor(`${apiUrl}/product-attribute`)
                    .then((res) => {
                        this.productAttributes.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.productAttributes.loading = false;
                    });
            }
        },
    }
})
