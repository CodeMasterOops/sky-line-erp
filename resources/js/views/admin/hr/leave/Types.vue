<template>
    <PageHeader title="Leave Types" subtitle="Configure leave types" @refresh="fetchLeaveTypes">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Type
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search leave types" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="leaveTypeColumns"
                    :data-source="leaveTypes.data" :pagination="false" :loading="leaveTypes.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (leaveTypes.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'days_allowed'">
                            {{ record.days_allowed }}
                        </template>
                        <template v-else-if="column.key === 'is_paid'">
                            <span :class="record.is_paid ? 'badge bg-success' : 'badge bg-secondary'">
                                {{ record.is_paid ? 'Yes' : 'No' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                {{ record.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="leaveTypes.meta" />
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Leave Type">
        <template #modal-body>
            <form @submit.prevent="storeType" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-3"><VInput v-model="cForm.days_allowed" label="Days Allowed" type="number" /></div>
                <div class="col-md-3 pt-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" v-model="cForm.is_paid" />
                        <label class="form-check-label">Paid</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="createModalOpened = false" class="btn btn-cancel">Cancel</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <!-- Edit Modal -->
    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Leave Type">
        <template #modal-body>
            <form v-if="editItem" @submit.prevent="updateType" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-3"><VInput v-model="editItem.days_allowed" label="Days Allowed" type="number" /></div>
                <div class="col-md-3 pt-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" v-model="editItem.is_paid" />
                        <label class="form-check-label">Paid</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="editItem.is_active" />
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="editItem = null" class="btn btn-cancel">Cancel</button>
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
import { useLeaveStore } from '@/stores/admin/hr/leave.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { leaveTypeColumns, createRowActions } from './typesTableConfig.js';

const leaveStore = useLeaveStore();
const { leaveTypes } = storeToRefs(leaveStore);
const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const cForm = reactive({ name: '', days_allowed: 0, is_paid: true });

const fetchLeaveTypes = () => leaveStore.getLeaveTypes(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchLeaveTypes,
});

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onEdit: (record) => { editItem.value = record; },
    onDelete: (id) => confirmDelete(
        () => leaveStore.deleteLeaveType(id),
        fetchLeaveTypes,
    ),
});

const storeType = async () => {
    cSubmitting.value = true;
    try {
        const res = await leaveStore.storeLeaveType(cForm);
        toast(res.status, res.data.message);
        createModalOpened.value = false;
        Object.assign(cForm, { name: '', days_allowed: 0, is_paid: true });
        fetchLeaveTypes();
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};

const updateType = async () => {
    eSubmitting.value = true;
    try {
        const res = await leaveStore.updateLeaveType(editItem.value.id, editItem.value);
        toast(res.status, res.data.message);
        editItem.value = null;
    } catch (e) {
        showErrors(e);
    } finally {
        eSubmitting.value = false;
    }
};
</script>
