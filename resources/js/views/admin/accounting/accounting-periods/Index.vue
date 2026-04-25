<template>
    <PageHeader title="Accounting Periods" subtitle="Manage and close accounting periods" />

    <div class="card border-0 mb-3">
        <div class="card-body d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label class="form-label">Fiscal Year</label>
                <vue-select
                    v-model="selectedFiscalYear"
                    :options="fiscalYearOptions"
                    :reduce="opt => opt.value"
                    placeholder="Select Fiscal Year"
                    class="w-200px"
                    @update:modelValue="loadPeriods"
                />
            </div>
            <button class="btn btn-outline-primary" @click="generatePeriods" :disabled="!selectedFiscalYear || generating">
                <span v-if="generating" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-refresh me-1"></i>
                Generate Periods
            </button>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Period</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Closed By</th>
                            <th>Closed At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!periods.length && !loading">
                            <td colspan="8" class="text-center text-muted py-4">
                                Select a fiscal year and generate periods.
                            </td>
                        </tr>
                        <tr v-for="period in periods" :key="period.id">
                            <td>{{ period.period_number }}</td>
                            <td>{{ period.period_name }}</td>
                            <td>{{ formatDate(period.start_date) }}</td>
                            <td>{{ formatDate(period.end_date) }}</td>
                            <td>
                                <span class="badge" :class="statusBadge(period.status)">{{ period.status }}</span>
                            </td>
                            <td>{{ period.closed_by?.name || '-' }}</td>
                            <td>{{ formatDate(period.closed_at) }}</td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button
                                        v-if="period.status === 'open'"
                                        class="btn btn-sm btn-warning"
                                        @click="closePeriod(period.id)"
                                    >Close</button>
                                    <button
                                        v-if="period.status === 'closed'"
                                        class="btn btn-sm btn-outline-success"
                                        @click="reopenPeriod(period.id)"
                                    >Reopen</button>
                                    <button
                                        v-if="period.status === 'closed'"
                                        class="btn btn-sm btn-danger"
                                        @click="lockPeriod(period.id)"
                                    >Lock</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';

const selectedFiscalYear = ref(null);
const fiscalYearOptions = ref([]);
const periods = ref([]);
const loading = ref(false);
const generating = ref(false);

const loadFiscalYears = async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year', 'get');
        fiscalYearOptions.value = (res.data.data || []).map(fy => ({ label: fy.year_name, value: fy.id }));
    } catch (e) { /* ignore */ }
};

const loadPeriods = async () => {
    if (!selectedFiscalYear.value) return;
    loading.value = true;
    try {
        const res = await apiAdmin('accounting-period', 'get', { fiscal_year_id: selectedFiscalYear.value });
        periods.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const generatePeriods = async () => {
    if (!selectedFiscalYear.value) return;
    generating.value = true;
    try {
        const res = await apiAdmin('accounting-period/generate', 'post', { fiscal_year_id: selectedFiscalYear.value });
        toast('success', res.data.message);
        await loadPeriods();
    } catch (e) {
        showErrors(e);
    } finally {
        generating.value = false;
    }
};

const closePeriod = async (id) => {
    try {
        const res = await apiAdmin(`accounting-period/${id}/close`, 'post');
        toast('success', res.data.message);
        await loadPeriods();
    } catch (e) { showErrors(e); }
};

const reopenPeriod = async (id) => {
    try {
        const res = await apiAdmin(`accounting-period/${id}/reopen`, 'post');
        toast('success', res.data.message);
        await loadPeriods();
    } catch (e) { showErrors(e); }
};

const lockPeriod = async (id) => {
    try {
        const res = await apiAdmin(`accounting-period/${id}/lock`, 'post');
        toast('success', res.data.message);
        await loadPeriods();
    } catch (e) { showErrors(e); }
};

const statusBadge = (status) => ({
    open: 'bg-success',
    closed: 'bg-warning text-dark',
    locked: 'bg-danger',
}[status] || 'bg-secondary');

onMounted(() => { loadFiscalYears(); });
</script>
