<template>
    <div>
        <PageHeader
            title="Address reference"
            subtitle="Provinces, districts, palikas, and wards"
            @refresh="refreshForTab"
        >
            <template #actions>
                <button
                    type="button"
                    class="btn btn-primary d-flex align-items-center"
                    @click="createModalOpened = true"
                >
                    <i class="ti ti-circle-plus me-2"></i> Add
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <super-admin-settings-sidebar></super-admin-settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-header">
                        <h4 class="fs-18 fw-bold">Address reference</h4>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3">
                            <li v-for="t in tabDefs" :key="t.id" class="nav-item">
                                <button
                                    type="button"
                                    class="nav-link"
                                    :class="{ active: activeTab === t.id }"
                                    @click="setTab(t.id)"
                                >
                                    {{ t.label }}
                                </button>
                            </li>
                        </ul>

                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="currentColumns"
                                :data-source="currentData"
                                :loading="loading"
                                :pagination="false"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ index + 1 }}
                                    </template>
                                    <template v-if="column.key === 'province_name'">
                                        {{ record.province_name || '—' }}
                                    </template>
                                    <template v-if="column.key === 'district_name'">
                                        {{ record.district_name || '—' }}
                                    </template>
                                    <template v-if="column.key === 'palika_name'">
                                        {{ record.palika_name || '—' }}
                                    </template>
                                    <template v-if="column.key === 'postal_code'">
                                        {{ record.postal_code || '—' }}
                                    </template>
                                    <template v-if="column.key === 'sort_order'">
                                        {{ record.sort_order ?? 0 }}
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <a
                                                class="me-2"
                                                href="javascript:void(0);"
                                                @click="editRecord = record"
                                            >
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <a
                                                href="javascript:void(0);"
                                                @click="removeRecord(record.id)"
                                            >
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <CreateAddress
        v-model:create-modal-opened="createModalOpened"
        :active-tab="activeTab"
        @saved="refreshForTab"
    />
    <EditAddress
        v-model:edit-record="editRecord"
        :active-tab="activeTab"
        @saved="refreshForTab"
    />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import Swal from 'sweetalert2';
import { useLocationStore } from '@/stores/super-admin/location';
import CreateAddress from './Create.vue';
import EditAddress from './Edit.vue';

const locationStore = useLocationStore();
const { provinces, districts, palikas, wards } = storeToRefs(locationStore);

const tabDefs = [
    { id: 'provinces', label: 'Provinces' },
    { id: 'districts', label: 'Districts' },
    { id: 'palikas', label: 'Palikas' },
    { id: 'wards', label: 'Wards' },
];

const provinceColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Sort', key: 'sort_order' },
    { title: 'Action', key: 'action', align: 'center' },
];

const districtColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Province', key: 'province_name' },
    { title: 'District', dataIndex: 'name', key: 'name' },
    { title: 'Sort', key: 'sort_order' },
    { title: 'Action', key: 'action', align: 'center' },
];

const palikaColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Province', key: 'province_name' },
    { title: 'District', key: 'district_name' },
    { title: 'Palika', dataIndex: 'name', key: 'name' },
    { title: 'Sort', key: 'sort_order' },
    { title: 'Action', key: 'action', align: 'center' },
];

const wardColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Province', key: 'province_name' },
    { title: 'District', key: 'district_name' },
    { title: 'Palika', key: 'palika_name' },
    { title: 'Ward', dataIndex: 'name', key: 'name' },
    { title: 'Postal', key: 'postal_code' },
    { title: 'Sort', key: 'sort_order' },
    { title: 'Action', key: 'action', align: 'center' },
];

const activeTab = ref('provinces');
const loading = ref(false);
const createModalOpened = ref(false);
const editRecord = ref(null);

const currentColumns = computed(() => {
    const map = {
        provinces: provinceColumns,
        districts: districtColumns,
        palikas: palikaColumns,
        wards: wardColumns,
    };
    return map[activeTab.value] || provinceColumns;
});

const currentData = computed(() => {
    const map = {
        provinces: provinces.value,
        districts: districts.value,
        palikas: palikas.value,
        wards: wards.value,
    };
    return map[activeTab.value] || [];
});

function setTab(tab) {
    activeTab.value = tab;
    createModalOpened.value = false;
    editRecord.value = null;
    refreshForTab();
}

async function refreshForTab() {
    loading.value = true;
    try {
        switch (activeTab.value) {
            case 'provinces':
                await locationStore.loadProvinces();
                break;
            case 'districts':
                await locationStore.loadProvinces();
                await locationStore.loadDistricts();
                break;
            case 'palikas':
                await locationStore.loadProvinces();
                await locationStore.loadPalikas();
                break;
            case 'wards':
                await locationStore.loadProvinces();
                await locationStore.loadWards();
                break;
            default:
        }
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    refreshForTab();
});

function removeRecord(id) {
    switch (activeTab.value) {
        case 'provinces':
            removeProvince(id);
            break;
        case 'districts':
            removeDistrict(id);
            break;
        case 'palikas':
            removePalika(id);
            break;
        case 'wards':
            removeWard(id);
            break;
        default:
    }
}

async function removeProvince(id) {
    const r = await Swal.fire({
        title: 'Delete province?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
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
    const r = await Swal.fire({
        title: 'Delete district?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
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
    const r = await Swal.fire({
        title: 'Delete palika?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
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
    const r = await Swal.fire({
        title: 'Delete ward?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
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
