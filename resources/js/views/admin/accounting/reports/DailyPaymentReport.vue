<template>
    <PageHeader hide-action-buttons title="Daily Payment Report" subtitle="Total payments made per day" />

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
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!dailySummary.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="dailySummary.length">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Daily Summary</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Vouchers</th>
                                    <th class="text-end">Total Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="day in dailySummary" :key="day.date">
                                    <td class="fw-semibold">{{ day.date }}</td>
                                    <td class="text-end">{{ day.count }}</td>
                                    <td class="text-end fw-semibold text-danger">{{ formatMoney(day.total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td>Total</td>
                                    <td class="text-end">{{ totalVouchers }}</td>
                                    <td class="text-end text-danger">{{ formatMoney(grandTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Voucher Details</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Voucher No</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in allRows" :key="row.id">
                                    <td>{{ i + 1 }}</td>
                                    <td>{{ row.date }}</td>
                                    <td>{{ row.voucher_no }}</td>
                                    <td><span class="badge bg-danger-subtle text-danger">{{ row.type_label }}</span></td>
                                    <td class="text-end fw-semibold">{{ formatMoney(row.total_cr) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!loading" class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="ti ti-building-bank display-4 d-block mb-3"></i>
                Select a date range and click 'Generate' to view daily payments.
            </div>
        </div>

        <div v-if="loading" class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <div class="spinner-border spinner-border-sm me-2"></div>Loading...
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

const allRows = ref([]);
const loading = ref(false);
const filters = ref({from_date: '', to_date: ''});

const dailySummary = computed(() => {
    const map = {};
    allRows.value.forEach((row) => {
        if (!map[row.date]) { map[row.date] = {date: row.date, count: 0, total: 0}; }
        map[row.date].count++;
        map[row.date].total += Number(row.total_cr ?? 0);
    });
    return Object.values(map).sort((a, b) => a.date.localeCompare(b.date));
});

const totalVouchers = computed(() => dailySummary.value.reduce((s, d) => s + d.count, 0));
const grandTotal = computed(() => dailySummary.value.reduce((s, d) => s + d.total, 0));

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {journal_type: 'payment-voucher'};
        if (filters.value.from_date) { params.start_date = filters.value.from_date; }
        if (filters.value.to_date) { params.end_date = filters.value.to_date; }
        const res = await apiAdmin('account-report/journal-report', 'get', params);
        const data = res.data.data;
        allRows.value = (data?.rows ?? []).map((row) => ({
            ...row,
            total_cr: row.items?.reduce((s, item) => s + Number(item.cr_amount ?? 0), 0) ?? 0,
        }));
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!dailySummary.value.length) { return; }
    const headers = ['Date', 'Vouchers', 'Total Paid'];
    const data = dailySummary.value.map((d) => [d.date, d.count, d.total]);
    const csv = [headers, ...data].map((r) => r.map((v) => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'daily-payment.csv';
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
