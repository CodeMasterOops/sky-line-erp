<template>
    <PageHeader hide-action-buttons title="AR Aging Report" subtitle="Accounts receivable aging" @refresh="generateReport" />

    <section class="section">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-xl-3 col-md-5">
                        <label class="form-label">As Of Date</label>
                        <input
                            v-model="filter.as_of_date"
                            type="date"
                            class="form-control"
                        >
                    </div>
                    <div class="col-xl-2 col-md-3">
                        <button
                            type="button"
                            class="btn btn-success w-100"
                            :disabled="aging.loading"
                            @click="generateReport"
                        >
                            {{ aging.loading ? 'Loading...' : 'Generate' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Current</p>
                        <h5 class="mb-0 text-success">{{ formatAmount(aging.data.buckets.current) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">1–30 Days</p>
                        <h5 class="mb-0 text-warning">{{ formatAmount(aging.data.buckets['1_30']) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">31–60 Days</p>
                        <h5 class="mb-0 text-orange">{{ formatAmount(aging.data.buckets['31_60']) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">61–90 Days</p>
                        <h5 class="mb-0 text-danger">{{ formatAmount(aging.data.buckets['61_90']) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Over 90 Days</p>
                        <h5 class="mb-0 text-danger fw-bold">{{ formatAmount(aging.data.buckets.over_90) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Total Outstanding</p>
                        <h5 class="mb-0 text-primary fw-bold">{{ formatAmount(aging.data.buckets.total) }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 aging-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th class="text-end">Outstanding</th>
                                <th>Bucket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="aging.loading">
                                <td colspan="8" class="text-center py-4 text-muted">Loading...</td>
                            </tr>
                            <tr v-else-if="!aging.data.rows.length">
                                <td colspan="8" class="text-center py-4 text-muted">No outstanding invoices.</td>
                            </tr>
                            <tr v-for="(row, index) in aging.data.rows" :key="row.invoice_no">
                                <td>{{ index + 1 }}</td>
                                <td>{{ row.invoice_no }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.invoice_date }}</td>
                                <td>{{ row.due_date }}</td>
                                <td>
                                    <span :class="daysClass(row.days_overdue)">
                                        {{ row.days_overdue }} days
                                    </span>
                                </td>
                                <td class="text-end fw-medium">{{ formatAmount(row.outstanding) }}</td>
                                <td>
                                    <span class="badge" :class="bucketBadge(row.bucket)">
                                        {{ bucketLabel(row.bucket) }}
                                    </span>
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
import { reactive, computed, onMounted } from 'vue';
import { useSalesReportStore } from '@/stores/admin/sales/report.js';
import { formatMoney } from '@/helpers/formatMoney.js';

const store = useSalesReportStore();
const aging = computed(() => store.aging);

const today = new Date().toISOString().slice(0, 10);
const filter = reactive({ as_of_date: today });

const formatAmount = (value) => formatMoney(Number(value || 0));

const daysClass = (days) => {
    if (days === 0) return 'text-success';
    if (days <= 30) return 'text-warning';
    if (days <= 60) return 'text-orange';
    return 'text-danger fw-bold';
};

const bucketLabel = (bucket) => {
    const labels = { current: 'Current', '1_30': '1-30 Days', '31_60': '31-60 Days', '61_90': '61-90 Days', over_90: 'Over 90' };
    return labels[bucket] || bucket;
};

const bucketBadge = (bucket) => {
    const classes = { current: 'bg-success-subtle text-success', '1_30': 'bg-warning-subtle text-warning', '31_60': 'bg-orange-subtle text-orange', '61_90': 'bg-danger-subtle text-danger', over_90: 'bg-danger text-white' };
    return classes[bucket] || 'bg-secondary-subtle text-secondary';
};

const generateReport = () => store.getAging({ as_of_date: filter.as_of_date });

onMounted(generateReport);
</script>

<style scoped>
.aging-table thead th {
    background: #eef3f9;
    border-color: #dbe5f0;
    color: #384860;
    font-size: 13px;
    font-weight: 700;
}
.text-orange { color: #f97316; }
</style>
