<template>
    <PageHeader title="Salary Structures" subtitle="Assign salary components to employees" @refresh="fetchStructures">
        <template #actions>
            <button type="button" @click="showCreateModal = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Assign Structure
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search by employee name" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="structureColumns"
                    :data-source="structures.data" :pagination="false" :loading="structures.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (structures.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'employee'">
                            {{ record.employee?.full_name }}
                        </template>
                        <template v-else-if="column.key === 'gross'">
                            {{ grossOf(record) }}
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
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="structures.meta" />
            </div>
        </div>
    </div>

    <!-- Create Structure Modal -->
    <VModal :show-modal="showCreateModal" @close-click="showCreateModal = false" title="Assign Salary Structure" size="lg">
        <template #modal-body>
            <form @submit.prevent="storeStructure" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employee *</label>
                    <select v-model="cForm.employee_id" class="form-select">
                        <option value="">Select Employee</option>
                        <option v-for="e in employees.data" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <VDatepicker id="effective_from" v-model="cForm.effective_from" label="Effective From" required />
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Salary Components</h6>
                        <button type="button" @click="addItem" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-plus"></i> Add
                        </button>
                    </div>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr><th>Component</th><th>Amount</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in cForm.items" :key="idx">
                                <td>
                                    <select v-model="item.salary_component_id" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        <option v-for="c in components.data" :key="c.id" :value="c.id">
                                            {{ c.name }} ({{ c.type_label }})
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" v-model="item.amount" class="form-control form-control-sm" step="0.01" />
                                </td>
                                <td>
                                    <button type="button" @click="cForm.items.splice(idx, 1)"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="showCreateModal = false" class="btn btn-cancel">Cancel</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <!-- View Structure Modal -->
    <VModal :show-modal="!!viewItem" @close-click="viewItem = null" title="Salary Structure Details">
        <template #modal-body>
            <div v-if="viewItem">
                <p><strong>Employee:</strong> {{ viewItem.employee?.full_name }}</p>
                <p><strong>Effective From:</strong> {{ viewItem.effective_from }}</p>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr><th>Component</th><th>Type</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in viewItem.items" :key="item.id">
                            <td>{{ item.component?.name }}</td>
                            <td>{{ item.component?.type_label }}</td>
                            <td class="text-end">{{ formatMoney(item.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { formatMoney } from '@/helpers/formatMoney.js';
import { ref, reactive, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import VPagination from '@/components/base/VPagination.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { usePayrollStore } from '@/stores/admin/hr/payroll.js';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { structureColumns, createRowActions } from './structureTableConfig.js';

const payrollStore = usePayrollStore();
const empStore = useEmployeeStore();
const { structures, components } = storeToRefs(payrollStore);
const { employees } = storeToRefs(empStore);

const showCreateModal = ref(false);
const viewItem = ref(null);
const cSubmitting = ref(false);
const cForm = reactive({ employee_id: '', effective_from: '', items: [{ salary_component_id: '', amount: 0 }] });

const fetchStructures = () => {
    payrollStore.getStructures(filter);
};

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 10 },
    onFilter: fetchStructures,
});

onMounted(() => {
    payrollStore.getComponents({ limit: 200 });
    empStore.getEmployees({ limit: 200 });
});

const addItem = () => cForm.items.push({ salary_component_id: '', amount: 0 });

const grossOf = (s) =>
    s.items?.filter(i => i.component?.type_label === 'Earning').reduce((sum, i) => sum + i.amount, 0) ?? '—';

const { confirmDelete } = useConfirmAction();

const rowActions = createRowActions({
    onView:   (record) => { viewItem.value = record; },
    onDelete: (id) => confirmDelete(
        () => payrollStore.deleteStructure(id),
        fetchStructures,
    ),
});

const storeStructure = async () => {
    cSubmitting.value = true;
    try {
        const res = await payrollStore.storeStructure(cForm);
        toast(res.status, res.data.message);
        showCreateModal.value = false;
        Object.assign(cForm, { employee_id: '', effective_from: '', items: [{ salary_component_id: '', amount: 0 }] });
        fetchStructures();
    } catch (e) {
        showErrors(e);
    } finally {
        cSubmitting.value = false;
    }
};
</script>
