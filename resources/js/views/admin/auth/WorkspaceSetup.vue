<template>
    <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
        <div class="card shadow-sm" style="max-width:520px;width:100%">
            <div class="card-body p-5">

                <!-- Pending / running -->
                <template v-if="status !== 'complete' && status !== 'failed'">
                    <div class="text-center mb-4">
                        <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
                        <h4 class="fw-semibold">Setting up your workspace…</h4>
                        <p class="text-muted mb-0">We're preparing your accounts, roles, and defaults. This takes a few seconds.</p>
                    </div>

                    <div v-if="steps.length" class="mt-3">
                        <div
                            v-for="step in steps"
                            :key="step.name"
                            class="d-flex align-items-center gap-2 mb-2"
                        >
                            <span v-if="step.status === 'ok'" class="text-success fs-14">
                                <i class="ti ti-circle-check-filled"></i>
                            </span>
                            <span v-else-if="step.status === 'failed'" class="text-danger fs-14">
                                <i class="ti ti-circle-x-filled"></i>
                            </span>
                            <span v-else class="text-muted fs-14">
                                <i class="ti ti-circle-dashed"></i>
                            </span>
                            <span class="fs-13 text-body">{{ step.name }}</span>
                            <span v-if="step.duration_ms != null" class="text-muted fs-11 ms-auto">
                                {{ step.duration_ms }} ms
                            </span>
                        </div>
                    </div>

                    <div v-else class="progress mt-3" style="height:6px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                    </div>
                </template>

                <!-- Complete -->
                <template v-else-if="status === 'complete'">
                    <div class="text-center mb-4">
                        <i class="ti ti-circle-check text-success mb-2" style="font-size:3rem"></i>
                        <h4 class="fw-semibold">Your workspace is ready!</h4>
                        <p class="text-muted mb-0">Redirecting to your dashboard…</p>
                    </div>
                    <div class="progress mt-3" style="height:6px">
                        <div class="progress-bar bg-success w-100"></div>
                    </div>
                </template>

                <!-- Failed -->
                <template v-else>
                    <div class="text-center mb-4">
                        <i class="ti ti-alert-circle text-danger mb-2" style="font-size:3rem"></i>
                        <h4 class="fw-semibold">Setup encountered an issue</h4>
                        <p class="text-muted">Some defaults could not be applied. You can continue — most features will still work — or contact support if you see missing data.</p>
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn btn-primary" @click="proceed">Continue to Dashboard</button>
                        <button class="btn btn-outline-secondary" @click="retry">Retry Setup</button>
                    </div>
                </template>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { apiAdmin } from '@/helpers/api';
import { useAdminAuthStore } from '@/stores/admin/auth';

const router = useRouter();
const authStore = useAdminAuthStore();

const status = ref('not_started');
const steps = ref([]);
let pollTimer = null;

async function pollStatus() {
    try {
        const { data } = await apiAdmin('provision/status', 'get');
        status.value = data.status;
        steps.value = data.steps ?? [];

        if (data.status === 'complete') {
            authStore.setNeedsOnboarding(true);
            clearInterval(pollTimer);
            setTimeout(proceed, 1200);
        } else if (data.status === 'failed') {
            clearInterval(pollTimer);
        }
    } catch {
        // Network hiccup — keep polling
    }
}

function proceed() {
    authStore.setNeedsOnboarding(true);
    router.push({ name: 'admin.onboarding' });
}

async function retry() {
    status.value = 'not_started';
    steps.value = [];
    await pollStatus();
    pollTimer = setInterval(pollStatus, 2000);
}

onMounted(() => {
    pollStatus();
    pollTimer = setInterval(pollStatus, 2000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
});
</script>
