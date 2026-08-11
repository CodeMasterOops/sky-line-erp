<template>
    <div class="page-wrapper">
        <div class="content">
            <div class="d-flex flex-column align-items-center justify-content-center text-center py-5">
                <div class="module-unavailable-icon mb-3">
                    <i class="ti ti-plug-connected-x"></i>
                </div>

                <h4 class="mb-2">{{ moduleLabel }} isn't enabled</h4>

                <p class="text-muted mb-4" style="max-width: 32rem">
                    This part of the workspace belongs to a module your company doesn't
                    currently run. Nothing has been lost — if it's switched on again, all
                    of its data and settings come back exactly as they were.
                </p>

                <p class="text-muted small mb-4">
                    Ask your administrator to enable it for your company.
                </p>

                <router-link :to="{ name: 'admin.dashboard' }" class="btn btn-primary">
                    <i class="ti ti-arrow-left me-1"></i> Back to dashboard
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useModuleCatalogue } from '@/composables/useModuleCatalogue';

const route = useRoute();

// Names come from config/modules.php through the catalogue endpoint. This
// screen used to keep its own copy of the module list, which quietly went stale
// every time a module was added to the registry.
const { load, moduleLabel: labelFor } = useModuleCatalogue();

onMounted(load);

const moduleLabel = computed(() => labelFor(route.query.module));
</script>

<style scoped>
.module-unavailable-icon {
    display: grid;
    place-items: center;
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: var(--bs-secondary-bg, #f1f3f5);
    color: var(--bs-secondary-color, #6c757d);
    font-size: 1.75rem;
}
</style>
