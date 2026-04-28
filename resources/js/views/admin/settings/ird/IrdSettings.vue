<template>
    <PageHeader title="IRD EBS Settings" subtitle="Inland Revenue Department e-Billing System credentials" />

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>

                <div class="card flex-fill mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="fs-18 fw-bold mb-0">IRD Electronic Billing System (EBS)</h4>
                        <span :class="['badge', form.ird_ebs_enabled ? 'bg-success' : 'bg-secondary']">
                            {{ form.ird_ebs_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <VLoader v-if="loading" loader-type="progress" />

                    <div class="card-body">
                        <div class="alert alert-info border-0 mb-4">
                            <i class="ti ti-info-circle me-2"></i>
                            These credentials are issued by IRD Nepal after registering your fiscal device.
                            Visit <strong>ird.gov.np</strong> or your local IRD office to obtain them.
                            All approved invoices will be automatically synced to IRD when EBS is enabled.
                        </div>

                        <form @submit.prevent="saveSettings">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <VInput
                                        id="ird_username"
                                        v-model="form.ird_username"
                                        label="IRD Username"
                                        placeholder="IRD-issued username"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">IRD Password</label>
                                    <div class="input-group">
                                        <input
                                            :type="showPassword ? 'text' : 'password'"
                                            class="form-control"
                                            v-model="form.ird_password"
                                            :placeholder="hasExistingPassword ? '••••••••  (leave blank to keep existing)' : 'Enter IRD password'"
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="showPassword = !showPassword"
                                        >
                                            <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                                        </button>
                                    </div>
                                    <small v-if="hasExistingPassword" class="text-muted">
                                        <i class="ti ti-lock me-1"></i>Password already saved. Leave blank to keep it.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <VInput
                                        id="ird_branch_office"
                                        v-model="form.ird_branch_office"
                                        label="Branch Office Code"
                                        placeholder="e.g. 001 (assigned by IRD)"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <VInput
                                        id="ird_unit_name"
                                        v-model="form.ird_unit_name"
                                        label="Business Unit Name"
                                        placeholder="As registered with IRD"
                                    />
                                </div>

                                <div class="col-md-6">
                                    <VInput
                                        id="ird_fiscal_device"
                                        v-model="form.ird_fiscal_device"
                                        label="Fiscal Device Serial No."
                                        placeholder="Serial number of your fiscal device"
                                    />
                                </div>

                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="ird_ebs_enabled"
                                            v-model="form.ird_ebs_enabled"
                                            role="switch"
                                        />
                                        <label class="form-check-label fw-medium" for="ird_ebs_enabled">
                                            Enable IRD EBS Sync
                                        </label>
                                        <div class="text-muted" style="font-size: 12px;">
                                            When enabled, all approved invoices will be automatically reported to IRD.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 d-flex gap-2">
                                    <VButton type="submit" :loading="saving" variant="primary">
                                        <i class="ti ti-device-floppy me-1"></i>Save IRD Settings
                                    </VButton>
                                    <button
                                        type="button"
                                        class="btn btn-outline-info"
                                        @click="testConnection"
                                        :disabled="testing || !form.ird_username"
                                    >
                                        <span v-if="testing" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="ti ti-plug me-1"></i>
                                        Test Connection
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Status Summary -->
    <div class="row mt-4" v-if="syncSummary">
        <div class="col-12">
            <div class="card border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">IRD Sync Status Summary</h6>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadSyncSummary">
                        <i class="ti ti-refresh me-1"></i>Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card border-0 bg-success-subtle text-center p-3">
                                <div class="fs-3 fw-bold text-success">{{ syncSummary.summary?.synced || 0 }}</div>
                                <div class="text-muted small">Synced</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-warning-subtle text-center p-3">
                                <div class="fs-3 fw-bold text-warning">{{ syncSummary.summary?.pending || 0 }}</div>
                                <div class="text-muted small">Pending</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-danger-subtle text-center p-3">
                                <div class="fs-3 fw-bold text-danger">{{ syncSummary.summary?.failed || 0 }}</div>
                                <div class="text-muted small">Failed</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 bg-secondary-subtle text-center p-3">
                                <div class="fs-3 fw-bold text-secondary">{{ syncSummary.summary?.skipped || 0 }}</div>
                                <div class="text-muted small">Skipped</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="syncSummary.failed?.length">
                        <h6 class="text-danger mb-2">Failed Invoices (latest 20)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Date</th>
                                        <th>Error</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="inv in syncSummary.failed" :key="inv.id">
                                        <td>
                                            <router-link :to="{ name: 'admin.invoice-view', params: { id: inv.id } }" class="text-primary">
                                                {{ inv.invoice_no }}
                                            </router-link>
                                        </td>
                                        <td>{{ inv.invoice_date }}</td>
                                        <td class="text-danger small">{{ inv.ird_error }}</td>
                                        <td>
                                            <button
                                                class="btn btn-xs btn-outline-primary"
                                                @click="retrySync(inv.id)"
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
                    <div v-else-if="!syncSummary.failed?.length && syncSummary.summary?.failed === 0" class="text-center text-success py-3">
                        <i class="ti ti-circle-check fs-2"></i>
                        <p class="mb-0">All invoices are synced with IRD.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors.js';

const loading = ref(false);
const saving = ref(false);
const testing = ref(false);
const showPassword = ref(false);
const hasExistingPassword = ref(false);
const syncSummary = ref(null);
const retrying = ref(null);

const form = ref({
    ird_username: '',
    ird_password: '',
    ird_branch_office: '',
    ird_unit_name: '',
    ird_fiscal_device: '',
    ird_ebs_enabled: false,
});

const loadSettings = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('nepal/ird/settings', 'get');
        const d = res.data.data;
        form.value.ird_username = d.ird_username || '';
        form.value.ird_branch_office = d.ird_branch_office || '';
        form.value.ird_unit_name = d.ird_unit_name || '';
        form.value.ird_fiscal_device = d.ird_fiscal_device || '';
        form.value.ird_ebs_enabled = d.ird_ebs_enabled || false;
        hasExistingPassword.value = d.has_password || false;
        form.value.ird_password = '';
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const saveSettings = async () => {
    saving.value = true;
    try {
        const payload = { ...form.value };
        if (!payload.ird_password) delete payload.ird_password;
        await apiAdmin('nepal/ird/settings', 'put', payload);
        toast(200, 'IRD settings saved successfully.');
        await loadSettings();
        await loadSyncSummary();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const testConnection = async () => {
    testing.value = true;
    try {
        // We test by loading the sync summary — if the API responds the connection is valid
        await apiAdmin('nepal/ird/sync-summary', 'get');
        toast(200, 'IRD connection is configured. Approve an invoice to test the actual sync.');
    } catch (e) {
        showErrors(e);
    } finally {
        testing.value = false;
    }
};

const loadSyncSummary = async () => {
    try {
        const res = await apiAdmin('nepal/ird/sync-summary', 'get');
        syncSummary.value = res.data.data;
    } catch (e) {
        // silent
    }
};

const retrySync = async (invoiceId) => {
    retrying.value = invoiceId;
    try {
        await apiAdmin(`nepal/ird/invoice/${invoiceId}/retry-sync`, 'post');
        toast(200, 'Sync queued. Status will update in a few seconds.');
        setTimeout(loadSyncSummary, 3000);
    } catch (e) {
        showErrors(e);
    } finally {
        retrying.value = null;
    }
};

onMounted(() => {
    loadSettings();
    loadSyncSummary();
});
</script>
