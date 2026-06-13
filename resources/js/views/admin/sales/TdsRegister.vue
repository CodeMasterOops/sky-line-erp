<template>
    <PageHeader hide-action-buttons title="TDS Register" subtitle="Tax deducted at source register" @refresh="generateReport" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-4 col-md-6">
                        <label class="form-label">Date Range</label>
                        <div class="input-icon-start position-relative">
                            <input
                                ref="dateRangeInput"
                                type="text"
                                class="form-control"
                                placeholder="dd-mm-yyyy - dd-mm-yyyy"
                            >
                            <span class="input-icon-left"><i class="ti ti-calendar"></i></span>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="tdsRegister.loading"
                            @click="generateReport"
                        >
                            {{ tdsRegister.loading ? 'Loading...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Total TDS Base</p>
                        <h5 class="mb-0 text-primary">{{ formatAmount(tdsRegister.data.summary.total_base_amount) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Total TDS Deducted</p>
                        <h5 class="mb-0 text-danger">{{ formatAmount(tdsRegister.data.summary.total_tds_amount) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Transactions</p>
                        <h5 class="mb-0 text-secondary">{{ tdsRegister.data.rows.length }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <div class="px-3 pt-3 pb-2">
                    <h6 class="mb-0">TDS Deduction Register</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 tds-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th>TDS Category</th>
                                <th>Month</th>
                                <th class="text-end">Base Amount</th>
                                <th class="text-end">TDS Rate %</th>
                                <th class="text-end">TDS Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="tdsRegister.loading">
                                <td colspan="9" class="text-center py-4 text-muted">Loading...</td>
                            </tr>
                            <tr v-else-if="!tdsRegister.data.rows.length">
                                <td colspan="9" class="text-center py-4 text-muted">No TDS deductions found.</td>
                            </tr>
                            <tr v-for="row in tdsRegister.data.rows" :key="row.sn">
                                <td>{{ row.sn }}</td>
                                <td>{{ row.date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan }}</td>
                                <td>{{ row.tds_category_label }}</td>
                                <td>{{ row.period_month }}</td>
                                <td class="text-end">{{ formatAmount(row.base_amount) }}</td>
                                <td class="text-end">{{ row.tds_rate }}%</td>
                                <td class="text-end text-danger fw-medium">{{ formatAmount(row.tds_amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="tdsRegister.data.rows.length">
                            <tr class="fw-bold table-light">
                                <td colspan="6">Total</td>
                                <td class="text-end">{{ formatAmount(tdsRegister.data.summary.total_base_amount) }}</td>
                                <td></td>
                                <td class="text-end text-danger">{{ formatAmount(tdsRegister.data.summary.total_tds_amount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="tdsRegister.data.by_party.length" class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="px-3 pt-3 pb-2">
                    <h6 class="mb-0">Summary by Customer</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 tds-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>PAN</th>
                                <th class="text-end">Total Base</th>
                                <th class="text-end">Total TDS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in tdsRegister.data.by_party" :key="index">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.party_pan }}</td>
                                <td class="text-end">{{ formatAmount(row.total_base) }}</td>
                                <td class="text-end text-danger fw-medium">{{ formatAmount(row.total_tds) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useSalesReportStore } from '@/stores/admin/sales/report.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import DateRangePicker from 'daterangepicker';
import moment from 'moment';

const store = useSalesReportStore();
const settingStore = useAdminSettingStore();
const tdsRegister = computed(() => store.tdsRegister);

const dateRangeInput = ref(null);
const filter = reactive({ from_date: null, to_date: null });

const formatAmount = (value) => formatMoney(Number(value || 0));

const applyDateRange = (start, end) => {
    filter.from_date = start.format('YYYY-MM-DD');
    filter.to_date = end.format('YYYY-MM-DD');
    if (dateRangeInput.value) {
        dateRangeInput.value.value = `${start.format('DD-MM-YYYY')} - ${end.format('DD-MM-YYYY')}`;
    }
};

const generateReport = () => store.getTdsRegister({ from_date: filter.from_date, to_date: filter.to_date });

onMounted(async () => {
    await settingStore.getFiscalYears();
    const currentFiscalYear = settingStore.fiscalYears?.data?.find((y) => y.is_current);
    filter.from_date = currentFiscalYear?.start_date || moment().startOf('month').format('YYYY-MM-DD');
    filter.to_date = currentFiscalYear?.end_date || moment().format('YYYY-MM-DD');

    new DateRangePicker(
        dateRangeInput.value,
        { startDate: moment(filter.from_date), endDate: moment(filter.to_date), autoApply: true, locale: { format: 'DD-MM-YYYY' } },
        applyDateRange
    );

    await generateReport();
});
</script>

<style scoped>
.tds-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}
</style>
