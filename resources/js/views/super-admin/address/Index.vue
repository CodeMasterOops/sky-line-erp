<template>
    <PageHeader title="Address reference" subtitle="Provinces, districts, palikas, and wards" @refresh="refreshForTab">
        <template #actions>
            <button type="button" class="btn btn-primary d-flex align-items-center" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <ul class="nav nav-tabs card-header-tabs mb-3">
            <li v-for="t in tabDefs" :key="t.id" class="nav-item">
                <a
                    href="#"
                    class="nav-link"
                    :class="{ active: activeTab === t.id }"
                    @click.prevent="setTab(t.id)">
                    {{ t.label }}
                </a>
            </li>
        </ul>

        <div v-show="activeTab === 'provinces'" class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Name</th>
                            <th>Sort</th>
                            <th class="text-end" style="width:7rem">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="!provinces.length">
                            <td colspan="4" class="text-center text-muted py-4">No provinces yet.</td>
                        </tr>
                        <tr v-for="(p, i) in provinces" :key="p.id">
                            <td>{{ i + 1 }}</td>
                            <td>{{ p.name }}</td>
                            <td>{{ p.sort_order ?? 0 }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="editProvince(p)">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeProvince(p.id)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'districts'" class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Province</th>
                            <th>District</th>
                            <th>Sort</th>
                            <th class="text-end" style="width:7rem">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="!districts.length">
                            <td colspan="5" class="text-center text-muted py-4">No districts yet. Add a province first.</td>
                        </tr>
                        <tr v-for="(d, i) in districts" :key="d.id">
                            <td>{{ i + 1 }}</td>
                            <td>{{ d.province_name || '—' }}</td>
                            <td>{{ d.name }}</td>
                            <td>{{ d.sort_order ?? 0 }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="editDistrict(d)">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeDistrict(d.id)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'palikas'" class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Province</th>
                            <th>District</th>
                            <th>Palika</th>
                            <th>Sort</th>
                            <th class="text-end" style="width:7rem">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="!palikas.length">
                            <td colspan="6" class="text-center text-muted py-4">No palikas yet. Add a district first.</td>
                        </tr>
                        <tr v-for="(p, i) in palikas" :key="p.id">
                            <td>{{ i + 1 }}</td>
                            <td>{{ p.province_name || '—' }}</td>
                            <td>{{ p.district_name || '—' }}</td>
                            <td>{{ p.name }}</td>
                            <td>{{ p.sort_order ?? 0 }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="editPalika(p)">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removePalika(p.id)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'wards'" class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:3rem">#</th>
                            <th>Province</th>
                            <th>District</th>
                            <th>Palika</th>
                            <th>Ward</th>
                            <th>Postal</th>
                            <th>Sort</th>
                            <th class="text-end" style="width:7rem">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="!wards.length">
                            <td colspan="8" class="text-center text-muted py-4">No wards yet. Add a palika first.</td>
                        </tr>
                        <tr v-for="(w, i) in wards" :key="w.id">
                            <td>{{ i + 1 }}</td>
                            <td>{{ w.province_name || '—' }}</td>
                            <td>{{ w.district_name || '—' }}</td>
                            <td>{{ w.palika_name || '—' }}</td>
                            <td>{{ w.name }}</td>
                            <td>{{ w.postal_code || '—' }}</td>
                            <td>{{ w.sort_order ?? 0 }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="editWard(w)">
                                    <i class="ti ti-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeWard(w.id)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div v-if="showForm" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editId ? 'Edit' : 'Add' }} {{ formTitle }}</h5>
                    <button class="btn-close" type="button" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <template v-if="activeTab === 'provinces'">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input v-model="form.name" class="form-control" type="text" maxlength="255"/>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort order</label>
                            <input v-model.number="form.sort_order" class="form-control" min="0" type="number"/>
                        </div>
                    </template>
                    <template v-else-if="activeTab === 'districts'">
                        <VSelect
                            id="district_province"
                            v-model="form.province_id"
                            label="Province *"
                            :options="provinces"
                        />
                        <div class="mb-3">
                            <label class="form-label">District name *</label>
                            <input v-model="form.name" class="form-control" type="text" maxlength="255"/>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort order</label>
                            <input v-model.number="form.sort_order" class="form-control" min="0" type="number"/>
                        </div>
                    </template>
                    <template v-else-if="activeTab === 'palikas'">
                        <VSelect
                            id="palika_province"
                            v-model="palForm.province_id"
                            label="Province *"
                            :options="provinces"
                        />
                        <VSelect
                            id="palika_district"
                            v-model="form.district_id"
                            :disabled="!palForm.province_id"
                            label="District *"
                            :options="palikaDistricts"
                        />
                        <div class="mb-3">
                            <label class="form-label">Palika name *</label>
                            <input v-model="form.name" class="form-control" type="text" maxlength="255"/>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort order</label>
                            <input v-model.number="form.sort_order" class="form-control" min="0" type="number"/>
                        </div>
                    </template>
                    <template v-else-if="activeTab === 'wards'">
                        <VSelect
                            id="ward_province"
                            v-model="wardForm.province_id"
                            label="Province *"
                            :options="provinces"
                        />
                        <VSelect
                            id="ward_district"
                            v-model="wardForm.district_id"
                            :disabled="!wardForm.province_id"
                            label="District *"
                            :options="wardDistricts"
                        />
                        <VSelect
                            id="ward_palika"
                            v-model="form.palika_id"
                            :disabled="!wardForm.district_id"
                            label="Palika *"
                            :options="wardPalikas"
                        />
                        <div class="mb-3">
                            <label class="form-label">Ward *</label>
                            <input v-model="form.name" class="form-control" type="text" maxlength="255" placeholder="e.g. 1 or Ward 5"/>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Postal code (default for companies in this ward)</label>
                            <input v-model="form.postal_code" class="form-control" type="text" maxlength="20"/>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort order</label>
                            <input v-model.number="form.sort_order" class="form-control" min="0" type="number"/>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" type="button" :disabled="saving" @click="saveForm">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import Swal from 'sweetalert2';
import { useLocationStore } from '@/stores/super-admin/location';

const locationStore = useLocationStore();
const { provinces, districts, palikas, wards } = storeToRefs(locationStore);

const tabDefs = [
    { id: 'provinces', label: 'Provinces' },
    { id: 'districts', label: 'Districts' },
    { id: 'palikas', label: 'Palikas' },
    { id: 'wards', label: 'Wards' },
];

const activeTab = ref('provinces');
const showForm = ref(false);
const saving = ref(false);
const editId = ref(null);
const form = ref({ name: '', sort_order: 0 });
const palForm = ref({ province_id: '' });
const wardForm = ref({ province_id: '', district_id: '' });
const palikaDistricts = ref([]);
const wardDistricts = ref([]);
const wardPalikas = ref([]);
const suppressPalikaCascade = ref(false);
const suppressWardCascade = ref(false);

const formTitle = computed(() => {
    const m = { provinces: 'Province', districts: 'District', palikas: 'Palika', wards: 'Ward' };
    return m[activeTab.value] || '';
});

function defaultForm() {
    const t = activeTab.value;
    if (t === 'provinces') {
        return { name: '', sort_order: 0 };
    }
    if (t === 'districts') {
        return { province_id: '', name: '', sort_order: 0 };
    }
    if (t === 'palikas') {
        return { district_id: '', name: '', sort_order: 0 };
    }
    if (t === 'wards') {
        return { palika_id: '', name: '', postal_code: '', sort_order: 0 };
    }
    return {};
}

function resetForm() {
    editId.value = null;
    form.value = defaultForm();
    palForm.value = { province_id: '' };
    wardForm.value = { province_id: '', district_id: '' };
    palikaDistricts.value = [];
    wardDistricts.value = [];
    wardPalikas.value = [];
}

function setTab(tab) {
    activeTab.value = tab;
    showForm.value = false;
    resetForm();
    refreshForTab();
}

async function refreshForTab() {
    switch (activeTab.value) {
        case 'provinces':
            return locationStore.loadProvinces();
        case 'districts':
            await locationStore.loadProvinces();
            return locationStore.loadDistricts();
        case 'palikas':
            await locationStore.loadProvinces();
            return locationStore.loadPalikas();
        case 'wards':
            await locationStore.loadProvinces();
            return locationStore.loadWards();
        default:
    }
}

onMounted(() => {
    refreshForTab();
});

function openCreate() {
    resetForm();
    showForm.value = true;
}

function editProvince(row) {
    editId.value = row.id;
    form.value = { name: row.name, sort_order: row.sort_order ?? 0 };
    showForm.value = true;
}

function editDistrict(row) {
    editId.value = row.id;
    form.value = { province_id: String(row.province_id), name: row.name, sort_order: row.sort_order ?? 0 };
    showForm.value = true;
}

async function editPalika(row) {
    suppressPalikaCascade.value = true;
    editId.value = row.id;
    palForm.value = { province_id: String(row.province_id || '') };
    form.value = { district_id: String(row.district_id), name: row.name, sort_order: row.sort_order ?? 0 };
    if (row.province_id) {
        await locationStore.loadDistricts(row.province_id);
        palikaDistricts.value = [...locationStore.districts];
    }
    showForm.value = true;
    await nextTick();
    suppressPalikaCascade.value = false;
}

async function editWard(row) {
    suppressWardCascade.value = true;
    editId.value = row.id;
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
        await locationStore.loadDistricts(row.province_id);
        wardDistricts.value = [...locationStore.districts];
    }
    if (row.district_id) {
        await locationStore.loadPalikas(row.district_id);
        wardPalikas.value = [...locationStore.palikas];
    }
    showForm.value = true;
    await nextTick();
    suppressWardCascade.value = false;
}

watch(
    () => palForm.value.province_id,
    async (pid) => {
        if (suppressPalikaCascade.value || !showForm.value || activeTab.value !== 'palikas') {
            return;
        }
        form.value.district_id = '';
        if (!pid) {
            palikaDistricts.value = [];
            return;
        }
        await locationStore.loadDistricts(pid);
        palikaDistricts.value = [...locationStore.districts];
    }
);

watch(
    () => wardForm.value.province_id,
    async (pid) => {
        if (suppressWardCascade.value || !showForm.value || activeTab.value !== 'wards') {
            return;
        }
        wardForm.value.district_id = '';
        form.value.palika_id = '';
        wardPalikas.value = [];
        if (!pid) {
            wardDistricts.value = [];
            return;
        }
        await locationStore.loadDistricts(pid);
        wardDistricts.value = [...locationStore.districts];
    }
);

watch(
    () => wardForm.value.district_id,
    async (did) => {
        if (suppressWardCascade.value || !showForm.value || activeTab.value !== 'wards') {
            return;
        }
        form.value.palika_id = '';
        if (!did) {
            wardPalikas.value = [];
            return;
        }
        await locationStore.loadPalikas(did);
        wardPalikas.value = [...locationStore.palikas];
    }
);

async function saveForm() {
    const tab = activeTab.value;
    saving.value = true;
    try {
        if (tab === 'provinces') {
            const body = { name: form.value.name, sort_order: form.value.sort_order ?? 0 };
            await locationStore.saveProvince(editId.value, body);
            toast('success', editId.value ? 'Province updated.' : 'Province added.');
        } else if (tab === 'districts') {
            const body = {
                name: form.value.name,
                province_id: Number(form.value.province_id),
                sort_order: form.value.sort_order ?? 0,
            };
            if (!body.province_id) {
                toast(422, 'Select a province.');
                return;
            }
            await locationStore.saveDistrict(editId.value, body);
            toast('success', editId.value ? 'District updated.' : 'District added.');
        } else if (tab === 'palikas') {
            const body = {
                name: form.value.name,
                district_id: Number(form.value.district_id),
                sort_order: form.value.sort_order ?? 0,
            };
            if (!body.district_id) {
                toast(422, 'Select a district.');
                return;
            }
            await locationStore.savePalika(editId.value, body);
            toast('success', editId.value ? 'Palika updated.' : 'Palika added.');
        } else if (tab === 'wards') {
            const body = {
                name: form.value.name,
                palika_id: Number(form.value.palika_id),
                postal_code: form.value.postal_code || null,
                sort_order: form.value.sort_order ?? 0,
            };
            if (!body.palika_id) {
                toast(422, 'Select a palika.');
                return;
            }
            await locationStore.saveWard(editId.value, body);
            toast('success', editId.value ? 'Ward updated.' : 'Ward added.');
        }
        showForm.value = false;
        resetForm();
        await refreshForTab();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
}

async function removeProvince(id) {
    const r = await Swal.fire({ title: 'Delete province?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
    if (!r.isConfirmed) {
        return;
    }
    try {
        await locationStore.deleteProvince(id);
        toast('success', 'Deleted.');
        await locationStore.loadProvinces();
    } catch (e) {
        showErrors(e);
    }
}

async function removeDistrict(id) {
    const r = await Swal.fire({ title: 'Delete district?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
    if (!r.isConfirmed) {
        return;
    }
    try {
        await locationStore.deleteDistrict(id);
        toast('success', 'Deleted.');
        await refreshForTab();
    } catch (e) {
        showErrors(e);
    }
}

async function removePalika(id) {
    const r = await Swal.fire({ title: 'Delete palika?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
    if (!r.isConfirmed) {
        return;
    }
    try {
        await locationStore.deletePalika(id);
        toast('success', 'Deleted.');
        await refreshForTab();
    } catch (e) {
        showErrors(e);
    }
}

async function removeWard(id) {
    const r = await Swal.fire({ title: 'Delete ward?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
    if (!r.isConfirmed) {
        return;
    }
    try {
        await locationStore.deleteWard(id);
        toast('success', 'Deleted.');
        await locationStore.loadWards();
    } catch (e) {
        showErrors(e);
    }
}
</script>
