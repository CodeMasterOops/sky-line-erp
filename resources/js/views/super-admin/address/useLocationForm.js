import { computed, nextTick, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useLocationStore } from '@/stores/super-admin/location';

const formTitles = {
    provinces: 'Province',
    districts: 'District',
    palikas: 'Palika',
    wards: 'Ward',
};

export function useLocationForm(activeTab, onSaved) {
    const locationStore = useLocationStore();
    const { provinces: provincesState } = storeToRefs(locationStore);
    const provinces = computed(() => provincesState.value.data ?? []);

    const form = ref({ name: '', sort_order: 0 });
    const palForm = ref({ province_id: '' });
    const wardForm = ref({ province_id: '', district_id: '' });
    const palikaDistricts = ref([]);
    const wardDistricts = ref([]);
    const wardPalikas = ref([]);
    const suppressPalikaCascade = ref(false);
    const suppressWardCascade = ref(false);
    const isSubmitting = ref(false);

    function defaultForm() {
        const tab = activeTab.value;
        if (tab === 'provinces') {
            return { name: '', sort_order: 0 };
        }
        if (tab === 'districts') {
            return { province_id: '', name: '', sort_order: 0 };
        }
        if (tab === 'palikas') {
            return { district_id: '', name: '', sort_order: 0 };
        }
        if (tab === 'wards') {
            return { palika_id: '', name: '', postal_code: '', sort_order: 0 };
        }
        return {};
    }

    function resetForm() {
        form.value = defaultForm();
        palForm.value = { province_id: '' };
        wardForm.value = { province_id: '', district_id: '' };
        palikaDistricts.value = [];
        wardDistricts.value = [];
        wardPalikas.value = [];
    }

    function formTitle() {
        return formTitles[activeTab.value] || '';
    }

    async function populateForEdit(row) {
        const tab = activeTab.value;
        if (tab === 'provinces') {
            form.value = { name: row.name, sort_order: row.sort_order ?? 0 };
            return;
        }
        if (tab === 'districts') {
            form.value = {
                province_id: String(row.province_id),
                name: row.name,
                sort_order: row.sort_order ?? 0,
            };
            return;
        }
        if (tab === 'palikas') {
            suppressPalikaCascade.value = true;
            palForm.value = { province_id: String(row.province_id || '') };
            form.value = {
                district_id: String(row.district_id),
                name: row.name,
                sort_order: row.sort_order ?? 0,
            };
            if (row.province_id) {
                await locationStore.loadDistricts({ provinceId: row.province_id });
                palikaDistricts.value = [...locationStore.districts.data];
            }
            await nextTick();
            suppressPalikaCascade.value = false;
            return;
        }
        if (tab === 'wards') {
            suppressWardCascade.value = true;
            wardForm.value = {
                province_id: String(row.province_id || ''),
                district_id: String(row.district_id || ''),
            };
            form.value = {
                palika_id: String(row.palika_id),
                name: row.name,
                postal_code: row.postal_code || '',
                sort_order: row.sort_order ?? 0,
            };
            if (row.province_id) {
                await locationStore.loadDistricts({ provinceId: row.province_id });
                wardDistricts.value = [...locationStore.districts.data];
            }
            if (row.district_id) {
                await locationStore.loadPalikas({ districtId: row.district_id });
                wardPalikas.value = [...locationStore.palikas.data];
            }
            await nextTick();
            suppressWardCascade.value = false;
        }
    }

    watch(
        () => palForm.value.province_id,
        async (pid) => {
            if (suppressPalikaCascade.value || activeTab.value !== 'palikas') {
                return;
            }
            form.value.district_id = '';
            if (!pid) {
                palikaDistricts.value = [];
                return;
            }
            await locationStore.loadDistricts({ provinceId: pid });
            palikaDistricts.value = [...locationStore.districts.data];
        }
    );

    watch(
        () => wardForm.value.province_id,
        async (pid) => {
            if (suppressWardCascade.value || activeTab.value !== 'wards') {
                return;
            }
            wardForm.value.district_id = '';
            form.value.palika_id = '';
            wardPalikas.value = [];
            if (!pid) {
                wardDistricts.value = [];
                return;
            }
            await locationStore.loadDistricts({ provinceId: pid });
            wardDistricts.value = [...locationStore.districts.data];
        }
    );

    watch(
        () => wardForm.value.district_id,
        async (did) => {
            if (suppressWardCascade.value || activeTab.value !== 'wards') {
                return;
            }
            form.value.palika_id = '';
            if (!did) {
                wardPalikas.value = [];
                return;
            }
            await locationStore.loadPalikas({ districtId: did });
            wardPalikas.value = [...locationStore.palikas.data];
        }
    );

    async function saveRecord(editId = null) {
        const tab = activeTab.value;
        isSubmitting.value = true;
        try {
            if (tab === 'provinces') {
                const body = { name: form.value.name, sort_order: form.value.sort_order ?? 0 };
                await locationStore.saveProvince(editId, body);
                toast('success', editId ? 'Province updated.' : 'Province added.');
            } else if (tab === 'districts') {
                const body = {
                    name: form.value.name,
                    province_id: Number(form.value.province_id),
                    sort_order: form.value.sort_order ?? 0,
                };
                if (!body.province_id) {
                    toast(422, 'Select a province.');
                    return false;
                }
                await locationStore.saveDistrict(editId, body);
                toast('success', editId ? 'District updated.' : 'District added.');
            } else if (tab === 'palikas') {
                const body = {
                    name: form.value.name,
                    district_id: Number(form.value.district_id),
                    sort_order: form.value.sort_order ?? 0,
                };
                if (!body.district_id) {
                    toast(422, 'Select a district.');
                    return false;
                }
                await locationStore.savePalika(editId, body);
                toast('success', editId ? 'Palika updated.' : 'Palika added.');
            } else if (tab === 'wards') {
                const body = {
                    name: form.value.name,
                    palika_id: Number(form.value.palika_id),
                    postal_code: form.value.postal_code || null,
                    sort_order: form.value.sort_order ?? 0,
                };
                if (!body.palika_id) {
                    toast(422, 'Select a palika.');
                    return false;
                }
                await locationStore.saveWard(editId, body);
                toast('success', editId ? 'Ward updated.' : 'Ward added.');
            }
            await onSaved?.();
            return true;
        } catch (e) {
            showErrors(e);
            return false;
        } finally {
            isSubmitting.value = false;
        }
    }

    return {
        provinces,
        form,
        palForm,
        wardForm,
        palikaDistricts,
        wardDistricts,
        wardPalikas,
        isSubmitting,
        resetForm,
        formTitle,
        populateForEdit,
        saveRecord,
        locationStore,
    };
}
