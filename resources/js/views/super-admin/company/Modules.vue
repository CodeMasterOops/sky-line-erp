<template>
    <PageHeader
        :title="matrix.meta.company_name ? `Modules — ${matrix.meta.company_name}` : 'Company Modules'"
        subtitle="Switching a module off hides it. Nothing is deleted, and switching it back on restores everything."
        @refresh="load"
    >
        <template #actions>
            <router-link :to="{ name: 'super-admin.company-list' }" class="btn btn-cancel me-2">
                <i class="ti ti-arrow-left me-1"></i>Companies
            </router-link>
            <button
                v-if="matrix.meta.category"
                type="button"
                class="btn btn-outline-primary"
                :disabled="saving"
                @click="resetToCategory"
            >
                <i class="ti ti-rotate me-1"></i>Reset to category
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="fs-12 fw-medium text-muted mb-2">Industry category</p>
                        <div class="d-flex align-items-center gap-2">
                            <select v-model="selectedCategoryId" class="form-select form-select-sm" style="max-width: 18rem">
                                <option :value="null">No category</option>
                                <option v-for="category in categories.data" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary"
                                :disabled="saving || selectedCategoryId === (matrix.meta.category?.id ?? null)"
                                @click="applyCategory"
                            >Apply</button>
                        </div>
                        <p class="fs-11 text-muted mt-2 mb-0">
                            Applying a category switches its modules on. It never switches anything off —
                            use the toggles below for that.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="fs-12 fw-medium text-muted mb-2">Subscription plan</p>
                        <h5 class="mb-1">{{ matrix.meta.plan?.name ?? 'No active plan' }}</h5>
                        <p class="fs-11 text-muted mb-0">
                            <template v-if="matrix.meta.plan_modules === null || matrix.meta.plan_modules === undefined">
                                This plan includes every module.
                            </template>
                            <template v-else>
                                Caps the company to {{ matrix.meta.plan_modules.length }} module(s). You can still
                                switch one on deliberately — it will be recorded as an override.
                            </template>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="matrix.loading" class="card">
            <div class="card-body text-center py-5 text-muted">Loading modules…</div>
        </div>

        <div v-for="section in sections" v-else :key="section.group" class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0 text-uppercase fs-12">{{ groupLabel(section.group) }}</h6>
            </div>
            <div class="card-body pt-2">
                <div
                    v-for="module in section.modules"
                    :key="module.key"
                    class="module-row d-flex align-items-start justify-content-between gap-3 py-2"
                >
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2">
                            <i v-if="module.icon" :class="module.icon"></i>
                            <span class="fw-medium">{{ module.name }}</span>

                            <span v-if="module.locked" class="badge badge-sm bg-light text-dark">Always on</span>
                            <span
                                v-else-if="module.enabled && module.source === 'manual'"
                                class="badge badge-sm badge-info"
                            >Manual override</span>
                            <span
                                v-else-if="module.enabled && module.is_category_default"
                                class="badge badge-sm bg-light text-dark"
                            >Category default</span>
                            <span
                                v-if="!module.entitled_by_plan"
                                class="badge badge-sm badge-warning"
                            >Not in plan</span>
                        </div>

                        <div class="fs-11 text-muted">{{ module.description }}</div>
                        <div v-if="module.reason" class="fs-11 text-danger mt-1">{{ module.reason }}</div>
                        <div v-if="module.enabled && module.dependents.length" class="fs-11 text-muted mt-1">
                            Required by: {{ module.dependents.join(', ') }}
                        </div>
                    </div>

                    <div class="form-check form-switch flex-shrink-0 pt-1">
                        <input
                            :id="`module-${module.key}`"
                            class="form-check-input"
                            type="checkbox"
                            :checked="module.enabled"
                            :disabled="module.locked || saving"
                            @change="toggle(module, $event.target.checked)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 text-uppercase fs-12">Change history</h6>
            </div>
            <div class="card-body">
                <div v-if="!events.data.length" class="text-muted fs-12">No module changes recorded yet.</div>
                <ul v-else class="list-unstyled mb-0">
                    <li v-for="event in events.data" :key="event.id" class="d-flex gap-2 py-2 border-bottom">
                        <span
                            :class="['badge badge-sm flex-shrink-0', event.action === 'disabled' ? 'badge-danger' : 'badge-success']"
                        >{{ event.action_label }}</span>
                        <div>
                            <div class="fw-medium">{{ event.module_name }}</div>
                            <div class="fs-11 text-muted">
                                {{ event.reason || '—' }}
                                <span v-if="event.actor"> · by {{ event.actor }}</span>
                                <span> · {{ formatDate(event.created_at) }}</span>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { formatDate } from '@/helpers/helper.js';
