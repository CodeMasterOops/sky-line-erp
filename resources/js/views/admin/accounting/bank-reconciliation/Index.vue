<template>
    <PageHeader title="Bank Reconciliation" subtitle="Match bank statement lines to GL entries" />

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Bank Accounts</h6>
                    <button class="btn btn-sm btn-primary" @click="showAddAccount = true">
                        <i class="ti ti-plus"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div
                        v-for="ba in bankAccounts"
                        :key="ba.id"
                        class="p-3 border-bottom cursor-pointer"
                        :class="{ 'bg-primary-subtle': selectedAccount?.id === ba.id }"
                        @click="selectAccount(ba)"
                    >
                        <div class="fw-semibold">{{ ba.bank_name }}</div>
                        <div class="text-muted small">{{ ba.account_number }}</div>
                        <div class="text-muted small">{{ ba.account?.name }}</div>
                    </div>
                    <div v-if="!bankAccounts.length" class="p-3 text-muted text-center small">
                        No bank accounts. Add one.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div v-if="selectedAccount">
                <div class="card border-0 mb-3">
                    <div class="card-body d-flex gap-3 flex-wrap align-items-end">
                        <div>
                            <label class="form-label small">Start Date</label>
                            <input type="date" class="form-control form-control-sm" v-model="filters.start_date" />
                        </div>
                        <div>
                            <label class="form-label small">End Date</label>
                            <input type="date" class="form-control form-control-sm" v-model="filters.end_date" />
                        </div>
                        <button class="btn btn-sm btn-primary" @click="loadLines">Load</button>
                        <button class="btn btn-sm btn-outline-secondary" @click="autoMatch">
                            <i class="ti ti-wand me-1"></i> Auto Match
                        </button>
                        <button class="btn btn-sm btn-outline-primary" @click="showImport = true">
                            <i class="ti ti-file-import me-1"></i> Import CSV
                        </button>
                    </div>
                </div>

                <div v-if="summary" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="card border-0 bg-info-subtle p-3 text-center">
                            <div class="text-muted small">GL Balance</div>
                            <div class="fw-bold">NPR {{ fmt(summary.gl_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary-subtle p-3 text-center">
                            <div class="text-muted small">Statement Balance</div>
                            <div class="fw-bold">NPR {{ fmt(summary.statement_balance) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 p-3 text-center" :class="Math.abs(summary.difference) < 0.01 ? 'bg-success-subtle' : 'bg-danger-subtle'">
                            <div class="text-muted small">Difference</div>
                            <div class="fw-bold" :class="Math.abs(summary.difference) < 0.01 ? 'text-success' : 'text-danger'">
                                NPR {{ fmt(summary.difference) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0">
                    <div class="card-header">
                        <h6 class="mb-0">Statement Lines – {{ selectedAccount.bank_name }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
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
                                    <tr v-if="!lines.length && !loading">
                                        <td colspan="8" class="text-center text-muted py-4">No statement lines. Import or add manually.</td>
                                    </tr>
                                    <tr v-for="line in lines" :key="line.id" :class="line.status === 'matched' ? 'table-success' : ''">
                                        <td>{{ formatDate(line.transaction_date) }}</td>
                                        <td>{{ line.description }}</td>
                                        <td>{{ line.reference }}</td>
                                        <td class="text-end">{{ fmt(line.debit) }}</td>
                                        <td class="text-end">{{ fmt(line.credit) }}</td>
                                        <td>
                                            <span class="badge" :class="line.status === 'matched' ? 'bg-success' : 'bg-warning text-dark'">
                                                {{ line.status }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">{{ line.journal_item?.journal?.voucher_no || '-' }}</td>
                                        <td>
                                            <button v-if="line.status === 'matched'" class="btn btn-xs btn-outline-danger" @click="unmatch(line.id)">
                                                Unmatch
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center text-muted py-5">
                <i class="ti ti-building-bank display-4 d-block mb-3"></i>
                Select a bank account to view statement lines.
            </div>
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div v-if="showAddAccount" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Bank Account</h5>
                    <button type="button" class="btn-close" @click="showAddAccount = false"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">GL Account (Cash/Bank) *</label>
                        <vue-select v-model="newAccount.account_id" :options="glAccountOptions" :reduce="o => o.value" placeholder="Select account" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bank Name *</label>
                        <input type="text" class="form-control" v-model="newAccount.bank_name" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number *</label>
                        <input type="text" class="form-control" v-model="newAccount.account_number" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <input type="text" class="form-control" v-model="newAccount.branch" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showAddAccount = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveBankAccount">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';

const bankAccounts = ref([]);
const selectedAccount = ref(null);
const lines = ref([]);
const summary = ref(null);
const loading = ref(false);
const showAddAccount = ref(false);
const showImport = ref(false);
const glAccountOptions = ref([]);
const filters = ref({ start_date: null, end_date: null, status: null });
const newAccount = ref({ account_id: null, bank_name: '', account_number: '', branch: '' });

const loadBankAccounts = async () => {
    try {
        const res = await apiAdmin('bank-reconciliation/bank-accounts', 'get');
        bankAccounts.value = res.data.data || [];
    } catch (e) { showErrors(e); }
};

const loadGlAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { per_page: 500 });
        glAccountOptions.value = (res.data.data || []).map(a => ({ label: `${a.name} (${a.code})`, value: a.id }));
    } catch (e) { /* ignore */ }
};

const selectAccount = async (ba) => {
    selectedAccount.value = ba;
    await loadLines();
};

const loadLines = async () => {
    if (!selectedAccount.value) return;
    loading.value = true;
    try {
        const res = await apiAdmin(`bank-reconciliation/bank-accounts/${selectedAccount.value.id}/statement-lines`, 'get', filters.value);
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
    } catch (e) { showErrors(e); }
};

const unmatch = async (id) => {
    try {
        await apiAdmin(`bank-reconciliation/statement-lines/${id}/unmatch`, 'post');
        await loadLines();
    } catch (e) { showErrors(e); }
};

const saveBankAccount = async () => {
    try {
        await apiAdmin('bank-reconciliation/bank-accounts', 'post', newAccount.value);
        toast('success', 'Bank account added.');
        showAddAccount.value = false;
        newAccount.value = { account_id: null, bank_name: '', account_number: '', branch: '' };
        await loadBankAccounts();
    } catch (e) { showErrors(e); }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => {
    loadBankAccounts();
    loadGlAccounts();
});
</script>
