<template>
    <PageHeader hide-action-buttons title="Supplier Transaction Report" subtitle="All transactions for a selected supplier" />

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
                            <h4 class="mb-0">
                                {{ reportData ? formatMoney(Math.abs(reportData.opening_balance)) : '—' }}
                                <small v-if="reportData" class="fs-6 fw-normal text-muted">{{ reportData.opening_balance >= 0 ? 'DR' : 'CR' }}</small>
                            </h4>
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
                            <h4 class="mb-0">
                                {{ reportData ? formatMoney(Math.abs(reportData.closing_balance)) : '—' }}
                                <small v-if="reportData" class="fs-6 fw-normal text-muted">{{ reportData.closing_balance >= 0 ? 'DR' : 'CR' }}</small>
                            </h4>
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
                            <h4 class="mb-0">{{ rows.length }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <VMultiselect
                            id="party_id"
                            v-model="filters.party_id"
                            :options="partyOptions"
                            label="Supplier"
                            placeholder="— Select Supplier —"
                            required
                        />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading || !filters.party_id">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
                <p v-if="attemptedLoad && !filters.party_id" class="text-danger small mt-2 mb-0">Please select a supplier.</p>
            </div>
        </div>

        <div v-if="reportData?.party" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <strong>{{ reportData.party.name }}</strong>
                <span v-if="reportData.party.code" class="text-muted ms-2">{{ reportData.party.code }}</span>
                <span v-if="reportData.party.pan" class="text-muted ms-3">PAN: {{ reportData.party.pan }}</span>
                <span class="ms-3 text-muted small">Period: {{ reportData.period?.label }}</span>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th class="text-end">Debit (DR)</th>
                                <th class="text-end">Credit (CR)</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="6" class="text-center text-muted py-5">
                                    {{ filters.party_id ? 'Click Generate to load transactions.' : 'Select a supplier to get started.' }}
                                </td>
                            </tr>
                            <tr v-if="loading">
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td class="text-nowrap">{{ row.date }}</td>
                                <td>
                                    <span class="badge" :class="{
                                        'bg-info': row.type === 'Bill',
                                        'bg-success': row.type === 'Payment',
                                        'bg-danger': row.type === 'Debit Note',
                                    }">{{ row.type }}</span>
                                </td>
                                <td class="fw-semibold">{{ row.reference }}</td>
                                <td class="text-end text-primary fw-semibold">{{ row.debit > 0 ? formatMoney(row.debit) : '-' }}</td>
                                <td class="text-end text-success fw-semibold">{{ row.credit > 0 ? formatMoney(row.credit) : '-' }}</td>
                                <td class="text-end fw-bold" :class="row.balance >= 0 ? 'text-primary' : 'text-success'">
                                    {{ formatMoney(Math.abs(row.balance)) }}
                                    <small class="text-muted ms-1">{{ row.balance >= 0 ? 'DR' : 'CR' }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {formatMoney} from '@/helpers/formatMoney.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const rows = ref([]);
const reportData = ref(null);
const loading = ref(false);
const partyOptions = ref([]);
const attemptedLoad = ref(false);
const filters = ref({party_id: '', from_date: '', to_date: ''});

const loadReport = async () => {
    attemptedLoad.value = true;
    if (!filters.value.party_id) { return; }
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/purchase-ledger', 'get', params);
        reportData.value = res.data.data;
        rows.value = reportData.value?.rows || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Type', 'Reference', 'Debit (DR)', 'Credit (CR)', 'Balance'];
    const csvRows = rows.value.map((r) => [r.date, r.type, r.reference, r.debit, r.credit, r.balance].map((v) => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'supplier-transaction.csv';
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
    try {
        const res = await apiAdmin('purchase-report/purchase-ledger', 'get', {
            from_date: filters.value.from_date,
            to_date: filters.value.to_date,
        });
        partyOptions.value = res.data.data?.party_options || [];
    } catch { /* ignore */ }
});
</script>
