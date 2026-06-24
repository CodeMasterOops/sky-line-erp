<template>
    <PageHeader title="Budget Management" subtitle="Set budgets and track actuals" @refresh="fetchBudgets">
        <template #actions>
            <button v-can="'create_budget'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> New Budget
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div v-if="!viewingBudget">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <a-table :columns="listColumns" :data-source="budgets" :loading="loading" :pagination="false" row-key="id">
                            <template #bodyCell="{ column, record, index }">
                                <template v-if="column.key === 'sn'">
                                    {{ (listMeta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                                </template>
                                <template v-if="column.key === 'is_active'">
                                    <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                        {{ record.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </template>
                                <template v-if="column.key === 'action'">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary" @click="openVsActual(record)">
                                            <i class="ti ti-chart-bar me-1"></i> vs Actual
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" @click="openEdit(record)">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" @click="deleteBudget(record.id)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </template>
                            </template>
                        </a-table>
                        <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="listMeta" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget vs Actual Report -->
        <div v-else>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-0">{{ viewingBudget.name }} — Budget vs Actual</h5>
                    <small class="text-muted">{{ viewingBudget.fiscal_year?.year_code }}</small>
                </div>
                <div class="d-flex gap-2 align-items-end">
                    <div>
                        <VDatepicker id="vs_from_date" label="From" v-model="vsActualFilter.from_date" />
                    </div>
                    <div>
                        <VDatepicker id="vs_to_date" label="To" v-model="vsActualFilter.to_date" />
                    </div>
                    <button class="btn btn-sm btn-primary" @click="loadVsActual">Apply</button>
                    <button class="btn btn-sm btn-outline-secondary" @click="viewingBudget = null">Back</button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-3" v-if="vsActualData">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Total Budgeted</div>
                            <div class=" fw-bold text-primary">{{ formatMoney(vsActualData.summary.total_budgeted) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Total Actual</div>
                            <div class=" fw-bold text-warning">{{ formatMoney(vsActualData.summary.total_actual) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Variance</div>
                            <div :class="[' fw-bold', vsActualData.summary.total_variance >= 0 ? 'text-success' : 'text-danger']">
                                {{ formatMoney(vsActualData.summary.total_variance) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <a-table :columns="vsActualColumns" :data-source="vsActualData?.rows ?? []" :loading="vsActualLoading" row-key="account_id">
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'budgeted_amount'">{{ formatMoney(record.budgeted_amount) }}</template>
                                <template v-if="column.key === 'actual_amount'">{{ formatMoney(record.actual_amount) }}</template>
                                <template v-if="column.key === 'variance'">
                                    <span :class="record.variance >= 0 ? 'text-success' : 'text-danger'">
                                        {{ formatMoney(record.variance) }}
                                        <small v-if="record.variance_pct !== null">({{ record.variance_pct }}%)</small>
                                    </span>
                                </template>
                                <template v-if="column.key === 'progress'">
                                    <div class="progress" style="height:6px;min-width:80px">
                                        <div class="progress-bar" :class="progressColor(record)"
                                            :style="`width:${Math.min(100, record.budgeted_amount > 0 ? record.actual_amount/record.budgeted_amount*100 : 0)}%`"></div>
                                    </div>
                                    <small>{{ record.budgeted_amount > 0 ? Math.round(record.actual_amount/record.budgeted_amount*100) : 0 }}%</small>
                                </template>
                            </template>
                        </a-table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Create / Edit Budget Modal -->
    <VModal
        :show-modal="formModal"
        size="xl"
        :title="editingId ? 'Edit Budget' : 'Create Budget'"
        @close-click="formModal = false">
        <template #modal-body>
            <form @submit.prevent="saveBudget" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="budget_name"
                        v-model="form.name"
                        label="Budget Name"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="budget_branch"
                        v-model="form.branch_id"
                        label="Branch"
                        placeholder="All Branches"
                        :options="branches"
                    />
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Budget Lines</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addLine">
                            <i class="ti ti-plus me-1"></i> Add Line
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px">SN</th>
                                    <th style="min-width:260px">Account <VRequiredMark /></th>
                                    <th style="width:160px">Month <small class="text-muted">(1–12, blank = annual)</small></th>
                                    <th style="width:160px">Budgeted Amount</th>
                                    <th style="width:60px" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, i) in form.lines" :key="i">
                                    <td>{{ i + 1 }}</td>
                                    <td>
                                        <VMultiselect
                                            v-model="line.account_id"
                                            :options="accountOptions"
                                            placeholder="Account"
                                        />
                                    </td>
                                    <td>
                                        <VInput
                                            v-model="line.period_month"
                                            input-type="number"
                                            :min-value="1"
                                            :max-value="12"
                                            placeholder="Annual"
                                        />
                                    </td>
                                    <td>
                                        <VInput
                                            v-model="line.budgeted_amount"
                                            input-type="number"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            @click="form.lines.splice(i, 1)"
                                            :disabled="form.lines.length === 1">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="formModal = false">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        {{ editingId ? 'Save Changes' : 'Create Budget' }}
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import { ref, computed, onMounted } from 'vue';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const vsActualLoading = ref(false);
const budgets = ref([]);
const listMeta = ref({ total: 0, current_page: 1, per_page: 10, from: null, to: null, last_page: 1 });
const branches = ref([]);
const accounts = ref([]);
const formModal = ref(false);
const editingId = ref(null);
const viewingBudget = ref(null);
const vsActualData = ref(null);
const vsActualFilter = ref({ from_date: '', to_date: '' });

const emptyLine = () => ({ account_id: '', period_month: '', budgeted_amount: '' });
const form = ref({ branch_id: '', name: '', is_active: true, lines: [emptyLine()] });

const accountOptions = computed(() =>
    accounts.value.map((a) => ({ id: a.id, name: `${a.code} — ${a.name}` }))
);

const listColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Fiscal Year', key: 'fiscal_year', dataIndex: ['fiscal_year', 'year_code'] },
    { title: 'Branch', key: 'branch', dataIndex: ['branch', 'name'] },
    { title: 'Status', key: 'is_active' },
    { title: 'Action', key: 'action' },
];

const vsActualColumns = [
    { title: 'Account', key: 'account_name', dataIndex: 'account_name' },
    { title: 'Code', dataIndex: 'account_code', key: 'account_code' },
    { title: 'Month', dataIndex: 'period_month', key: 'period_month' },
    { title: 'Budgeted', key: 'budgeted_amount' },
    { title: 'Actual', key: 'actual_amount' },
    { title: 'Variance', key: 'variance' },
    { title: 'Usage', key: 'progress' },
];

const { filter, fetch: fetchBudgets } = usePaginatedList({
    fetchFn: async ({ filter }) => {
        loading.value = true;
        try {
            const params = new URLSearchParams({
                page: String(filter.page),
                limit: String(filter.limit),
            });
            const { data } = await apiAdmin(`budget?${params}`);
            budgets.value = data.data ?? [];
            listMeta.value = data.meta ?? { total: budgets.value.length };
        } finally { loading.value = false; }
    },
    defaults: { page: 1, limit: 10 },
});

onMounted(() => {
    fetchBranches();
    fetchAccounts();
});

async function fetchBranches() {
    try {
        const { data } = await apiAdmin('branch');
        branches.value = data.data;
    } catch { /* optional */ }
}

async function fetchAccounts() {
    try {
        const { data } = await apiAdmin('account', 'get', { per_page: 1000 });
        accounts.value = data.data ?? [];
    } catch { /* optional */ }
}

function addLine() {
    form.value.lines.push(emptyLine());
}

function openCreate() {
    editingId.value = null;
    form.value = { branch_id: '', name: '', is_active: true, lines: [emptyLine()] };
    formModal.value = true;
}

function openEdit(budget) {
    editingId.value = budget.id;
    form.value = {
        branch_id: budget.branch_id ?? '',
        name: budget.name,
        is_active: budget.is_active,
        lines: (budget.lines ?? []).map((l) => ({
            account_id: l.account_id,
            period_month: l.period_month ?? '',
            budgeted_amount: l.budgeted_amount,
        })),
    };
    if (!form.value.lines.length) form.value.lines.push(emptyLine());
    formModal.value = true;
}

async function saveBudget() {
    saving.value = true;
    try {
        if (editingId.value) {
            await apiAdmin(`budget/${editingId.value}`, 'put', form.value);
            toast('success', 'Budget updated successfully.');
        } else {
            await apiAdmin('budget', 'post', form.value);
            toast('success', 'Budget created successfully.');
        }
        formModal.value = false;
        fetchBudgets();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
}

async function openVsActual(budget) {
    viewingBudget.value = budget;
    await loadVsActual();
}

async function loadVsActual() {
    vsActualLoading.value = true;
    try {
        const params = new URLSearchParams(
            Object.fromEntries(Object.entries(vsActualFilter.value).filter(([, v]) => v))
        );
        const { data } = await apiAdmin(`budget/${viewingBudget.value.id}/vs-actual?${params}`);
        vsActualData.value = data.data;
    } finally { vsActualLoading.value = false; }
}

async function deleteBudget(id) {
    await apiAdmin(`budget/${id}`, 'delete');
    toast('success', 'Budget deleted.');
    fetchBudgets();
}

function progressColor(row) {
    const pct = row.budgeted_amount > 0 ? row.actual_amount / row.budgeted_amount * 100 : 0;
    if (pct > 100) return 'bg-danger';
    if (pct > 80) return 'bg-warning';
    return 'bg-success';
}
</script>
