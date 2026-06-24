<template>
    <form @submit.prevent="onSubmit">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <template v-if="loading">
                    <RoleFormSkeleton />
                </template>

                <template v-else>
                    <div class="mb-4" style="max-width: 480px;">
                        <VInput
                            id="name"
                            v-model="form.name"
                            label="Role Name"
                            placeholder="e.g. Branch Manager"
                            required
                            @validate="validateField('name')"
                            :error="errors.name"
                        />
                    </div>

                    <div class="permissions-toolbar rounded-3 border bg-body-tertiary p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <h6 class="mb-1 fw-semibold">Permissions</h6>
                                <p class="text-muted small mb-0">
                                    <span class="fw-semibold text-body">{{ form.permissions.length }}</span>
                                    of {{ allPermissionValues.length }} permissions selected
                                </p>
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="search-box position-relative">
                                    <i class="ti ti-search search-icon"></i>
                                    <input
                                        v-model="search"
                                        type="text"
                                        class="form-control form-control-sm ps-5"
                                        placeholder="Search permissions..."
                                    >
                                    <button
                                        v-if="search"
                                        type="button"
                                        class="btn-clear"
                                        @click="search = ''"
                                        aria-label="Clear search"
                                    >
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="toggleAllPanels"
                                >
                                    <i class="ti me-1" :class="anyPanelOpen ? 'ti-fold' : 'ti-fold-down'"></i>
                                    {{ anyPanelOpen ? 'Collapse all' : 'Expand all' }}
                                </button>

                                <div class="form-check form-switch m-0 ps-0 d-flex align-items-center gap-2">
                                    <input
                                        id="select-all-permissions"
                                        class="form-check-input m-0"
                                        type="checkbox"
                                        role="switch"
                                        v-indeterminate="someSelected && !allSelected"
                                        :checked="allSelected"
                                        @change="toggleValues(allPermissionValues, $event.target.checked)"
                                    >
                                    <label class="form-check-label small" for="select-all-permissions">
                                        Select all
                                    </label>
                                </div>
                            </div>
                        </div>

                        <p v-if="errors.permissions" class="text-danger small mt-2 mb-0">
                            {{ errors.permissions }}
                        </p>
                    </div>

                    <div v-if="filteredModuleEntries.length" class="permission-modules">
                        <div
                            v-for="[module, permissionGroups] in filteredModuleEntries"
                            :key="module"
                            class="module-panel rounded-3 border mb-2"
                            :class="{ 'is-open': isOpen(module) }"
                        >
                            <button
                                type="button"
                                class="module-header"
                                @click="togglePanel(module)"
                            >
                                <span class="d-flex align-items-center gap-2 text-start">
                                    <i class="ti ti-chevron-right chevron"></i>
                                    <span class="fw-semibold">{{ module }}</span>
                                </span>
                                <span class="d-flex align-items-center gap-3" @click.stop>
                                    <span
                                        class="badge rounded-pill"
                                        :class="moduleSelectedCount(permissionGroups) > 0 ? 'text-bg-primary' : 'text-bg-light text-muted'"
                                    >
                                        {{ moduleSelectedCount(permissionGroups) }} / {{ moduleValues(permissionGroups).length }}
                                    </span>
                                    <span class="form-check m-0 ps-0">
                                        <input
                                            class="form-check-input m-0"
                                            type="checkbox"
                                            :aria-label="`Select all ${module} permissions`"
                                            v-indeterminate="isSome(moduleValues(permissionGroups)) && !isAll(moduleValues(permissionGroups))"
                                            :checked="isAll(moduleValues(permissionGroups))"
                                            @change="toggleValues(moduleValues(permissionGroups), $event.target.checked)"
                                        >
                                    </span>
                                </span>
                            </button>

                            <div v-show="isOpen(module)" class="module-body">
                                <div
                                    v-for="[group, permissions] in Object.entries(permissionGroups)"
                                    :key="`${module}-${group}`"
                                    class="permission-group"
                                >
                                    <div class="group-header">
                                        <label class="form-check m-0 ps-0 d-flex align-items-center gap-2">
                                            <input
                                                class="form-check-input m-0"
                                                type="checkbox"
                                                v-indeterminate="isSome(groupValues(permissions)) && !isAll(groupValues(permissions))"
                                                :checked="isAll(groupValues(permissions))"
                                                @change="toggleValues(groupValues(permissions), $event.target.checked)"
                                            >
                                            <span class="fw-semibold small text-uppercase text-muted">{{ group }}</span>
                                        </label>
                                    </div>

                                    <div class="row g-2 mt-1">
                                        <div
                                            v-for="permission in permissions"
                                            :key="permission.permission"
                                            class="col-xl-3 col-lg-4 col-md-6"
                                        >
                                            <div class="form-check">
                                                <input
                                                    :id="permission.permission"
                                                    v-model="form.permissions"
                                                    :value="permission.permission"
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    @change="validateField('permissions')"
                                                >
                                                <label class="form-check-label" :for="permission.permission">
                                                    {{ permission.description }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center text-muted py-5">
                        <i class="ti ti-search-off fs-2 d-block mb-2"></i>
                        <p class="mb-0">No permissions match "{{ search }}".</p>
                    </div>
                </template>
            </div>

            <div class="save-bar d-flex flex-wrap align-items-center justify-content-between gap-2 px-4 py-3">
                <span class="text-muted small">
                    <i class="ti ti-shield-check me-1"></i>
                    {{ form.permissions.length }} permission{{ form.permissions.length === 1 ? '' : 's' }} selected
                </span>
                <VButton :loading="submitting" :btn-label="submitLabel" :disabled="loading" />
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { array, object, string } from "yup";
import { useYup } from "@/helpers/yup";
import {
    countSelected,
    filterModules,
    flattenAll,
    flattenModule,
    groupValues,
    isAllSelected,
    isSomeSelected,
    toggleSelection,
} from "@/composables/rolePermissions";
import RoleFormSkeleton from "./RoleFormSkeleton.vue";

const form = defineModel({ type: Object, required: true });

const props = defineProps({
    permissionGroups: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: "Save Role",
    },
});

