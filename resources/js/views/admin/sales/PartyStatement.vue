<template>
    <PageHeader hide-action-buttons title="Party Statement" subtitle="Customer ledger statement" @refresh="generateReport" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-3 col-md-6">
                        <VMultiselect
                            id="party_id"
                            v-model="filter.party_id"
                            :options="partyOptions"
                            label="Customer"
                            :disabled="partyStatement.loading"
                        />
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <label class="form-label">Date Range</label>
                        <div class="input-icon-start position-relative">
                            <input
                                ref="dateRangeInput"
                                type="text"
                                class="form-control"
                                placeholder="dd-mm-yyyy - dd-mm-yyyy"
                            >
                            <span class="input-icon-left">
                                <i class="ti ti-calendar"></i>
                            </span>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="partyStatement.loading || !filter.party_id"
                            @click="generateReport"
                        >
                            {{ partyStatement.loading ? 'Loading...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="partyStatement.data.party">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-1">{{ partyStatement.data.party.name }}</h5>
                            <p class="text-muted mb-0">PAN: {{ partyStatement.data.party.pan || '—' }}</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1 text-muted small">Period: {{ partyStatement.data.period?.label }}</p>
                            <p class="mb-0">
                                Closing Balance:
                                <strong :class="partyStatement.data.summary.closing_balance > 0 ? 'text-danger' : 'text-success'">
                                    {{ formatAmount(partyStatement.data.summary.closing_balance) }}
                                </strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Total Invoiced</p>
                            <h5 class="mb-0 text-primary">{{ formatAmount(partyStatement.data.summary.total_invoiced) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Total Received</p>
                            <h5 class="mb-0 text-success">{{ formatAmount(partyStatement.data.summary.total_received) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Closing Balance</p>
                            <h5 class="mb-0" :class="partyStatement.data.summary.closing_balance > 0 ? 'text-danger' : 'text-success'">
                                {{ formatAmount(partyStatement.data.summary.closing_balance) }}
                            </h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 stmt-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit (DR)</th>
                                    <th class="text-end">Credit (CR)</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="partyStatement.loading">
                                    <td colspan="8" class="text-center py-4 text-muted">Loading...</td>
                                </tr>
                                <tr v-else-if="!partyStatement.data.rows.length">
                                    <td colspan="8" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                                <tr
                                    v-for="(row, index) in partyStatement.data.rows"
                                    :key="index"
                                    :class="row.type === 'invoice' ? 'table-light' : ''"
                                >
                                    <td>{{ index + 1 }}</td>
                                    <td>{{ row.date }}</td>
                                    <td>
                                        <span class="badge" :class="row.type === 'invoice' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success'">
                                            {{ row.type === 'invoice' ? 'Invoice' : 'Receipt' }}
                                        </span>
                                    </td>
                                    <td>{{ row.reference }}</td>
                                    <td>{{ row.description }}</td>
                                    <td class="text-end">{{ row.debit > 0 ? formatAmount(row.debit) : '—' }}</td>
                                    <td class="text-end text-success">{{ row.credit > 0 ? formatAmount(row.credit) : '—' }}</td>
                                    <td class="text-end fw-medium" :class="row.balance > 0 ? 'text-danger' : 'text-success'">
                                        {{ formatAmount(row.balance) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
        <div v-else class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                Select a customer and click Generate to view their statement.
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useSalesReportStore } from '@/stores/admin/sales/report.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import { usePartyStore } from '@/stores/admin/party.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import DateRangePicker from 'daterangepicker';
import moment from 'moment';

const store = useSalesReportStore();
const settingStore = useAdminSettingStore();
const partyStore = usePartyStore();

const partyStatement = computed(() => store.partyStatement);
const partyOptions = computed(() =>
    (partyStore.parties?.data || []).map((p) => ({ id: String(p.id), name: p.name }))
);

const dateRangeInput = ref(null);
let pickerInstance = null;

const filter = reactive({ party_id: null, from_date: null, to_date: null });

const formatAmount = (value) => formatMoney(Number(value || 0));

const applyDateRange = (start, end) => {
    filter.from_date = start.format('YYYY-MM-DD');
    filter.to_date = end.format('YYYY-MM-DD');
    if (dateRangeInput.value) {
        dateRangeInput.value.value = `${start.format('DD-MM-YYYY')} - ${end.format('DD-MM-YYYY')}`;
    }
};

const generateReport = () => {
    if (!filter.party_id) return;
    store.getPartyStatement({ party_id: filter.party_id, from_date: filter.from_date, to_date: filter.to_date });
};

onMounted(async () => {
    await Promise.all([
        settingStore.getFiscalYears(),
        partyStore.getParties({ type: 'customer', per_page: 500 }),
    ]);

    const currentFiscalYear = settingStore.fiscalYears?.data?.find((y) => y.is_current);
    filter.from_date = currentFiscalYear?.start_date || moment().startOf('month').format('YYYY-MM-DD');
    filter.to_date = currentFiscalYear?.end_date || moment().format('YYYY-MM-DD');

    const startDate = moment(filter.from_date);
    const endDate = moment(filter.to_date);

    pickerInstance = new DateRangePicker(
        dateRangeInput.value,
        {
            startDate, endDate, autoApply: true,
            locale: { format: 'DD-MM-YYYY' },
        },
        applyDateRange
    );

    applyDateRange(startDate, endDate);
});
</script>

<style scoped>
.stmt-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}
</style>
