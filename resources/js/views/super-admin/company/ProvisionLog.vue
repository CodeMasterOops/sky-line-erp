<template>
    <PageHeader
        title="Provision Log"
        :subtitle="company?.company_name || 'Company provisioning audit'"
        @refresh="fetchLog"
    >
        <template #actions>
            <router-link
                :to="{name:'super-admin.company-list'}"
                class="btn btn-outline-secondary d-flex align-items-center me-2"
            >
                <i class="ti ti-arrow-left me-2"></i> Back
            </router-link>
            <button
                class="btn btn-primary d-flex align-items-center"
                :disabled="reprovisioning"
                @click="reprovision"
            >
                <span v-if="reprovisioning" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="ti ti-refresh me-2"></i>
                Re-Provision
            </button>
        </template>
    </PageHeader>

    <VLoader v-if="loading" loader-type="progress"/>

    <template v-else>
        <!-- Summary card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Company</small>
                        <strong>{{ company?.company_name }}</strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Onboarding Completed</small>
                        <span v-if="onboardingCompletedAt" class="badge bg-success">
                            {{ onboardingCompletedAt }}
                        </span>
                        <span v-else class="badge bg-warning text-dark">Pending</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Provision Runs</small>
                        <strong>{{ logs.length }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="logs.length === 0" class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="ti ti-database-off fs-48 mb-3 d-block"></i>
                No provisioning logs found. Click <strong>Re-Provision</strong> to start.
            </div>
        </div>

        <div v-for="log in logs" :key="log.id" class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span :class="statusBadge(log.status)">{{ log.status }}</span>
                    <small class="text-muted">Run #{{ log.id }}</small>
                </div>
                <small class="text-muted">{{ log.started_at ? formatDateTime(log.started_at) : '—' }}</small>
            </div>
            <div class="card-body p-0">
                <div v-if="log.error" class="alert alert-danger m-3 mb-0">
                    <strong>Error:</strong> {{ log.error }}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Step</th>
                                <th>Status</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(step, i) in log.steps" :key="step.name">
                                <td class="text-muted">{{ i + 1 }}</td>
                                <td>{{ step.name }}</td>
                                <td>
                                    <span :class="stepBadge(step.status)">{{ step.status }}</span>
                                </td>
                                <td class="text-muted">
                                    {{ step.duration_ms != null ? step.duration_ms + ' ms' : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted small d-flex justify-content-between">
                <span>{{ log.step_count }} steps completed</span>
                <span v-if="log.completed_at">Finished {{ formatDateTime(log.completed_at) }}</span>
            </div>
        </div>
    </template>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { toast } from '@/helpers/toast';

const route = useRoute();
const companyId = route.params.id;

const loading = ref(true);
const reprovisioning = ref(false);
const company = ref(null);
const onboardingCompletedAt = ref(null);
const logs = ref([]);

async function fetchLog() {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/super-admin/company/${companyId}/provision-log`);
        company.value = { company_name: data.company_name };
        onboardingCompletedAt.value = data.onboarding_completed_at
            ? formatDateTime(data.onboarding_completed_at)
            : null;
        logs.value = data.logs;
    } finally {
        loading.value = false;
    }
}

async function reprovision() {
    reprovisioning.value = true;
    try {
        await axios.post(`/api/super-admin/company/${companyId}/reprovision`);
        toast.success('Provisioning job queued. Refresh in a moment.');
        setTimeout(fetchLog, 3000);
    } catch {
        toast.error('Failed to queue provisioning job.');
    } finally {
        reprovisioning.value = false;
    }
}

function statusBadge(status) {
    return {
        badge: true,
        'bg-success': status === 'complete',
        'bg-danger': status === 'failed',
        'bg-warning text-dark': status === 'running' || status === 'pending',
        'bg-secondary': status === 'superseded',
    };
}

function stepBadge(status) {
    return {
        badge: true,
        'bg-success': status === 'ok',
        'bg-danger': status === 'failed',
        'bg-secondary': !status,
    };
}

function formatDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

onMounted(fetchLog);
</script>
