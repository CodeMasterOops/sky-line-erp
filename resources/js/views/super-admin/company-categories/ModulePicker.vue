<template>
    <div class="module-picker">
        <p v-if="hint" class="text-muted fs-12 mb-2">{{ hint }}</p>

        <div v-for="section in sections" :key="section.group" class="mb-3">
            <div class="fw-medium text-uppercase fs-11 text-muted mb-2">{{ groupLabel(section.group) }}</div>

            <div class="row g-2">
                <div v-for="module in section.modules" :key="module.key" class="col-md-6">
                    <label
                        class="module-option"
                        :class="{ 'is-checked': isChecked(module.key), 'is-locked': module.always_on }"
                    >
                        <input
                            type="checkbox"
                            class="form-check-input mt-0 me-2"
                            :checked="isChecked(module.key)"
                            :disabled="module.always_on"
                            @change="toggle(module.key, $event.target.checked)"
                        />
                        <span class="flex-grow-1">
                            <span class="d-block fw-medium">
                                <i v-if="module.icon" :class="[module.icon, 'me-1']"></i>{{ module.name }}
                            </span>
                            <span v-if="module.always_on" class="d-block fs-11 text-muted">Always on</span>
                            <span v-else-if="module.requires.length" class="d-block fs-11 text-muted">
                                Needs {{ module.requires.join(', ') }}
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useModuleStore } from '@/stores/super-admin/module';

defineProps({
    hint: { type: String, default: '' },
});

const selected = defineModel({ type: Array, default: () => [] });

const moduleStore = useModuleStore();
const { catalogue } = storeToRefs(moduleStore);

const sections = computed(() => moduleStore.catalogueByGroup);

const GROUP_LABELS = {
    core: 'Always included',
    foundation: 'Foundation',
    optional: 'Optional',
    industry: 'Industry',
};

const groupLabel = (group) => GROUP_LABELS[group] ?? group;

const isChecked = (key) =>
    catalogue.value.alwaysOn.includes(key) || (selected.value ?? []).includes(key);

/**
 * Selecting a module selects everything it requires, and clearing one clears
 * whatever depends on it — the same closure the server applies, done here so
 * the checkboxes never show a combination the backend would silently correct.
 */
const toggle = (key, checked) => {
    const current = new Set(selected.value ?? []);
    const entry = catalogue.value.data.find((m) => m.key === key);

    if (checked) {
        current.add(key);
        (entry?.requires ?? []).forEach((requirement) => current.add(requirement));
    } else {
        current.delete(key);
        (entry?.dependents ?? []).forEach((dependent) => current.delete(dependent));
    }

    selected.value = catalogue.value.data
        .filter((m) => !m.always_on && current.has(m.key))
        .map((m) => m.key);
};
</script>

<style scoped>
.module-option {
    display: flex;
    align-items: flex-start;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.5rem;
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.module-option.is-checked {
    border-color: var(--bs-primary, #0d6efd);
    background: rgba(13, 110, 253, 0.04);
}

.module-option.is-locked {
    cursor: default;
    opacity: 0.7;
}
</style>
