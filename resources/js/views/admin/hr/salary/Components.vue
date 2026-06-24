<template>
    <PageHeader title="Salary Components" subtitle="Define earnings and deductions" @refresh="fetchComponents">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Component
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search components" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedType || 'Type' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.type = ''">All Types</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.type = 'earning'">Earning</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="filter.type = 'deduction'">Deduction</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="componentColumns"
                    :data-source="components.data" :pagination="false" :loading="components.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (components.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'type'">
                            <span :class="typeBadge(record.type?.value ?? record.type)">
                                {{ record.type_label }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'calculation_type'">
                            {{ record.calculation_type }}
                        </template>
                        <template v-else-if="column.key === 'is_taxable'">
                            <span :class="record.is_taxable ? 'badge bg-warning text-dark' : 'badge bg-secondary'">
                                {{ record.is_taxable ? 'Yes' : 'No' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'account'">
                            <span class="small text-muted">{{ record.account_name || '—' }}</span>
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="components.meta" />
            </div>
        </div>
    </div>

    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Salary Component">
        <template #modal-body>
            <form @submit.prevent="storeComp" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select v-model="cForm.type" class="form-select">
                        <option value="earning">Earning</option>
                        <option value="deduction">Deduction</option>
                        <option value="employer_contribution">Employer Contribution</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calculation</label>
                    <select v-model="cForm.calculation_type" class="form-select">
                        <option value="fixed">Fixed</option>
                        <option value="percentage">Percentage</option>
                    </select>
                </div>
                <div v-if="cForm.calculation_type === 'percentage'" class="col-md-4">
                    <label class="form-label">Percentage Of</label>
                    <select v-model="cForm.percentage_base" class="form-select">
                        <option value="gross_earnings">All Earnings</option>
                        <option value="basic">Basic Salary</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">GL Account
                        <span class="text-muted small">{{ cForm.type === 'employer_contribution' ? '(employer expense — debit)' : '(for journal posting)' }}</span>
                    </label>
                    <select v-model="cForm.account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div v-if="cForm.type === 'employer_contribution'" class="col-md-8">
                    <label class="form-label">Payable Account <span class="text-muted small">(liability — credit)</span></label>
                    <select v-model="cForm.contra_account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div class="col-md-4 pt-3">
                    <div v-if="cForm.type === 'earning'" class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="cForm.is_basic" />
                        <label class="form-check-label">Basic salary</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="cForm.is_taxable" />
                        <label class="form-check-label">Taxable</label>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="createModalOpened = false" class="btn btn-cancel">Cancel</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Salary Component">
        <template #modal-body>
            <form v-if="editItem" @submit.prevent="updateComp" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select v-model="editItem.type" class="form-select">
                        <option value="earning">Earning</option>
                        <option value="deduction">Deduction</option>
                        <option value="employer_contribution">Employer Contribution</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calculation</label>
                    <select v-model="editItem.calculation_type" class="form-select">
                        <option value="fixed">Fixed</option>
                        <option value="percentage">Percentage</option>
                    </select>
                </div>
                <div v-if="editItem.calculation_type === 'percentage'" class="col-md-4">
                    <label class="form-label">Percentage Of</label>
                    <select v-model="editItem.percentage_base" class="form-select">
                        <option value="gross_earnings">All Earnings</option>
                        <option value="basic">Basic Salary</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">GL Account
                        <span class="text-muted small">{{ editTypeValue === 'employer_contribution' ? '(employer expense — debit)' : '(for journal posting)' }}</span>
                    </label>
                    <select v-model="editItem.account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div v-if="editTypeValue === 'employer_contribution'" class="col-md-8">
                    <label class="form-label">Payable Account <span class="text-muted small">(liability — credit)</span></label>
                    <select v-model="editItem.contra_account_id" class="form-select">
                        <option value="">-- Select Account --</option>
                        <option v-for="acc in accountList" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div class="col-md-4 pt-3">
                    <div v-if="editTypeValue === 'earning'" class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="editItem.is_basic" />
                        <label class="form-check-label">Basic salary</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="editItem.is_taxable" />
                        <label class="form-check-label">Taxable</label>
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
import { ref, reactive, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { apiAdmin } from '@/helpers/api.js';
import { componentColumns, createRowActions } from './componentsTableConfig.js';

const payrollStore = usePayrollStore();
const { components } = storeToRefs(payrollStore);
const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const accountList = ref([]);
const cForm = reactive({ name: '', type: 'earning', is_basic: false, calculation_type: 'fixed', percentage_base: 'gross_earnings', is_taxable: false, account_id: '', contra_account_id: '' });

const editTypeValue = computed(() => editItem.value?.type?.value ?? editItem.value?.type);

const typeBadge = (t) => ({ earning: 'badge bg-success', deduction: 'badge bg-danger', employer_contribution: 'badge bg-info text-dark' }[t] ?? 'badge bg-secondary');

const fetchComponents = () => payrollStore.getComponents(filter);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', type: '', page: 1, limit: 10 },
    onFilter: fetchComponents,
});

const selectedType = computed(() => {
    const labels = { earning: 'Earning', deduction: 'Deduction' };
    return labels[filter.type] ?? '';
});

onMounted(async () => {
    try {
        const res = await apiAdmin('account?limit=200');
        accountList.value = res.data.data ?? [];
    } catch { /* ignore */ }
});

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onEdit:   (record) => { editItem.value = record; },
    onDelete: (id) => confirmDelete(
        () => payrollStore.deleteComponent(id),
        fetchComponents,
    ),
});

const storeComp = async () => {
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeComponent(cForm);
        toast(res.status, res.data.message);
        createModalOpened.value = false;
        Object.assign(cForm, { name: '', type: 'earning', is_basic: false, calculation_type: 'fixed', percentage_base: 'gross_earnings', is_taxable: false, account_id: '', contra_account_id: '' });
        fetchComponents();
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};

const updateComp = async () => {
    eSubmitting.value = true;
    const type = editItem.value.type?.value ?? editItem.value.type;
    const payload = {
        name:              editItem.value.name,
        type,
        is_basic:          type === 'earning' ? !!editItem.value.is_basic : false,
        calculation_type:  editItem.value.calculation_type,
        percentage_base:   editItem.value.percentage_base || 'gross_earnings',
        is_taxable:        editItem.value.is_taxable,
        is_active:         editItem.value.is_active,
        account_id:        editItem.value.account_id || null,
        contra_account_id: type === 'employer_contribution' ? (editItem.value.contra_account_id || null) : null,
    };
    try {
        const res = await payrollStore.updateComponent(editItem.value.id, payload);
        toast(res.status, res.data.message);
        editItem.value = null;
    } catch (e) {
        showErrors(e);
    } finally {
        eSubmitting.value = false;
    }
};
</script>
