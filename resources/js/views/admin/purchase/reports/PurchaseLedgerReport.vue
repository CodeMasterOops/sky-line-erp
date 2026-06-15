<template>
    <PageHeader hide-action-buttons title="Purchase Ledger" subtitle="Statement of account for a specific supplier (AP ledger)" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-wallet fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Opening Balance</p>
                            <h4 class="mb-0">{{ supplierSelected ? formatMoney(kpi.opening_balance) : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Closing Balance</p>
                            <h4 class="mb-0">{{ supplierSelected ? formatMoney(kpi.closing_balance) : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-list fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Transactions</p>
                            <h4 class="mb-0">{{ supplierSelected ? rows.length : '—' }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="filters.party_id" :class="{'is-invalid': !filters.party_id && attemptedLoad}">
                            <option value="">Select Supplier</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <div v-if="!filters.party_id && attemptedLoad" class="invalid-feedback">Please select a supplier.</div>
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="triggerLoad" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="supplierSelected && reportData" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="mb-1">{{ reportData.party?.name }}</h6>
                <p class="text-muted mb-0 small">{{ reportData.party?.phone }} {{ reportData.party?.email }}</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th class="text-end">Debit (DR)</th>
                                <th class="text-end">Credit (CR)</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!supplierSelected">
                                <td colspan="7" class="text-center text-muted py-4">Select a supplier to view ledger.</td>
                            </tr>
                            <tr v-else-if="loading">
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="7" class="text-center text-muted py-4">No transactions found.</td>
                            </tr>
                            <template v-else>
                                <tr class="table-light">
                                    <td colspan="6" class="fw-semibold">Opening Balance</td>
                                    <td class="text-end fw-semibold">{{ formatMoney(reportData.opening_balance) }}</td>
                                </tr>
                                <tr v-for="(row, i) in rows" :key="i">
                                    <td>{{ i + 1 }}</td>
                                    <td>{{ row.date }}</td>
                                    <td>
                                        <span class="badge" :class="row.type === 'Bill' ? 'bg-info-subtle text-info' : row.type === 'Payment' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                            {{ row.type }}
                                        </span>
                                    </td>
                                    <td>{{ row.reference }}</td>
                                    <td class="text-end text-success">{{ row.debit > 0 ? formatMoney(row.debit) : '—' }}</td>
                                    <td class="text-end text-danger">{{ row.credit > 0 ? formatMoney(row.credit) : '—' }}</td>
                                    <td class="text-end fw-semibold">{{ formatMoney(row.balance) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const reportData = ref(null);
const loading = ref(false);
const partyOptions = ref([]);
const attemptedLoad = ref(false);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({party_id: '', from_date: '', to_date: ''});

const supplierSelected = computed(() => !!filters.value.party_id);
const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => ({
    opening_balance: reportData.value?.opening_balance ?? 0,
    closing_balance: reportData.value?.closing_balance ?? 0,
}));

const triggerLoad = async () => {
    attemptedLoad.value = true;
    if (!filters.value.party_id) { return; }
    await loadReport();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/purchase-ledger', 'get', params);
        reportData.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Type', 'Reference', 'Debit (DR)', 'Credit (CR)', 'Balance'];
    const data = rows.value.map(r => [r.date, r.type, r.reference, r.debit, r.credit, r.balance]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'purchase-ledger.csv';
    a.click();
};

onMounted(async () => {
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        filters.value.to_date = now.toISOString().slice(0, 10);
    }
    // load party options by calling without party_id — backend returns party_options in that case
    try {
        const res = await apiAdmin('purchase-report/purchase-ledger', 'get', {from_date: filters.value.from_date, to_date: filters.value.to_date});
        partyOptions.value = res.data.data?.party_options ?? [];
    } catch (e) {
        showErrors(e);
    }
});
</script>
