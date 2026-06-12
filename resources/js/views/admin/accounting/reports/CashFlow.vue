<template>
    <PageHeader hide-action-buttons title="Cash Flow Statement" subtitle="Indirect Method" />

    <div class="card border-0 mb-3 no-print">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Fiscal Year</label>
                        <Multiselect
                            v-model="filters.fiscal_year_id"
                            :options="fiscalYearOptions"
                            value-prop="value" label="label" :searchable="true"
                            placeholder="Select Fiscal Year"
                        />
                    </div>
                </div>
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
            </div>
        </div>
    </div>

    <ReportPrintShell
        v-if="data.period"
        report-title="Cash Flow Statement"
        :subtitle="data.period?.label || ''"
    >
            <table class="table">
                <tbody>
                    <tr class="table-secondary">
                        <td colspan="2"><strong>A. Operating Activities</strong></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Net cash from operating activities</td>
                        <td class="text-end fw-semibold" :class="data.operating >= 0 ? 'text-success' : 'text-danger'">
                            {{ formatMoney(data.operating) }}
                        </td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="2"><strong>B. Investing Activities</strong></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Net cash from investing activities</td>
                        <td class="text-end fw-semibold" :class="data.investing >= 0 ? 'text-success' : 'text-danger'">
                            {{ formatMoney(data.investing) }}
                        </td>
                    </tr>
                    <tr class="table-secondary">
                        <td colspan="2"><strong>C. Financing Activities</strong></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Net cash from financing activities</td>
                        <td class="text-end fw-semibold" :class="data.financing >= 0 ? 'text-success' : 'text-danger'">
                            {{ formatMoney(data.financing) }}
                        </td>
                    </tr>
                    <tr class="table-primary">
                        <td><strong>Net Increase / (Decrease) in Cash</strong></td>
                        <td class="text-end fw-bold fs-6" :class="data.net_change >= 0 ? 'text-success' : 'text-danger'">
                            {{ formatMoney(data.net_change) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="alert alert-info mt-3 no-print">
                <i class="ti ti-info-circle me-2"></i>
                Cash flow is calculated using the indirect method based on account group classifications (Income/Expenses → Operating; Assets → Investing; Liabilities/Equity → Financing).
            </div>
    </ReportPrintShell>

    <div v-else-if="!loading" class="text-center text-muted py-5 no-print">
        <i class="ti ti-chart-bar display-4 d-block mb-3"></i>
        Select a period and click Generate to view the Cash Flow Statement.
    </div>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import ReportPrintShell from '@/components/print/ReportPrintShell.vue';

const filters = ref({ fiscal_year_id: null, start_date: null, end_date: null });
const loading = ref(false);
const data = ref({});
const fiscalYearOptions = ref([]);

const loadFiscalYears = async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year', 'get');
        fiscalYearOptions.value = (res.data.data || []).map(fy => ({ label: fy.year_name, value: fy.id }));
    } catch (e) { /* ignore */ }
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/cash-flow', 'get', filters.value);
        data.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};


onMounted(() => { loadFiscalYears(); });
</script>
