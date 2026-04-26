import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api';

function unwrapList(res) {
    const d = res.data?.data;
    return Array.isArray(d) ? d : [];
}

const base = 'location-reference';

export const useAdminLocationStore = defineStore('admin-location', {
    state: () => ({
        provinces: [],
        districts: [],
        palikas: [],
        wards: [],
    }),

    actions: {
        async loadProvinces() {
            const res = await apiAdmin(`${base}/province`, 'get');
            this.provinces = unwrapList(res);
        },

        async loadDistricts(provinceId = null) {
            const res = await apiAdmin(`${base}/district`, 'get', provinceId ? { province_id: provinceId } : {});
            this.districts = unwrapList(res);
        },

        async loadPalikas(districtId = null) {
            const res = await apiAdmin(`${base}/palika`, 'get', districtId ? { district_id: districtId } : {});
            this.palikas = unwrapList(res);
        },

        async loadWards(palikaId = null) {
            const res = await apiAdmin(`${base}/ward`, 'get', palikaId ? { palika_id: palikaId } : {});
            this.wards = unwrapList(res);
        },
    },
});