import { useModuleStore } from '@/stores/super-admin/module';

const route = useRoute();
const moduleStore = useModuleStore();
const { matrix, events, categories, saving } = storeToRefs(moduleStore);

const companyId = computed(() => route.params.id);
const selectedCategoryId = ref(null);

const sections = computed(() => moduleStore.matrixByGroup);

const GROUP_LABELS = {
    core: 'Core',
    foundation: 'Foundation',
    optional: 'Optional',
    industry: 'Industry',
};

const groupLabel = (group) => GROUP_LABELS[group] ?? group;

const load = async () => {
    await Promise.all([
        moduleStore.getCatalogue(),
        moduleStore.getCategories({ filter: { limit: 100 } }),
        moduleStore.getCompanyModules(companyId.value),
        moduleStore.getCompanyModuleEvents(companyId.value, { filter: { limit: 15 } }),
    ]);
};

onMounted(load);

watch(
    () => matrix.value.meta.category,
    (category) => {
        selectedCategoryId.value = category?.id ?? null;
    },
    { immediate: true },
);

const refreshEvents = () => moduleStore.getCompanyModuleEvents(companyId.value, { filter: { limit: 15 } });

const save = async (modules, options = {}) => {
    try {
        const res = await moduleStore.updateCompanyModules(companyId.value, modules, options);
        toast(res.status, res.data.message);
        await refreshEvents();
    } catch (e) {
        showErrors(e);
        await moduleStore.getCompanyModules(companyId.value);
    }
};

/**
 * Turning a module off that others depend on needs a deliberate confirmation —
 * the server refuses without `cascade`, and the dependents are named here so
 * nobody removes half a company's navigation by accident.
 */
const toggle = async (module, checked) => {
    if (!checked) {
        const blockers = (module.dependents ?? []).filter(
            (key) => matrix.value.data.find((m) => m.key === key)?.enabled,
        );

        if (blockers.length) {
            const confirmed = await Swal.fire({
                title: `Also switch off ${blockers.length} dependent module(s)?`,
                text: `${module.name} is required by: ${blockers.join(', ')}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'red',
                confirmButtonText: 'Switch all off',
            });

            if (!confirmed.value) {
                await moduleStore.getCompanyModules(companyId.value);
                return;
            }

            await save({ [module.key]: false }, { cascade: true });
            return;
        }
    }

    await save({ [module.key]: checked });
};

const applyCategory = async () => {
    try {
        const res = await moduleStore.applyCategory(companyId.value, {
            company_category_id: selectedCategoryId.value,
            apply_defaults: true,
        });
        toast(res.status, res.data.message);
        await refreshEvents();
    } catch (e) {
        showErrors(e);
    }
};

const resetToCategory = () => {
    Swal.fire({
        title: 'Reset to category defaults?',
        text: 'Manual overrides for this company will be undone. No data is deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reset',
    }).then(async (result) => {
        if (!result.value) {
            return;
        }

        try {
            const res = await moduleStore.resetToCategory(companyId.value);
            toast(res.status, res.data.message);
            await refreshEvents();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>

<style scoped>
.module-row + .module-row {
    border-top: 1px solid var(--bs-border-color, #dee2e6);
}
</style>