const emit = defineEmits(["submit"]);

const vIndeterminate = {
    mounted: (el, binding) => { el.indeterminate = Boolean(binding.value); },
    updated: (el, binding) => { el.indeterminate = Boolean(binding.value); },
};

const search = ref("");
const expandedModules = reactive(new Set());

const validations = object({
    name: string().required("Role name is required."),
    permissions: array().min(1, "Select at least one permission."),
});

const { errors, validateField, validateForm } = useYup(form.value, validations);

const moduleEntries = computed(() => Object.entries(props.permissionGroups ?? {}));

const allPermissionValues = computed(() => flattenAll(props.permissionGroups));

const filteredModuleEntries = computed(() => filterModules(props.permissionGroups, search.value));

const isSearching = computed(() => search.value.trim().length > 0);

const isOpen = (module) => isSearching.value || expandedModules.has(module);

const anyPanelOpen = computed(() =>
    filteredModuleEntries.value.some(([module]) => isOpen(module)),
);

const togglePanel = (module) => {
    if (expandedModules.has(module)) {
        expandedModules.delete(module);
    } else {
        expandedModules.add(module);
    }
};

const toggleAllPanels = () => {
    if (anyPanelOpen.value) {
        expandedModules.clear();
    } else {
        moduleEntries.value.forEach(([module]) => expandedModules.add(module));
    }
};

const moduleValues = (permissionGroups) => flattenModule(permissionGroups);

const isAll = (values) => isAllSelected(values, form.value.permissions);

const isSome = (values) => isSomeSelected(values, form.value.permissions);

const allSelected = computed(() => isAll(allPermissionValues.value));

const someSelected = computed(() => isSome(allPermissionValues.value));

const moduleSelectedCount = (permissionGroups) =>
    countSelected(moduleValues(permissionGroups), form.value.permissions);

const toggleValues = (values, checked) => {
    form.value.permissions = toggleSelection(form.value.permissions, values, checked);
    validateField("permissions");
};

watch(
    () => form.value.permissions,
    () => {
        if (errors.value?.permissions) {
            validateField("permissions");
        }
    },
);

const onSubmit = async () => {
    const validated = await validateForm();

    if (!validated) {
        return;
    }

    emit("submit");
};
</script>

<style scoped>
.search-box .search-icon {
    position: absolute;
    top: 50%;
    left: 0.75rem;
    transform: translateY(-50%);
    color: var(--bs-secondary-color);
    pointer-events: none;
}

.search-box .form-control {
    min-width: 220px;
}

.search-box .btn-clear {
    position: absolute;
    top: 50%;
    right: 0.4rem;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: var(--bs-secondary-color);
    line-height: 1;
    padding: 0.15rem;
}

.module-panel {
    background-color: var(--bs-body-bg);
    transition: border-color 0.15s ease;
}

.module-panel.is-open {
    border-color: var(--bs-primary-border-subtle);
}

.module-header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: transparent;
    border: 0;
    color: inherit;
}

.module-header:hover {
    background-color: var(--bs-tertiary-bg);
}

.module-header .chevron {
    transition: transform 0.15s ease;
    color: var(--bs-secondary-color);
}

.module-panel.is-open .module-header .chevron {
    transform: rotate(90deg);
}

.module-body {
    padding: 0.5rem 1rem 1rem;
    border-top: 1px solid var(--bs-border-color);
}

.permission-group + .permission-group {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--bs-border-color);
}

.group-header {
    margin-top: 0.75rem;
}

.save-bar {
    position: sticky;
    bottom: 0;
    background-color: var(--bs-body-bg);
    border-top: 1px solid var(--bs-border-color);
    border-bottom-left-radius: var(--bs-card-border-radius);
    border-bottom-right-radius: var(--bs-card-border-radius);
    z-index: 5;
}
</style>
