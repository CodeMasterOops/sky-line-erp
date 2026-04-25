<template>
    <PageHeader title="Kharid Khata" subtitle="VAT Purchase Register" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" v-model="filters.start_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" v-model="filters.end_date" />
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

    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0">{{ periodLabel || 'Kharid Khata – VAT Purchase Register' }}</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>SN</th>
                            <th>Date</th>
                            <th>Bill No</th>
                            <th>Supplier Name</th>
                            <th>Supplier PAN</th>
                            <th class="text-end">Taxable Amount</th>
                            <th class="text-end">Input VAT 13%</th>
                            <th class="text-end">Exempt</th>
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
                            <td>{{ row.bill_no }}</td>
                            <td>{{ row.supplier_name }}</td>
                            <td>{{ row.supplier_pan }}</td>
                            <td class="text-end">{{ fmt(row.taxable_amount) }}</td>
                            <td class="text-end text-success">{{ fmt(row.input_vat) }}</td>
                            <td class="text-end">{{ fmt(row.exempt_amount) }}</td>
                            <td class="text-end fw-semibold">{{ fmt(row.total_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="summary" class="table-secondary fw-bold">
                        <tr>
                            <td colspan="5">Total</td>
                            <td class="text-end">{{ fmt(summary.taxable_amount) }}</td>
                            <td class="text-end text-success">{{ fmt(summary.input_vat) }}</td>
                            <td class="text-end">{{ fmt(summary.exempt_amount) }}</td>
                            <td class="text-end">{{ fmt(summary.total_amount) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const filters = ref({ start_date: null, end_date: null });
const loading = ref(false);
const rows = ref([]);
const summary = ref(null);
const periodLabel = ref('');

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/vat-purchase-register', 'get', filters.value);
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

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

const exportCsv = () => {
    if (!rows.value.length) return;
    const headers = ['Date', 'Bill No', 'Supplier Name', 'Supplier PAN', 'Taxable Amount', 'Input VAT 13%', 'Exempt', 'Total'];
    const csvRows = rows.value.map(r => [
        r.date, r.bill_no, r.supplier_name, r.supplier_pan,
        r.taxable_amount, r.input_vat, r.exempt_amount, r.total_amount
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'kharid-khata.csv';
    a.click();
};
</script>
