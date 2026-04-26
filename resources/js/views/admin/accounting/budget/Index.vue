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
                        <a-table :columns="listColumns" :data-source="budgets" :loading="loading" row-key="id">
                            <template #bodyCell="{ column, record }">
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
                        <label class="form-label small mb-1">From</label>
                        <input type="date" class="form-control form-control-sm" v-model="vsActualFilter.from_date" />
                    </div>
                    <div>
                        <label class="form-label small mb-1">To</label>
                        <input type="date" class="form-control form-control-sm" v-model="vsActualFilter.to_date" />
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
                            <div class="fs-5 fw-bold text-primary">NPR {{ fmtNum(vsActualData.summary.total_budgeted) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Total Actual</div>
                            <div class="fs-5 fw-bold text-warning">NPR {{ fmtNum(vsActualData.summary.total_actual) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Variance</div>
                            <div :class="['fs-5 fw-bold', vsActualData.summary.total_variance >= 0 ? 'text-success' : 'text-danger']">
                                NPR {{ fmtNum(vsActualData.summary.total_variance) }}
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
                                <template v-if="column.key === 'budgeted_amount'">{{ fmtNum(record.budgeted_amount) }}</template>
                                <template v-if="column.key === 'actual_amount'">{{ fmtNum(record.actual_amount) }}</template>
                                <template v-if="column.key === 'variance'">
                                    <span :class="record.variance >= 0 ? 'text-success' : 'text-danger'">
                                        {{ fmtNum(record.variance) }}
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
    <div v-if="formModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingId ? 'Edit' : 'Create' }} Budget</h5>
                    <button type="button" class="btn-close" @click="formModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Fiscal Year <span class="text-danger">*</span></label>
                            <select v-model="form.fiscal_year_id" class="form-select">
                                <option v-for="fy in fiscalYears" :key="fy.id" :value="fy.id">{{ fy.year_code }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Budget Name <span class="text-danger">*</span></label>
                            <input v-model="form.name" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Branch (optional)</label>
                            <select v-model="form.branch_id" class="form-select">
                                <option value="">All Branches</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Search Account</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Type to filter accounts..."
                            v-model="accountSearch"
                        />
                    </div>
                    <h6 class="border-bottom pb-1">Budget Lines</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="min-width:220px">Account <span class="text-danger">*</span></th>
                                    <th style="min-width:120px">Month (1–12, blank=annual)</th>
                                    <th style="min-width:140px">Budgeted Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, i) in form.lines" :key="i">
                                    <td>
                                        <select v-model="line.account_id" class="form-select form-select-sm">
                                            <option value="">— Select Account —</option>
                                            <option
                                                v-for="acc in filteredAccounts"
                                                :key="acc.id"
                                                :value="acc.id">
                                                {{ acc.code }} — {{ acc.name }}
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <input v-model="line.period_month" type="number" min="1" max="12" class="form-control form-control-sm" placeholder="Annual" />
                                    </td>
                                    <td>
                                        <input v-model="line.budgeted_amount" type="number" min="0" step="0.01" class="form-control form-control-sm" />
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="form.lines.splice(i,1)">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addLine">
                        <i class="ti ti-plus me-1"></i> Add Line
                    </button>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="formModal = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveBudget">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        {{ editingId ? 'Save Changes' : 'Create Budget' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const vsActualLoading = ref(false);
const budgets = ref([]);
const fiscalYears = ref([]);
const branches = ref([]);
const accounts = ref([]);
const accountSearch = ref('');
const formModal = ref(false);
const editingId = ref(null);
const viewingBudget = ref(null);
const vsActualData = ref(null);
const vsActualFilter = ref({ from_date: '', to_date: '' });

const emptyLine = () => ({ account_id: '', period_month: '', budgeted_amount: '' });
const form = ref({ fiscal_year_id: '', branch_id: '', name: '', is_active: true, lines: [emptyLine()] });

const filteredAccounts = computed(() => {
    const q = accountSearch.value.trim().toLowerCase();
    if (!q) return accounts.value;
    return accounts.value.filter(
        (a) => a.name.toLowerCase().includes(q) || String(a.code).toLowerCase().includes(q)
    );
});

const listColumns = [
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

onMounted(() => {
    fetchBudgets();
    fetchFiscalYears();
    fetchBranches();
    fetchAccounts();
});

async function fetchBudgets() {
    loading.value = true;
    try {
        const { data } = await apiAdmin('budget');
        budgets.value = data.data;
    } finally { loading.value = false; }
}

async function fetchFiscalYears() {
    try {
        const { data } = await apiAdmin('admin-setting/fiscal-year');
        fiscalYears.value = data.data;
    } catch { /* optional */ }
}

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
    accountSearch.value = '';
    form.value = { fiscal_year_id: '', branch_id: '', name: '', is_active: true, lines: [emptyLine()] };
    formModal.value = true;
}

function openEdit(budget) {
    editingId.value = budget.id;
    accountSearch.value = '';
    form.value = {
        fiscal_year_id: budget.fiscal_year_id,
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

function fmtNum(n) {
    return (n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function progressColor(row) {
    const pct = row.budgeted_amount > 0 ? row.actual_amount / row.budgeted_amount * 100 : 0;
    if (pct > 100) return 'bg-danger';
    if (pct > 80) return 'bg-warning';
    return 'bg-success';
}
</script>
