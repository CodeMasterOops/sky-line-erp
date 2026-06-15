<template>
    <PageHeader hide-action-buttons title="Customer Statement" subtitle="Formal account statement for a customer" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3 no-print">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="filters.party_id">
                            <option value="">— Select Customer —</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading || !filters.party_id">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                    </div>
                </div>
                <p v-if="attemptedLoad && !filters.party_id" class="text-danger small mt-2 mb-0">Please select a customer.</p>
            </div>
        </div>

        <ReportPrintShell
            v-if="reportData && rows.length"
            report-title="Customer Statement"
            :subtitle="reportData.period?.label"
        >
            <div class="mb-4 d-flex justify-content-between">
                <div>
                    <div class="fw-bold fs-5">{{ reportData.party?.name }}</div>
                    <div v-if="reportData.party?.code" class="text-muted small">Code: {{ reportData.party.code }}</div>
                    <div v-if="reportData.party?.pan" class="text-muted small">PAN: {{ reportData.party.pan }}</div>
                    <div v-if="reportData.party?.phone" class="text-muted small">Phone: {{ reportData.party.phone }}</div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Opening Balance</div>
                    <div class="fw-semibold">
                        {{ formatMoney(Math.abs(reportData.opening_balance)) }}
                        <small class="text-muted">{{ reportData.opening_balance >= 0 ? 'DR' : 'CR' }}</small>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
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
                        <tr v-if="loading">
                            <td colspan="6" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td class="text-nowrap">{{ row.date }}</td>
                            <td>
                                <span class="badge" :class="{
                                    'bg-primary': row.type === 'Invoice',
                                    'bg-success': row.type === 'Receipt',
                                    'bg-warning text-dark': row.type === 'Credit Note',
                                }">{{ row.type }}</span>
                            </td>
                            <td class="fw-semibold">{{ row.reference }}</td>
                            <td class="text-end">{{ row.debit > 0 ? formatMoney(row.debit) : '-' }}</td>
                            <td class="text-end">{{ row.credit > 0 ? formatMoney(row.credit) : '-' }}</td>
                            <td class="text-end fw-semibold" :class="row.balance >= 0 ? 'text-primary' : 'text-success'">
                                {{ formatMoney(Math.abs(row.balance)) }}
                                <small class="text-muted ms-1">{{ row.balance >= 0 ? 'DR' : 'CR' }}</small>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5" class="text-end">Closing Balance</td>
                            <td class="text-end" :class="reportData.closing_balance >= 0 ? 'text-primary' : 'text-success'">
                                {{ formatMoney(Math.abs(reportData.closing_balance)) }}
                                <small class="text-muted ms-1">{{ reportData.closing_balance >= 0 ? 'DR' : 'CR' }}</small>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </ReportPrintShell>

        <div v-else-if="!loading" class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5 no-print">
                <i class="ti ti-file-description display-4 d-block mb-3"></i>
                Select a customer and click 'Generate' to produce the statement.
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
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

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
        const res = await apiAdmin('sales-report/sales-ledger', 'get', params);
        reportData.value = res.data.data;
        rows.value = reportData.value?.rows || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
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
        const res = await apiAdmin('sales-report/sales-ledger', 'get', {});
        partyOptions.value = res.data.data?.party_options || [];
    } catch { /* ignore */ }
});
</script>
