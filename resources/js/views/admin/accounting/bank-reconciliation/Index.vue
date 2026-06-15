<template>
    <PageHeader title="Bank Reconciliation" subtitle="Match bank statement lines to GL entries" />

    <div class="row g-3">
        <!-- Left: Bank account list -->
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
                        class="p-3 border-bottom cursor-pointer"
                        :class="selectedAccount?.id === ba.id ? 'bg-primary-subtle border-start border-primary border-3' : ''"
                        style="cursor: pointer"
                        @click="selectAccount(ba)"
                    >
                        <div class="fw-semibold">{{ ba.bank_name }}</div>
                        <div class="text-muted small">{{ ba.account_number }}</div>
                        <div class="text-muted small">{{ ba.account?.name }}</div>
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
                <!-- Filters -->
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
                            <div class="col-md-auto d-flex gap-2">
                                <button class="btn btn-sm btn-primary" @click="loadLines" :disabled="loading">
                                    <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                                    Load
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="autoMatch" title="Auto match by amount and date">
                                    <i class="ti ti-wand me-1"></i> Auto Match
                                </button>
                                <button class="btn btn-sm btn-outline-primary" @click="showImport = true">
                                    <i class="ti ti-file-import me-1"></i> Import CSV
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
                    <div
                        class="col-4"
                        :class="Math.abs(summary.difference) < 0.01 ? 'bg-success-subtle' : 'bg-danger-subtle'"
                    >
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
                            Use <strong>Auto Match</strong> or match manually.
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
                                        <th>Action</th>
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
                                        </td>
                                        <td class="text-muted small">
                                            {{ line.journal_item?.journal?.voucher_no || '—' }}
                                        </td>
                                        <td>
                                            <button
                                                v-if="line.status === 'matched'"
                                                class="btn btn-xs btn-outline-danger"
                                                @click="unmatch(line.id)"
                                            >
                                                Unmatch
                                            </button>
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

    <!-- Import CSV Modal -->
    <div v-if="showImport" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Bank Statement CSV</h5>
                    <button type="button" class="btn-close" @click="closeImportModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Bank Format</label>
                        <select class="form-select" v-model="importBank">
                            <option value="auto">Auto-detect</option>
                            <option value="nmb">NMB Bank</option>
                            <option value="nabil">Nabil Bank</option>
                            <option value="himalayan">Himalayan Bank</option>
                            <option value="global_ime">Global IME Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CSV File <span class="text-danger">*</span></label>
                        <input
                            type="file"
                            class="form-control"
                            accept=".csv,text/csv"
                            ref="csvFileInput"
                            @change="onCsvFileChange"
                        />
                        <div v-if="importFile" class="form-text text-success mt-1">
                            <i class="ti ti-check me-1"></i>{{ importFile.name }}
                        </div>
                    </div>
                    <div v-if="importResult" class="alert" :class="importResult.error ? 'alert-danger' : 'alert-success'">
                        <template v-if="importResult.error">
                            <i class="ti ti-alert-circle me-1"></i>{{ importResult.error }}
                        </template>
                        <template v-else>
                            <i class="ti ti-check-circle me-1"></i>
                            Imported <strong>{{ importResult.imported }}</strong> lines
                            <span v-if="importResult.skipped"> ({{ importResult.skipped }} skipped)</span>.
                        </template>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeImportModal">Cancel</button>
                    <button
                        class="btn btn-primary"
                        @click="submitCsvImport"
                        :disabled="!importFile || importing"
                    >
                        <span v-if="importing" class="spinner-border spinner-border-sm me-1"></span>
                        Import
                    </button>
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
                    <div class="mb-3">
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

const bankAccounts = ref([]);
const selectedAccount = ref(null);
const lines = ref([]);
const summary = ref(null);
const loading = ref(false);
const showAddAccount = ref(false);
const savingAccount = ref(false);
const showImport = ref(false);
const glAccountOptions = ref([]);
const filters = ref({ start_date: '', end_date: '', status: '' });
const newAccount = ref({ account_id: null, bank_name: '', account_number: '', branch: '' });

const importBank = ref('auto');
const importFile = ref(null);
const importing = ref(false);
const importResult = ref(null);
const csvFileInput = ref(null);

const onCsvFileChange = (e) => {
    importFile.value = e.target.files?.[0] ?? null;
    importResult.value = null;
};

const submitCsvImport = async () => {
    if (!importFile.value || !selectedAccount.value) return;
    importing.value = true;
    importResult.value = null;
    try {
        const fd = new FormData();
        fd.append('file', importFile.value);
        fd.append('bank', importBank.value);
        const res = await apiAdmin(
            `bank-reconciliation/bank-accounts/${selectedAccount.value.id}/import-csv`,
            'post',
            fd,
        );
        importResult.value = res.data;
        toast('success', `Imported ${res.data.imported ?? 0} lines successfully.`);
        await loadLines();
    } catch (err) {
        importResult.value = { error: err?.response?.data?.message ?? 'Import failed.' };
    } finally {
        importing.value = false;
    }
};

const closeImportModal = () => {
    showImport.value = false;
    importFile.value = null;
    importResult.value = null;
    importBank.value = 'auto';
    if (csvFileInput.value) {
        csvFileInput.value.value = '';
    }
};

const loadBankAccounts = async () => {
    try {
        const res = await apiAdmin('bank-reconciliation/bank-accounts', 'get');
        bankAccounts.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    }
};

const loadGlAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { per_page: 500 });
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

const autoMatch = async () => {
    try {
        const res = await apiAdmin(`bank-reconciliation/bank-accounts/${selectedAccount.value.id}/auto-match`, 'post');
        toast('success', res.data.message);
        await loadLines();
    } catch (e) {
        showErrors(e);
    }
};

const unmatch = async (id) => {
    try {
        await apiAdmin(`bank-reconciliation/statement-lines/${id}/unmatch`, 'post');
        await loadLines();
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
        newAccount.value = { account_id: null, bank_name: '', account_number: '', branch: '' };
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
