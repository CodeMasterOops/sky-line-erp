<template>
    <PageHeader title="Cash Flow Forecast" subtitle="30 / 60 / 90 day cash projection">
    </PageHeader>

    <section class="section">
        <!-- Controls -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Forecast Period</label>
                        <select class="form-select form-select-sm" v-model="params.days" @change="loadForecast">
                            <option :value="30">30 Days</option>
                            <option :value="60">60 Days</option>
                            <option :value="90">90 Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Opening Balance (NPR)</label>
                        <input v-model="params.opening_balance" type="number" class="form-control form-control-sm"
                               placeholder="Current cash/bank balance" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" :disabled="loading" @click="loadForecast">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Run Forecast
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="forecast">
            <!-- Summary Cards -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Opening Balance</div>
                            <div class="fs-5 fw-bold text-secondary">NPR {{ fmtNum(forecast.opening_balance) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Expected Inflows</div>
                            <div class="fs-5 fw-bold text-success">NPR {{ fmtNum(forecast.summary.total_inflow) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Expected Outflows</div>
                            <div class="fs-5 fw-bold text-danger">NPR {{ fmtNum(forecast.summary.total_outflow) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="text-muted small">Projected Closing Balance</div>
                            <div :class="['fs-5 fw-bold', forecast.summary.closing_balance >= 0 ? 'text-primary' : 'text-danger']">
                                NPR {{ fmtNum(forecast.summary.closing_balance) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day-by-day table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Day-by-Day Cash Flow ({{ forecast.from }} to {{ forecast.to }})</span>
                    <small class="text-muted">{{ forecast.daily.length }} active days</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end text-success">Inflow</th>
                                    <th class="text-end text-danger">Outflow</th>
                                    <th class="text-end">Net</th>
                                    <th class="text-end">Running Balance</th>
                                    <th style="width:120px">Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in forecast.daily" :key="row.date"
                                    :class="row.running_balance < 0 ? 'table-danger' : ''">
                                    <td>{{ row.date }}</td>
                                    <td class="text-end text-success">{{ row.inflow > 0 ? fmtNum(row.inflow) : '—' }}</td>
                                    <td class="text-end text-danger">{{ row.outflow > 0 ? fmtNum(row.outflow) : '—' }}</td>
                                    <td class="text-end" :class="row.net >= 0 ? 'text-success' : 'text-danger'">
                                        {{ fmtNum(row.net) }}
                                    </td>
                                    <td class="text-end fw-bold">NPR {{ fmtNum(row.running_balance) }}</td>
                                    <td>
                                        <div class="progress" style="height:6px">
                                            <div class="progress-bar"
                                                :class="row.running_balance < 0 ? 'bg-danger' : 'bg-primary'"
                                                :style="`width:${Math.min(100, Math.max(0, (row.running_balance / (forecast.summary.closing_balance || 1)) * 100))}%`">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="forecast.daily.length === 0">
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No cash flows projected in this period.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="text-muted small mt-2">
                <i class="ti ti-info-circle me-1"></i>
                Forecast is based on: unpaid invoice due dates (inflow), unpaid bill due dates (outflow), and pending PDC cheques.
            </div>
        </div>

        <div v-else-if="!loading" class="text-center py-5 text-muted">
            <i class="ti ti-chart-line fs-1 d-block mb-2"></i>
            Set the forecast period and opening balance, then click <strong>Run Forecast</strong>.
        </div>
    </section>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const forecast = ref(null);
const params = ref({ days: 90, opening_balance: 0 });

onMounted(loadForecast);

async function loadForecast() {
    loading.value = true;
    try {
        const p = new URLSearchParams({ days: params.value.days, opening_balance: params.value.opening_balance });
        const { data } = await apiAdmin(`cash-flow-forecast?${p}`);
        forecast.value = data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
}

function fmtNum(n) {
    return (n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
