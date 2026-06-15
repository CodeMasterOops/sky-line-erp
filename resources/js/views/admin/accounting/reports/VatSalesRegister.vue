<template>
    <PageHeader title="Bikri Khata" subtitle="VAT Sales Register" hide-action-buttons />

    <div class="card border-0 mb-3 no-print">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <VDatepicker id="start_date" label="Start Date" v-model="filters.start_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <VDatepicker id="end_date" label="End Date" v-model="filters.end_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-primary w-100" @click="loadReport" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-outline-secondary w-100" @click="exportCsv" :disabled="!rows.length">
                            <i class="ti ti-file-export me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ReportPrintShell
        v-if="rows.length || periodLabel"
        report-title="VAT Sales Register"
        :subtitle="periodLabel || 'Bikri Khata'"
        landscape
    >
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>SN</th>
                            <th>Date (Miti)</th>
                            <th>Bijak No</th>
                            <th>Buyer Name (Kretako Naam)</th>
                            <th>Buyer PAN</th>
                            <th class="text-end">Taxable Amount (Kar Laagne Mulya)</th>
                            <th class="text-end">VAT 13% (Bhyaat)</th>
                            <th class="text-end">Exempt (Kar Mukta)</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="9" class="text-center text-muted py-4">No data. Select a period and generate.</td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td>{{ idx + 1 }}</td>
                            <td>{{ row.date }}</td>
                            <td>{{ row.bijak_no }}</td>
                            <td>{{ row.buyer_name }}</td>
                            <td>{{ row.buyer_pan }}</td>
                            <td class="text-end">{{ formatMoney(row.taxable_amount) }}</td>
                            <td class="text-end text-danger">{{ formatMoney(row.vat_amount) }}</td>
                            <td class="text-end">{{ formatMoney(row.exempt_amount) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(row.total_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="summary" class="table-secondary fw-bold">
                        <tr>
                            <td colspan="5">Total</td>
                            <td class="text-end">{{ formatMoney(summary.taxable_amount) }}</td>
                            <td class="text-end text-danger">{{ formatMoney(summary.vat_amount) }}</td>
                            <td class="text-end">{{ formatMoney(summary.exempt_amount) }}</td>
                            <td class="text-end">{{ formatMoney(summary.total_amount) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
    </ReportPrintShell>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const filters = ref({ start_date: null, end_date: null });
const loading = ref(false);
const rows = ref([]);
const summary = ref(null);
const periodLabel = ref('');

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/vat-sales-register', 'get', filters.value);
        const d = res.data.data;
        rows.value = d.rows || [];
        summary.value = d.summary;
        periodLabel.value = d.period?.label || '';
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};


const exportCsv = () => {
    if (!rows.value.length) return;
    const headers = ['Date', 'Bijak No', 'Buyer Name', 'Buyer PAN', 'Taxable Amount', 'VAT 13%', 'Exempt', 'Total'];
    const csvRows = rows.value.map(r => [
        r.date, r.bijak_no, r.buyer_name, r.buyer_pan,
        r.taxable_amount, r.vat_amount, r.exempt_amount, r.total_amount
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'bikri-khata.csv';
    a.click();
};
</script>
