<template>
    <PageHeader title="Holidays" subtitle="Manage company holidays" @refresh="fetchHolidays">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Holiday
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search holidays" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ filter.year || 'Year' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.year = ''">All Years</a>
                        </li>
                        <li v-for="y in years" :key="y">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.year = y">{{ y }}</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="holidayColumns"
                    :data-source="holidays.data" :pagination="false" :loading="holidays.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (holidays.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="holidays.meta" />
            </div>
        </div>
    </div>

    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Holiday">
        <template #modal-body>
            <form @submit.prevent="storeHoliday" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-6"><VDatepicker id="create_date" v-model="cForm.date" label="Date" required /></div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" v-model="cForm.description" rows="2"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="createModalOpened = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Holiday">
        <template #modal-body>
            <form v-if="editItem" @submit.prevent="updateHoliday" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-6"><VDatepicker id="edit_date" v-model="editItem.date" label="Date" required /></div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" v-model="editItem.description" rows="2"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="editItem = null" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="eSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { useLeaveStore } from '@/stores/admin/hr/leave.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { holidayColumns, createRowActions } from './tableConfig.js';

const leaveStore = useLeaveStore();
const { holidays } = storeToRefs(leaveStore);

const now = new Date();
const years = Array.from({ length: 5 }, (_, i) => now.getFullYear() - i);

const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const cForm = reactive({ name: '', date: '', description: '' });

const fetchHolidays = () => leaveStore.getHolidays(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', year: now.getFullYear(), page: 1, limit: 10 },
    onFilter: fetchHolidays,
});

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onEdit:   (record) => { editItem.value = { ...record }; },
    onDelete: (id) => confirmDelete(
        () => leaveStore.deleteHoliday(id),
        fetchHolidays,
    ),
});

const storeHoliday = async () => {
    cSubmitting.value = true;
    try {
        const res = await leaveStore.storeHoliday(cForm);
        toast(res.status, res.data.message);
        createModalOpened.value = false;
        Object.assign(cForm, { name: '', date: '', description: '' });
        fetchHolidays();
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};

const updateHoliday = async () => {
    eSubmitting.value = true;
    try {
        const res = await leaveStore.updateHoliday(editItem.value.id, editItem.value);
        toast(res.status, res.data.message);
        editItem.value = null;
    } catch (e) {
        showErrors(e);
    } finally {
        eSubmitting.value = false;
    }
};
</script>
