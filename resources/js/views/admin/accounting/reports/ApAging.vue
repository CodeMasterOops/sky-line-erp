<template>
    <PageHeader title="Accounts Payable Aging" subtitle="Outstanding bills by age" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">As of Date (End Date)</label>
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

    <div v-if="buckets" class="row g-3 mb-3">
        <div class="col" v-for="(b, key) in bucketLabels" :key="key">
            <div class="card border-0" :class="b.bg">
                <div class="card-body text-center p-3">
                    <div class="text-muted small">{{ b.label }}</div>
                    <div class="fw-bold">NPR {{ fmt(buckets[key]) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0">Outstanding Bills {{ data.as_of ? '– As of ' + data.as_of : '' }}</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Bill No</th>
                            <th>Supplier</th>
                            <th>Bill Date</th>
                            <th>Due Date</th>
                            <th class="text-end">Days Overdue</th>
                            <th class="text-end">Outstanding (NPR)</th>
                            <th>Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="7" class="text-center text-muted py-4">No outstanding bills found.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.bill_no">
                            <td>{{ row.bill_no }}</td>
                            <td>{{ row.party_name }}</td>
                            <td>{{ row.bill_date }}</td>
                            <td>{{ row.due_date }}</td>
                            <td class="text-end" :class="row.days_overdue > 0 ? 'text-danger' : ''">{{ row.days_overdue }}</td>
                            <td class="text-end fw-semibold">{{ fmt(row.outstanding) }}</td>
                            <td><span class="badge" :class="bucketBadge(row.bucket)">{{ bucketDisplay(row.bucket) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const filters = ref({ end_date: new Date().toISOString().split('T')[0] });
const loading = ref(false);
const data = ref({});
const rows = ref([]);
const buckets = ref(null);

const bucketLabels = {
    current: { label: 'Current', bg: 'bg-success-subtle' },
    '1_30': { label: '1-30 Days', bg: 'bg-warning-subtle' },
    '31_60': { label: '31-60 Days', bg: 'bg-orange-subtle' },
    '61_90': { label: '61-90 Days', bg: 'bg-danger-subtle' },
    over_90: { label: '90+ Days', bg: 'bg-danger' },
    total: { label: 'Total', bg: 'bg-primary-subtle' },
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/ap-aging', 'get', filters.value);
        data.value = res.data.data;
        rows.value = data.value.rows || [];
        buckets.value = data.value.buckets;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });
const bucketDisplay = (key) => ({ current: 'Current', '1_30': '1-30d', '31_60': '31-60d', '61_90': '61-90d', over_90: '90+d' })[key] || key;
const bucketBadge = (key) => ({ current: 'bg-success', '1_30': 'bg-warning text-dark', '31_60': 'bg-warning text-dark', '61_90': 'bg-danger', over_90: 'bg-danger' })[key] || 'bg-secondary';
</script>
