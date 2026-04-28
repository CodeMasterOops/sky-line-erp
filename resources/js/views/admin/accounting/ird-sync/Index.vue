<template>
    <PageHeader
        title="IRD EBS Sync Status"
        subtitle="Monitor IRD e-invoicing sync across all approved invoices"
        @refresh="loadData"
    />

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <div class="fs-2 fw-bold text-success">{{ counts.synced || 0 }}</div>
                <div class="text-muted small">Synced to IRD</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning-subtle text-center p-3">
                <div class="fs-2 fw-bold text-warning">{{ counts.pending || 0 }}</div>
                <div class="text-muted small">Pending Sync</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-danger-subtle text-center p-3">
                <div class="fs-2 fw-bold text-danger">{{ counts.failed || 0 }}</div>
                <div class="text-muted small">Failed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-secondary-subtle text-center p-3">
                <div class="fs-2 fw-bold text-secondary">{{ counts.skipped || 0 }}</div>
                <div class="text-muted small">Skipped (IRD disabled)</div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-danger">
                <i class="ti ti-alert-triangle me-1"></i>
                Failed Invoices — Require Attention
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" @click="retryAll" :disabled="retryingAll || !failedInvoices.length">
                    <span v-if="retryingAll" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="ti ti-refresh me-1"></i>
                    Retry All Failed
                </button>
                <button class="btn btn-sm btn-outline-secondary" @click="loadData">
                    <i class="ti ti-refresh"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <VLoader v-if="loading" loader-type="progress" />
            <div v-else-if="!failedInvoices.length" class="text-center py-5 text-success">
                <i class="ti ti-circle-check fs-1 d-block mb-2"></i>
                <h5>All invoices are synced with IRD!</h5>
                <p class="text-muted small mb-0">No failed syncs at this time.</p>
            </div>
            <div v-else class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Last Attempted</th>
                            <th>Error</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in failedInvoices" :key="inv.id">
                            <td>
                                <router-link :to="{ name: 'admin.invoice-view', params: { id: inv.id } }" class="fw-medium text-primary">
                                    {{ inv.invoice_no }}
                                </router-link>
                            </td>
                            <td>{{ inv.invoice_date }}</td>
                            <td class="text-muted small">{{ inv.updated_at }}</td>
                            <td>
                                <span class="text-danger small">{{ inv.ird_error || 'Unknown error' }}</span>
                            </td>
                            <td>
                                <button
                                    class="btn btn-xs btn-outline-primary"
                                    @click="retryOne(inv.id)"
                                    :disabled="retrying === inv.id"
                                >
                                    <span v-if="retrying === inv.id" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="ti ti-refresh"></i>
                                    Retry
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <router-link :to="{ name: 'admin.ird-settings' }" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-settings me-1"></i>IRD EBS Settings
        </router-link>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors.js';

const loading = ref(false);
const counts = ref({});
const failedInvoices = ref([]);
const retrying = ref(null);
const retryingAll = ref(false);

const loadData = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('nepal/ird/sync-summary', 'get');
        counts.value = res.data.data.summary || {};
        failedInvoices.value = res.data.data.failed || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const retryOne = async (id) => {
    retrying.value = id;
    try {
        await apiAdmin(`nepal/ird/invoice/${id}/retry-sync`, 'post');
        toast(200, 'Sync queued. Refreshing in 3 seconds...');
        setTimeout(loadData, 3000);
    } catch (e) {
        showErrors(e);
    } finally {
        retrying.value = null;
    }
};

const retryAll = async () => {
    retryingAll.value = true;
    try {
        const promises = failedInvoices.value.map(inv =>
            apiAdmin(`nepal/ird/invoice/${inv.id}/retry-sync`, 'post').catch(() => null)
        );
        await Promise.allSettled(promises);
        toast(200, `${failedInvoices.value.length} invoices queued for retry.`);
        setTimeout(loadData, 4000);
    } finally {
        retryingAll.value = false;
    }
};

onMounted(loadData);
</script>
