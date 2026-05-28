import {defineStore} from 'pinia';
import {apiSuperAdmin} from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

function paginatedParams(filter = {}) {
    return {
        page: filter.page ?? 1,
        limit: filter.limit ?? 1000,
    };
}

export const useLocationStore = defineStore('super-admin-location', {
    state: () => ({
        provinces: { data: [], meta: {}, loading: false },
        districts: { data: [], meta: {}, loading: false },
        palikas: { data: [], meta: {}, loading: false },
        wards: { data: [], meta: {}, loading: false },
    }),

    actions: {
        loadProvinces({ filter } = {}) {
            this.provinces.loading = true;
            return apiSuperAdmin(`province?${new URLSearchParams(paginatedParams(filter))}`)
                .then((res) => {
                    this.provinces.data = res.data.data ?? [];
                    this.provinces.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.provinces.loading = false;
                });
        },

        loadDistricts(arg = {}) {
            const provinceId = typeof arg === 'string' || typeof arg === 'number'
                ? arg
                : (arg.provinceId ?? null);
            const filter = typeof arg === 'object' && arg !== null && !Array.isArray(arg)
                ? (arg.filter ?? {})
                : {};
            const params = {
                ...paginatedParams(filter),
                ...(provinceId ? { province_id: provinceId } : {}),
            };
            this.districts.loading = true;
            return apiSuperAdmin(`district?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.districts.data = res.data.data ?? [];
                    this.districts.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.districts.loading = false;
                });
        },

        loadPalikas(arg = {}) {
            const districtId = typeof arg === 'string' || typeof arg === 'number'
                ? arg
                : (arg.districtId ?? null);
            const filter = typeof arg === 'object' && arg !== null && !Array.isArray(arg)
                ? (arg.filter ?? {})
                : {};
            const params = {
                ...paginatedParams(filter),
                ...(districtId ? { district_id: districtId } : {}),
            };
            this.palikas.loading = true;
            return apiSuperAdmin(`palika?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.palikas.data = res.data.data ?? [];
                    this.palikas.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.palikas.loading = false;
                });
        },

        loadWards(arg = {}) {
            const palikaId = typeof arg === 'string' || typeof arg === 'number'
                ? arg
                : (arg.palikaId ?? null);
            const filter = typeof arg === 'object' && arg !== null && !Array.isArray(arg)
                ? (arg.filter ?? {})
                : {};
            const params = {
                ...paginatedParams(filter),
                ...(palikaId ? { palika_id: palikaId } : {}),
            };
            this.wards.loading = true;
            return apiSuperAdmin(`ward?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.wards.data = res.data.data ?? [];
                    this.wards.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.wards.loading = false;
                });
        },

        async saveProvince(id, form) {
            if (id) {
                return apiSuperAdmin(`province/${id}`, 'put', form);
            }
            return apiSuperAdmin('province', 'post', form);
        },

        async deleteProvince(id) {
            return apiSuperAdmin(`province/${id}`, 'delete');
        },

        async saveDistrict(id, form) {
            if (id) {
                return apiSuperAdmin(`district/${id}`, 'put', form);
            }
            return apiSuperAdmin('district', 'post', form);
        },

        async deleteDistrict(id) {
            return apiSuperAdmin(`district/${id}`, 'delete');
        },

        async savePalika(id, form) {
            if (id) {
                return apiSuperAdmin(`palika/${id}`, 'put', form);
            }
            return apiSuperAdmin('palika', 'post', form);
        },

        async deletePalika(id) {
            return apiSuperAdmin(`palika/${id}`, 'delete');
        },

        async saveWard(id, form) {
            if (id) {
                return apiSuperAdmin(`ward/${id}`, 'put', form);
            }
            return apiSuperAdmin('ward', 'post', form);
        },

        async deleteWard(id) {
            return apiSuperAdmin(`ward/${id}`, 'delete');
        },
    },
});
