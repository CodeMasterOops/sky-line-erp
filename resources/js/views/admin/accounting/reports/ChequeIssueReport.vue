<template>
    <PageHeader hide-action-buttons title="Cheque Issue Report" subtitle="Cheques issued by the company" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" v-model="filters.status">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="presented">Presented</option>
                            <option value="cleared">Cleared</option>
                            <option value="bounced">Bounced</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Cheque No</th>
                                <th>Cheque Date</th>
                                <th>Party</th>
                                <th>Bank</th>
                                <th>Branch</th>
                                <th>Deposit Date</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="ti ti-file-description display-4 d-block mb-3"></i>
                                    Select a date range and click 'Generate' to view issued cheques.
                                </td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="row.id">
                                <td>{{ i + 1 }}</td>
                                <td class="fw-semibold">{{ row.cheque_no }}</td>
                                <td>{{ row.cheque_date }}</td>
                                <td>{{ row.party?.name ?? '—' }}</td>
                                <td>{{ row.bank_name }}</td>
                                <td>{{ row.bank_branch }}</td>
                                <td>{{ row.deposit_date ?? '—' }}</td>
                                <td>
                                    <span class="badge" :class="statusClass(row.status)">{{ row.status }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="table-light fw-semibold">
                            <tr>
                                <td colspan="8">Total</td>
                                <td class="text-end">{{ formatMoney(grandTotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import {formatMoney} from '@/helpers/formatMoney.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const rows = ref([]);
const loading = ref(false);
const filters = ref({from_date: '', to_date: '', status: ''});

const grandTotal = computed(() => rows.value.reduce((s, r) => s + Number(r.amount ?? 0), 0));

const statusClass = (status) => ({
    pending: 'bg-warning-subtle text-warning',
    presented: 'bg-info-subtle text-info',
    cleared: 'bg-success-subtle text-success',
    bounced: 'bg-danger-subtle text-danger',
    cancelled: 'bg-secondary-subtle text-secondary',
}[status] ?? 'bg-secondary-subtle text-secondary');

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {type: 'payable'};
        if (filters.value.from_date) { params.from_date = filters.value.from_date; }
        if (filters.value.to_date) { params.to_date = filters.value.to_date; }
        if (filters.value.status) { params.status = filters.value.status; }
        const res = await apiAdmin('cheque', 'get', params);
        rows.value = res.data.data ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Cheque No', 'Cheque Date', 'Party', 'Bank', 'Branch', 'Deposit Date', 'Status', 'Amount'];
    const data = rows.value.map((r) => [r.cheque_no, r.cheque_date, r.party?.name ?? '', r.bank_name, r.bank_branch, r.deposit_date ?? '', r.status, r.amount]);
    const csv = [headers, ...data].map((r) => r.map((v) => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'cheque-issue-report.csv';
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
});
</script>
