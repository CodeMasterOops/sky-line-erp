import {defineStore} from 'pinia';
import {apiSuperAdmin} from '@/helpers/api';

function unwrapList(res) {
    const d = res.data?.data;
    return Array.isArray(d) ? d : [];
}

export const useLocationStore = defineStore('super-admin-location', {
    state: () => ({
        provinces: [],
        districts: [],
        palikas: [],
        wards: [],
    }),

    actions: {
        async loadProvinces() {
            const res = await apiSuperAdmin('province', 'get');
            this.provinces = unwrapList(res);
        },

        async loadDistricts(provinceId = null) {
            const res = await apiSuperAdmin('district', 'get', provinceId ? { province_id: provinceId } : {});
            this.districts = unwrapList(res);
        },

        async loadPalikas(districtId = null) {
            const res = await apiSuperAdmin('palika', 'get', districtId ? { district_id: districtId } : {});
            this.palikas = unwrapList(res);
        },

        async loadWards(palikaId = null) {
            const res = await apiSuperAdmin('ward', 'get', palikaId ? { palika_id: palikaId } : {});
            this.wards = unwrapList(res);
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
