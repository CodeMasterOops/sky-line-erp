<template>
    <PageHeader title="Bank Reconciliation" subtitle="Match bank statement lines to GL entries" />

    <div class="row g-3">
        <!-- Left: Bank account cards -->
        <div class="col-md-3">
            <div class="card border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Bank Accounts</h6>
                    <button class="btn btn-sm btn-primary" @click="showAddAccount = true" title="Add bank account">
                        <i class="ti ti-plus"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div v-if="!bankAccounts.length" class="p-4 text-center text-muted small">
                        <i class="ti ti-building-bank display-6 d-block mb-2"></i>
                        No bank accounts.<br />Add one to get started.
                    </div>
                    <div
                        v-for="ba in bankAccounts"
                        :key="ba.id"
                        class="p-3 border-bottom"
                        :class="selectedAccount?.id === ba.id ? 'bg-primary-subtle border-start border-primary border-3' : ''"
                        style="cursor: pointer"
                        @click="selectAccount(ba)"
                    >
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-semibold">{{ ba.bank_name }}</div>
                            <span
                                v-if="ba.unreconciled_count"
                                class="badge bg-warning text-dark"
                                :title="`${ba.unreconciled_count} unreconciled`"
                            >
                                {{ ba.unreconciled_count }}
                            </span>
                        </div>
                        <div class="text-muted small">{{ maskAccount(ba.account_number) }}</div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span class="text-muted">Book</span>
                            <span class="fw-semibold">{{ formatMoney(ba.book_balance ?? 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Statement</span>
                            <span>{{ formatMoney(ba.statement_balance ?? 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Difference</span>
                            <span :class="Math.abs(ba.difference ?? 0) < 0.01 ? 'text-success' : 'text-danger fw-semibold'">
                                {{ formatMoney(ba.difference ?? 0) }}
                                <i v-if="Math.abs(ba.difference ?? 0) < 0.01" class="ti ti-check"></i>
                            </span>
                        </div>
                        <div v-if="ba.last_reconciled_at" class="text-muted small mt-1">
                            <i class="ti ti-history me-1"></i>Reconciled {{ formatDate(ba.last_reconciled_at) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Statement lines -->
        <div class="col-md-9">
            <div v-if="!selectedAccount" class="card border-0 text-center py-5 text-muted">
                <i class="ti ti-building-bank display-4 d-block mb-3"></i>
                <p>Select a bank account to view statement lines.</p>
            </div>

            <template v-else>
                <!-- Filters / actions -->
                <div class="card border-0 mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-auto">
                                <VDatepicker id="start_date" label="Start Date" v-model="filters.start_date" />
                            </div>
                            <div class="col-md-auto">
                                <VDatepicker id="end_date" label="End Date" v-model="filters.end_date" />
                            </div>
                            <div class="col-md-auto">
                                <label class="form-label small mb-1">Status</label>
                                <select class="form-select form-select-sm" v-model="filters.status">
                                    <option value="">All</option>
                                    <option value="unmatched">Unmatched</option>
                                    <option value="matched">Matched</option>
                                </select>
                            </div>
                            <div class="col-md-auto d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-primary" @click="loadLines" :disabled="loading">
                                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                                    Load
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="autoMatch" title="Auto match by amount and date">
                                    <i class="ti ti-wand me-1"></i> Auto Match
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="applyRules" title="Post bank-only lines via matching rules">
                                    <i class="ti ti-filter-cog me-1"></i> Apply Rules
                                </button>
                                <button class="btn btn-sm btn-outline-success" @click="openReconcile">
                                    <i class="ti ti-checks me-1"></i> Reconcile
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="openHistory">
                                    <i class="ti ti-history me-1"></i> History
                                </button>
                                <button class="btn btn-sm btn-outline-primary" @click="openImport">
                                    <i class="ti ti-file-import me-1"></i> Import CSV/XLSX
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary cards -->
                <div v-if="summary" class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="card border-0 bg-info-subtle p-3 text-center">
                            <div class="text-muted small">GL Balance</div>
                            <div class="fw-bold">{{ formatMoney(summary.gl_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-primary-subtle p-3 text-center">
                            <div class="text-muted small">Statement Balance</div>
                            <div class="fw-bold">{{ formatMoney(summary.statement_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 p-3 text-center" :class="Math.abs(summary.difference) < 0.01 ? 'bg-success-subtle' : 'bg-danger-subtle'">
                            <div class="text-muted small">Difference</div>
                            <div
                                class="fw-bold"
                                :class="Math.abs(summary.difference) < 0.01 ? 'text-success' : 'text-danger'"
                            >
                                {{ formatMoney(summary.difference) }}
                            </div>
                        </div>
                    </div>
                    <div v-if="summary.unmatched_count" class="col-12">
                        <div class="alert alert-warning py-2 mb-0 small">
                            <i class="ti ti-alert-triangle me-1"></i>
                            {{ summary.unmatched_count }} unmatched line{{ summary.unmatched_count !== 1 ? 's' : '' }}.
                            Use <strong>Auto Match</strong>, <strong>Apply Rules</strong>, or create / park each line.
                        </div>
                    </div>
                </div>

                <!-- Statement lines table -->
                <div class="card border-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Statement Lines — {{ selectedAccount.bank_name }}</h6>
                        <span v-if="lines.length" class="badge bg-secondary">{{ lines.length }} lines</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th>Status</th>
                                        <th>GL Ref</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="loading">
                                        <td colspan="8" class="text-center py-4">
                                            <span class="spinner-border spinner-border-sm"></span>
                                        </td>
                                    </tr>
                                    <tr v-else-if="!lines.length">
                                        <td colspan="8" class="text-center text-muted py-5">
                                            No statement lines. Import a CSV or click Load.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="line in lines"
                                        :key="line.id"
                                        :class="line.status === 'matched' ? 'table-success' : ''"
                                    >
                                        <td class="text-nowrap">{{ formatDate(line.transaction_date) }}</td>
                                        <td>{{ line.description }}</td>
                                        <td class="text-muted small">{{ line.reference }}</td>
                                        <td class="text-end">{{ line.debit > 0 ? formatMoney(line.debit) : '—' }}</td>
                                        <td class="text-end">{{ line.credit > 0 ? formatMoney(line.credit) : '—' }}</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="line.status === 'matched' ? 'bg-success' : 'bg-warning text-dark'"
                                            >
                                                {{ line.status }}
                                            </span>
                                            <span v-if="line.match_type" class="badge bg-light text-muted ms-1">{{ line.match_type }}</span>
                                        </td>
                                        <td class="text-muted small">
                                            {{ line.journal_item?.journal?.voucher_no || '—' }}
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <template v-if="line.status === 'matched'">
                                                <button class="btn btn-xs btn-outline-danger" @click="unmatch(line.id)">
                                                    Unmatch
                                                </button>
                                            </template>
                                            <template v-else>
                                                <button class="btn btn-xs btn-outline-primary me-1" @click="openCreateEntry(line)" title="Create a GL entry for this line">
                                                    Create
                                                </button>
                                                <button class="btn btn-xs btn-outline-secondary" @click="parkSuspense(line.id)" title="Park to suspense account">
                                                    Park
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Import wizard (CSV / XLSX → map → preview → confirm) -->
    <BankStatementImportWizard ref="importWizard" @imported="refresh" />

    <!-- Create Entry Modal -->
    <div v-if="createEntryLine" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create GL Entry</h5>
                    <button type="button" class="btn-close" @click="createEntryLine = null"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Posts a balanced journal between the bank ledger and the chosen account for this
                        statement line ({{ createEntryLine.credit > 0 ? 'money in' : 'money out' }}:
                        <strong>{{ formatMoney(createEntryLine.credit > 0 ? createEntryLine.credit : createEntryLine.debit) }}</strong>).
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Contra Account <span class="text-danger">*</span></label>
                        <Multiselect
                            v-model="createEntryContra"
                            :options="glAccountOptions"
                            value-prop="value"
                            label="label"
                            :searchable="true"
                            placeholder="e.g. Bank Charges, Interest Income"
                        />
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="createEntryLine = null">Cancel</button>
                    <button class="btn btn-primary" @click="submitCreateEntry" :disabled="!createEntryContra || savingEntry">
                        <span v-if="savingEntry" class="spinner-border spinner-border-sm me-1"></span>
                        Post & Match
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconcile Modal -->
    <div v-if="showReconcile" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reconcile {{ selectedAccount?.bank_name }}</h5>
                    <button type="button" class="btn-close" @click="showReconcile = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <VDatepicker id="rec_start" label="Period Start" v-model="reconcileForm.period_start" />
                        </div>
                        <div class="col-6">
                            <VDatepicker id="rec_end" label="Period End" v-model="reconcileForm.period_end" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Statement Closing Balance <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" v-model.number="reconcileForm.statement_closing_balance" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" rows="2" v-model="reconcileForm.notes"></textarea>
                        </div>
                    </div>
                    <div v-if="draftReconciliation" class="alert mt-3 mb-0" :class="Math.abs(draftReconciliation.difference) < 0.01 ? 'alert-success' : 'alert-warning'">
                        Book balance {{ formatMoney(draftReconciliation.gl_balance) }} vs statement
                        {{ formatMoney(draftReconciliation.statement_closing_balance) }} —
                        difference <strong>{{ formatMoney(draftReconciliation.difference) }}</strong>.
                        <span v-if="Math.abs(draftReconciliation.difference) >= 0.01">Match or park remaining lines before completing.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showReconcile = false">Close</button>
                    <button v-if="!draftReconciliation" class="btn btn-primary" @click="startReconcile" :disabled="savingReconcile">
                        <span v-if="savingReconcile" class="spinner-border spinner-border-sm me-1"></span>
                        Start
                    </button>
                    <button v-else class="btn btn-success" @click="completeReconcile" :disabled="savingReconcile">
                        <span v-if="savingReconcile" class="spinner-border spinner-border-sm me-1"></span>
                        Complete &amp; Lock
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistory" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reconciliation History</h5>
                    <button type="button" class="btn-close" @click="showHistory = false"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Closing</th>
                                <th class="text-end">GL</th>
                                <th class="text-end">Difference</th>
                                <th>Status</th>
                                <th>By</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!history.length">
                                <td colspan="7" class="text-center text-muted py-4">No reconciliations yet.</td>
                            </tr>
                            <tr v-for="r in history" :key="r.id">
                                <td>{{ formatDate(r.period_start) }} – {{ formatDate(r.period_end) }}</td>
                                <td class="text-end">{{ formatMoney(r.statement_closing_balance) }}</td>
                                <td class="text-end">{{ formatMoney(r.gl_balance) }}</td>
                                <td class="text-end">{{ formatMoney(r.difference) }}</td>
                                <td><span class="badge" :class="r.status === 'locked' ? 'bg-success' : 'bg-secondary'">{{ r.status }}</span></td>
                                <td class="small">{{ r.reconciled_by?.name || '—' }}</td>
                                <td class="small text-muted">{{ r.reconciled_at ? formatDate(r.reconciled_at) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showHistory = false">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div v-if="showAddAccount" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Bank Account</h5>
                    <button type="button" class="btn-close" @click="showAddAccount = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">GL Account (Cash / Bank) <span class="text-danger">*</span></label>
                        <Multiselect
                            v-model="newAccount.account_id"
                            :options="glAccountOptions"
                            value-prop="value"
                            label="label"
                            :searchable="true"
                            placeholder="Select account"
                        />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="newAccount.bank_name" placeholder="e.g. Nabil Bank" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" v-model="newAccount.account_number" />
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Opening Balance</label>
                            <input type="number" step="0.01" class="form-control" v-model.number="newAccount.opening_balance" />
                        </div>
                        <div class="col-6">
                            <VDatepicker id="ob_date" label="Opening Date" v-model="newAccount.opening_balance_date" />
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Branch</label>
                        <input type="text" class="form-control" v-model="newAccount.branch" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showAddAccount = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveBankAccount" :disabled="savingAccount">
                        <span v-if="savingAccount" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { formatDate } from '@/helpers/helper.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import VDatepicker from '@/components/base/VDatepicker.vue';
import BankStatementImportWizard from './BankStatementImportWizard.vue';

const bankAccounts = ref([]);
const selectedAccount = ref(null);
const lines = ref([]);
const summary = ref(null);
const loading = ref(false);
const showAddAccount = ref(false);
const savingAccount = ref(false);
const glAccountOptions = ref([]);
const filters = ref({ start_date: '', end_date: '', status: '' });
const newAccount = ref({ account_id: null, bank_name: '', account_number: '', branch: '', opening_balance: 0, opening_balance_date: '' });

const importWizard = ref(null);
const openImport = () => importWizard.value?.show(selectedAccount.value.id);

// Create-on-match
const createEntryLine = ref(null);
const createEntryContra = ref(null);
const savingEntry = ref(false);

// Reconciliation session
const showReconcile = ref(false);
const savingReconcile = ref(false);
const reconcileForm = ref({ period_start: '', period_end: '', statement_closing_balance: 0, notes: '' });
const draftReconciliation = ref(null);

// History
const showHistory = ref(false);
const history = ref([]);

const maskAccount = (no) => {
    if (!no) return '';
    const s = String(no);
    return s.length <= 4 ? s : `••••${s.slice(-4)}`;
};

const loadBankAccounts = async () => {
    try {
        const res = await apiAdmin('bank-reconciliation/bank-accounts', 'get');
        bankAccounts.value = res.data.data || [];
        // Keep the selected account's card data fresh.
        if (selectedAccount.value) {
            const fresh = bankAccounts.value.find((b) => b.id === selectedAccount.value.id);
            if (fresh) selectedAccount.value = fresh;
        }
    } catch (e) {
        showErrors(e);
    }
};

const loadGlAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { limit: 500 });
        glAccountOptions.value = (res.data.data || []).map((a) => ({
            label: `${a.name} (${a.code})`,
            value: a.id,
        }));
    } catch { /* ignore */ }
};

const selectAccount = async (ba) => {
    selectedAccount.value = ba;
    lines.value = [];
    summary.value = null;
    await loadLines();
};

const loadLines = async () => {
    if (!selectedAccount.value) return;
    loading.value = true;
    try {
        const params = {};
        if (filters.value.start_date) params.start_date = filters.value.start_date;
        if (filters.value.end_date) params.end_date = filters.value.end_date;
        if (filters.value.status) params.status = filters.value.status;

        const res = await apiAdmin(
            `bank-reconciliation/bank-accounts/${selectedAccount.value.id}/statement-lines`,
            'get',
            params,
        );
        lines.value = res.data.data || [];
        summary.value = res.data.summary;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const refresh = async () => {
    await loadLines();
    await loadBankAccounts();
};

const autoMatch = async () => {
    try {
        const res = await apiAdmin(`bank-reconciliation/bank-accounts/${selectedAccount.value.id}/auto-match`, 'post');
        toast('success', res.data.message);
        await refresh();
    } catch (e) {
        showErrors(e);
    }
};

const applyRules = async () => {
    try {
        const res = await apiAdmin(`bank-reconciliation/bank-accounts/${selectedAccount.value.id}/apply-rules`, 'post');
        toast('success', res.data.message);
        await refresh();
    } catch (e) {
        showErrors(e);
    }
};

const unmatch = async (id) => {
    try {
        await apiAdmin(`bank-reconciliation/statement-lines/${id}/unmatch`, 'post');
        await refresh();
    } catch (e) {
        showErrors(e);
    }
};

const openCreateEntry = (line) => {
    createEntryLine.value = line;
    createEntryContra.value = null;
};

const submitCreateEntry = async () => {
    if (!createEntryContra.value || !createEntryLine.value) return;
    savingEntry.value = true;
    try {
        await apiAdmin(`bank-reconciliation/statement-lines/${createEntryLine.value.id}/create-entry`, 'post', {
            contra_account_id: createEntryContra.value,
        });
        toast('success', 'Entry created and matched.');
        createEntryLine.value = null;
        await refresh();
    } catch (e) {
        showErrors(e);
    } finally {
        savingEntry.value = false;
    }
};

const parkSuspense = async (id) => {
    try {
        await apiAdmin(`bank-reconciliation/statement-lines/${id}/park-suspense`, 'post');
        toast('success', 'Line parked to suspense.');
        await refresh();
    } catch (e) {
        showErrors(e);
    }
};

const openReconcile = () => {
    draftReconciliation.value = null;
    reconcileForm.value = {
        period_start: filters.value.start_date || '',
        period_end: filters.value.end_date || '',
        statement_closing_balance: summary.value?.statement_balance ?? 0,
        notes: '',
    };
    showReconcile.value = true;
};

const startReconcile = async () => {
    savingReconcile.value = true;
    try {
        const res = await apiAdmin(
            `bank-reconciliation/bank-accounts/${selectedAccount.value.id}/reconciliations`,
            'post',
            reconcileForm.value,
        );
        draftReconciliation.value = res.data.data;
        toast('success', 'Reconciliation started.');
    } catch (e) {
        showErrors(e);
    } finally {
        savingReconcile.value = false;
    }
};

const completeReconcile = async () => {
    if (!draftReconciliation.value) return;
    savingReconcile.value = true;
    try {
        await apiAdmin(`bank-reconciliation/reconciliations/${draftReconciliation.value.id}/complete`, 'post');
        toast('success', 'Reconciliation completed and locked.');
        showReconcile.value = false;
        draftReconciliation.value = null;
        await refresh();
    } catch (e) {
        showErrors(e);
    } finally {
        savingReconcile.value = false;
    }
};

const openHistory = async () => {
    showHistory.value = true;
    try {
        const res = await apiAdmin(`bank-reconciliation/bank-accounts/${selectedAccount.value.id}/reconciliations`, 'get');
        history.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    }
};

const saveBankAccount = async () => {
    savingAccount.value = true;
    try {
        await apiAdmin('bank-reconciliation/bank-accounts', 'post', newAccount.value);
        toast('success', 'Bank account added.');
        showAddAccount.value = false;
        newAccount.value = { account_id: null, bank_name: '', account_number: '', branch: '', opening_balance: 0, opening_balance_date: '' };
        await loadBankAccounts();
    } catch (e) {
        showErrors(e);
    } finally {
        savingAccount.value = false;
    }
};

onMounted(() => {
    loadBankAccounts();
    loadGlAccounts();
});
</script>
