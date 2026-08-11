<template>
    <div>
        <PageHeader
            title="Modules"
            subtitle="What your workspace runs, and what it could."
            :hide-action-buttons="true"
        />
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>

                <div class="card flex-fill mb-0">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="fs-18 fw-bold mb-0">Modules</h4>
                        <span class="badge bg-outline-primary">{{ enabledCount }} of {{ modules.length }} enabled</span>
                    </div>

                    <div class="card-body">
                        <p class="text-muted fs-13">
                            Modules are switched on by your administrator. Turning one off never
                            deletes anything — its data waits exactly where it is, and comes back
                            untouched if it is switched on again.
                        </p>

                        <div v-if="loading" class="text-center py-5">
                            <span class="spinner-border text-primary"></span>
                        </div>

                        <template v-else>
                            <div v-for="group in groups" :key="group.name" class="mb-4">
                                <h6 class="text-uppercase text-muted fs-12 fw-semibold mb-2">
                                    {{ group.label }}
                                </h6>

                                <div class="row g-2">
                                    <div
                                        v-for="module in group.items"
                                        :key="module.key"
                                        class="col-lg-6 d-flex"
                                    >
                                        <div
                                            class="module-card flex-fill"
                                            :class="{ 'module-card--off': !module.enabled }"
                                        >
                                            <div class="d-flex align-items-start gap-2">
                                                <span class="module-card__icon">
                                                    <i :class="module.icon || 'ti ti-plug'"></i>
                                                </span>
                                                <div class="min-w-0 flex-fill">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <h6 class="mb-0">{{ module.name }}</h6>
                                                        <span
                                                            class="badge badge-xs"
                                                            :class="module.enabled ? 'bg-outline-success' : 'bg-outline-secondary'"
                                                        >
                                                            {{ module.enabled ? 'Enabled' : 'Not enabled' }}
                                                        </span>
                                                        <span v-if="module.locked" class="badge badge-xs bg-outline-info">
                                                            Always on
                                                        </span>
                                                    </div>
                                                    <p class="text-muted fs-12 mb-1">{{ module.description }}</p>
                                                    <p v-if="!module.enabled && module.reason" class="fs-12 mb-0 text-warning">
                                                        {{ module.reason }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="events.length" class="mt-4">
                                <h6 class="text-uppercase text-muted fs-12 fw-semibold mb-2">Recent changes</h6>
                                <div class="table-responsive">
                                    <table class="table table-borderless custom-table mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Module</th>
                                                <th>Change</th>
                                                <th>Reason</th>
                                                <th>When</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="event in events" :key="event.id">
                                                <td class="fw-semibold">{{ event.module_name }}</td>
                                                <td>{{ event.action_label }}</td>
                                                <td class="text-muted">{{ event.reason || '–' }}</td>
                                                <td class="text-muted">{{ formatDate(event.created_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { apiAdmin } from '@/helpers/api';
import { useDisplayDate } from '@/composables/useDisplayDate.js';
import showErrors from '@/helpers/showErrors';

/**
 * Read-only. Switching modules is the Super Admin's job; what a tenant needs is
 * to see what they have, why something is off ("Not included in the Basic
 * plan", "Requires: inventory"), and that nothing was lost when it went off.
 */
const GROUP_LABELS = {
    core: 'Always included',
    foundation: 'Foundation',
    optional: 'Optional',
    industry: 'Industry',
};

const { formatDate } = useDisplayDate();

const modules = ref([]);
const events = ref([]);
const loading = ref(true);

const enabledCount = computed(() => modules.value.filter((m) => m.enabled).length);

const groups = computed(() =>
    Object.entries(GROUP_LABELS)
        .map(([name, label]) => ({
            name,
            label,
            items: modules.value.filter((m) => m.group === name),
        }))
        .filter((group) => group.items.length > 0),
);

onMounted(async () => {
    try {
        const [state, history] = await Promise.all([
            apiAdmin('module', 'get'),
            apiAdmin('module/event', 'get', { limit: 10 }),
        ]);

        modules.value = state.data?.data ?? [];
        events.value = history.data?.data ?? [];
    } catch (err) {
        showErrors(err);
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.module-card {
    border: 1px solid var(--bs-border-color, #e9ecef);
    border-radius: 0.5rem;
    padding: 0.875rem;
}

.module-card--off {
    background: var(--bs-secondary-bg, #f8f9fa);
}

.module-card__icon {
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 0.5rem;
    background: var(--bs-secondary-bg, #f1f3f5);
    font-size: 1.125rem;
}
</style>
